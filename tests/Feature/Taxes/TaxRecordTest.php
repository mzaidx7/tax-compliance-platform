<?php

declare(strict_types=1);

namespace Tests\Feature\Taxes;

use App\Actions\Filings\CreateFilingRecord;
use App\Actions\Taxes\AmendTaxRecord;
use App\Actions\Taxes\CreateTaxRecord;
use App\Enums\FilingStatus;
use App\Enums\FirmRole;
use App\Enums\TaxRecordStatus;
use App\Enums\TaxType;
use App\Enums\WorkItemStatus;
use App\Livewire\Obligations\Index;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Firm;
use App\Models\FirmMembership;
use App\Models\Obligation;
use App\Models\TaxRecord;
use App\Models\User;
use App\Models\WorkItem;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use LogicException;
use Tests\Concerns\BuildsFirmTenancy;
use Tests\TestCase;

final class TaxRecordTest extends TestCase
{
    use BuildsFirmTenancy;
    use RefreshDatabase;

    public function test_manager_opens_a_draft_tax_record_with_opening_history_and_audit(): void
    {
        $fixture = $this->fixture();

        $taxRecord = app(CreateTaxRecord::class)->handle(
            $fixture['manager'],
            $fixture['obligation'],
            TaxType::Vat,
            '2026-Q1',
            'AED',
            '10000.00',
            '500.00',
            'Synthetic tax opened.',
        );

        $this->assertSame(TaxRecordStatus::Draft, $taxRecord->status);
        $this->assertSame('10000.00', $taxRecord->taxable_amount);
        $this->assertSame('500.00', $taxRecord->tax_amount);

        $amendment = $taxRecord->amendments()->sole();
        $this->assertNull($amendment->previous_status);
        $this->assertSame(TaxRecordStatus::Draft, $amendment->new_status);

        $audit = AuditLog::query()->where('action', 'tax_record.created')->sole();
        $this->assertSame(TaxType::Vat->value, $audit->after_values['tax_type']);
        $this->assertSame('500.00', $audit->after_values['tax_amount']);
    }

    public function test_one_obligation_has_at_most_one_tax_record(): void
    {
        $fixture = $this->fixture();
        $this->openTax($fixture);

        $this->expectException(ValidationException::class);
        app(CreateTaxRecord::class)->handle(
            $fixture['manager'],
            $fixture['obligation'],
            TaxType::CorporateTax,
            '2026',
            'AED',
            '1.00',
            '0.00',
            'Synthetic duplicate.',
        );
    }

    public function test_amendment_updates_retained_figures_with_before_and_after_history(): void
    {
        $fixture = $this->fixture();
        $taxRecord = $this->openTax($fixture);

        app(AmendTaxRecord::class)->handle(
            $fixture['manager'],
            $taxRecord,
            '12000.00',
            '600.00',
            TaxRecordStatus::Draft,
            'Synthetic figure correction.',
        );

        $refreshed = $taxRecord->refresh();
        $this->assertSame('12000.00', $refreshed->taxable_amount);
        $this->assertSame('600.00', $refreshed->tax_amount);
        $this->assertSame(2, $taxRecord->amendments()->count());

        $latest = $taxRecord->amendments()->orderByDesc('amended_at')->first();
        $this->assertSame('10000.00', $latest->previous_taxable_amount);
        $this->assertSame('12000.00', $latest->new_taxable_amount);
    }

    public function test_finalising_locks_the_record_against_further_amendment(): void
    {
        $fixture = $this->fixture();
        $taxRecord = $this->openTax($fixture);

        app(AmendTaxRecord::class)->handle(
            $fixture['manager'],
            $taxRecord,
            '10000.00',
            '500.00',
            TaxRecordStatus::Final,
            'Synthetic finalisation.',
        );

        $this->assertSame(TaxRecordStatus::Final, $taxRecord->refresh()->status);

        try {
            app(AmendTaxRecord::class)->handle(
                $fixture['manager'],
                $taxRecord,
                '11000.00',
                '550.00',
                TaxRecordStatus::Final,
                'Synthetic post-final amendment.',
            );
            $this->fail('A final tax record must not be amendable.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('targetStatus', $exception->errors());
        }
    }

    public function test_negative_and_malformed_amounts_are_rejected(): void
    {
        $fixture = $this->fixture();

        try {
            app(CreateTaxRecord::class)->handle(
                $fixture['manager'],
                $fixture['obligation'],
                TaxType::Vat,
                '2026-Q1',
                'AED',
                '-5.00',
                '1.00',
                'Synthetic negative amount.',
            );
            $this->fail('A negative amount must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('taxableAmount', $exception->errors());
        }

        $this->assertDatabaseCount('tax_records', 0);
    }

    public function test_tax_state_does_not_change_work_or_filing_state(): void
    {
        $fixture = $this->fixture();
        $filingRecord = app(CreateFilingRecord::class)->handle(
            $fixture['manager'],
            $fixture['obligation'],
            FilingStatus::NotFiled,
            'Synthetic filing opened.',
        );
        $taxRecord = $this->openTax($fixture);

        app(AmendTaxRecord::class)->handle(
            $fixture['manager'],
            $taxRecord,
            '9000.00',
            '450.00',
            TaxRecordStatus::Final,
            'Synthetic amendment.',
        );

        $this->assertSame(WorkItemStatus::NotStarted, $fixture['workItem']->refresh()->status);
        $this->assertSame(FilingStatus::NotFiled, $filingRecord->refresh()->status);
    }

    public function test_member_without_the_tax_permission_cannot_open_a_tax_record(): void
    {
        $fixture = $this->fixture();
        $preparer = User::factory()->create();
        $preparerMembership = $this->createFirmMembership($fixture['firm'], $preparer, FirmRole::Preparer);
        $this->activateFirmMembership($preparerMembership);

        $this->expectException(AuthorizationException::class);
        app(CreateTaxRecord::class)->handle(
            $preparer,
            $fixture['obligation'],
            TaxType::Vat,
            '2026-Q1',
            'AED',
            '1.00',
            '0.00',
            'Synthetic unauthorised tax.',
        );
    }

    public function test_a_manager_from_another_firm_cannot_amend_this_firms_tax_record(): void
    {
        $fixture = $this->fixture();
        $taxRecord = $this->openTax($fixture);

        $otherFirm = Firm::factory()->create();
        $otherManager = User::factory()->create();
        $otherMembership = $this->createFirmMembership($otherFirm, $otherManager, FirmRole::Manager);
        $this->activateFirmMembership($otherMembership);

        $this->expectException(AuthorizationException::class);
        app(AmendTaxRecord::class)->handle(
            $otherManager,
            $taxRecord,
            '1.00',
            '0.00',
            TaxRecordStatus::Draft,
            'Synthetic cross-firm attempt.',
        );
    }

    public function test_manager_cannot_open_a_tax_record_for_another_firms_obligation(): void
    {
        $fixture = $this->fixture();
        $otherFirm = Firm::factory()->create();
        $otherClient = Client::factory()->createForFirm($otherFirm, ['created_by' => $fixture['manager']->id]);
        $otherObligation = Obligation::factory()->createForFirm($otherFirm, $otherClient, [
            'created_by' => $fixture['manager']->id,
            'verified_by' => $fixture['manager']->id,
        ]);
        $this->activateFirmMembership($fixture['managerMembership']);

        $this->expectException(AuthorizationException::class);
        app(CreateTaxRecord::class)->handle(
            $fixture['manager'],
            $otherObligation,
            TaxType::Vat,
            '2026-Q1',
            'AED',
            '10000.00',
            '500.00',
            'Synthetic cross-firm create attempt.',
        );
    }

    public function test_amendment_history_rejects_model_and_raw_query_builder_mutations(): void
    {
        $fixture = $this->fixture();
        $taxRecord = $this->openTax($fixture);
        $amendment = $taxRecord->amendments()->sole();

        try {
            $amendment->update(['reason' => 'Attempted overwrite']);
            $this->fail('Tax amendment updates must fail.');
        } catch (LogicException $exception) {
            $this->assertSame('Tax record amendment history is append-only.', $exception->getMessage());
        }

        try {
            DB::table('tax_record_amendments')->where('id', $amendment->id)->update(['reason' => 'Bulk overwrite']);
            $this->fail('A database trigger must reject a raw tax amendment update.');
        } catch (QueryException) {
            // Expected.
        }

        try {
            DB::table('tax_record_amendments')->where('id', $amendment->id)->delete();
            $this->fail('A database trigger must reject a raw tax amendment delete.');
        } catch (QueryException) {
            // Expected.
        }

        $this->assertSame('Synthetic tax opened.', $amendment->refresh()->reason);
    }

    public function test_manager_opens_and_amends_tax_through_the_livewire_interface(): void
    {
        $fixture = $this->fixture();

        Livewire::actingAs($fixture['manager'])
            ->test(Index::class)
            ->call('openTax', $fixture['obligation']->id)
            ->assertSet('showTaxModal', true)
            ->set('taxType', TaxType::Vat->value)
            ->set('taxPeriodLabel', '2026-Q1')
            ->set('taxCurrency', 'AED')
            ->set('taxTaxableAmount', '20000.00')
            ->set('taxTaxAmount', '1000.00')
            ->set('taxReason', 'Synthetic Livewire tax opened.')
            ->call('saveTax')
            ->assertHasNoErrors()
            ->assertSet('showTaxModal', false)
            ->assertSee('Tax: VAT Draft');

        $taxRecord = TaxRecord::query()->sole();
        $this->assertSame('20000.00', $taxRecord->taxable_amount);
    }

    public function test_preparer_does_not_see_a_tax_control(): void
    {
        $fixture = $this->fixture();
        $this->activateFirmMembership($fixture['preparerMembership']);

        Livewire::actingAs($fixture['preparer'])
            ->test(Index::class)
            ->assertDontSeeHtml("wire:click=\"openTax('{$fixture['obligation']->id}')\"");
    }

    /**
     * @param  array{manager: User, obligation: Obligation, managerMembership: FirmMembership}  $fixture
     */
    private function openTax(array $fixture): TaxRecord
    {
        $this->activateFirmMembership($fixture['managerMembership']);

        return app(CreateTaxRecord::class)->handle(
            $fixture['manager'],
            $fixture['obligation'],
            TaxType::Vat,
            '2026-Q1',
            'AED',
            '10000.00',
            '500.00',
            'Synthetic tax opened.',
        );
    }

    /**
     * @return array{
     *   firm: Firm,
     *   manager: User,
     *   managerMembership: FirmMembership,
     *   preparer: User,
     *   preparerMembership: FirmMembership,
     *   obligation: Obligation,
     *   workItem: WorkItem
     * }
     */
    private function fixture(): array
    {
        config([
            'platform.features.compliance_operations.enabled' => true,
            'platform.features.compliance_operations.firm_ids' => [],
        ]);
        $firm = Firm::factory()->create();
        $manager = User::factory()->create(['name' => 'Synthetic Manager']);
        $preparer = User::factory()->create(['name' => 'Synthetic Preparer']);
        $managerMembership = $this->createFirmMembership($firm, $manager, FirmRole::Manager);
        $preparerMembership = $this->createFirmMembership($firm, $preparer, FirmRole::Preparer);
        $this->activateFirmMembership($managerMembership);
        $client = Client::factory()->createForFirm($firm, ['created_by' => $manager->id]);
        $obligation = Obligation::factory()->createForFirm($firm, $client, [
            'created_by' => $manager->id,
            'verified_by' => $manager->id,
        ]);
        $workItem = WorkItem::factory()->createForFirm($firm, $obligation, [
            'created_by' => $manager->id,
        ]);
        $this->activateFirmMembership($managerMembership);

        return compact(
            'firm',
            'manager',
            'managerMembership',
            'preparer',
            'preparerMembership',
            'obligation',
            'workItem',
        );
    }
}
