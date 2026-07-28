<?php

declare(strict_types=1);

namespace Tests\Feature\Filings;

use App\Actions\Filings\CreateFilingRecord;
use App\Actions\Filings\TransitionFilingRecord;
use App\Enums\FilingStatus;
use App\Enums\FirmRole;
use App\Enums\WorkItemStatus;
use App\Livewire\Obligations\Index;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\FilingRecord;
use App\Models\Firm;
use App\Models\FirmMembership;
use App\Models\Obligation;
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

final class FilingRecordTest extends TestCase
{
    use BuildsFirmTenancy;
    use RefreshDatabase;

    public function test_manager_opens_a_filing_record_with_opening_history_and_audit(): void
    {
        $fixture = $this->fixture();

        $filingRecord = app(CreateFilingRecord::class)->handle(
            $fixture['manager'],
            $fixture['obligation'],
            FilingStatus::NotFiled,
            'Synthetic filing opened.',
        );

        $this->assertSame(FilingStatus::NotFiled, $filingRecord->status);
        $this->assertNull($filingRecord->filing_reference);
        $this->assertNull($filingRecord->filed_on);

        $transition = $filingRecord->transitions()->sole();
        $this->assertNull($transition->from_status);
        $this->assertSame(FilingStatus::NotFiled, $transition->to_status);

        $audit = AuditLog::query()->where('action', 'filing_record.created')->sole();
        $this->assertSame(FilingStatus::NotFiled->value, $audit->after_values['status']);
    }

    public function test_filing_cannot_be_opened_directly_in_an_authority_outcome_state(): void
    {
        $fixture = $this->fixture();

        try {
            app(CreateFilingRecord::class)->handle(
                $fixture['manager'],
                $fixture['obligation'],
                FilingStatus::Acknowledged,
                'Synthetic invalid opening state.',
            );
            $this->fail('A filing must not open as acknowledged.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('status', $exception->errors());
        }

        $this->assertDatabaseCount('filing_records', 0);
    }

    public function test_one_obligation_has_at_most_one_filing_record(): void
    {
        $fixture = $this->fixture();
        app(CreateFilingRecord::class)->handle(
            $fixture['manager'],
            $fixture['obligation'],
            FilingStatus::NotFiled,
            'Synthetic first filing.',
        );

        $this->expectException(ValidationException::class);
        app(CreateFilingRecord::class)->handle(
            $fixture['manager'],
            $fixture['obligation'],
            FilingStatus::NotFiled,
            'Synthetic duplicate filing.',
        );
    }

    public function test_filing_advances_through_filed_and_acknowledged_with_evidence(): void
    {
        $fixture = $this->fixture();
        $filingRecord = $this->openFiling($fixture);

        app(TransitionFilingRecord::class)->handle(
            $fixture['manager'],
            $filingRecord,
            FilingStatus::Filed,
            'Synthetic submission recorded.',
            'SYNTH-REF-001',
            now('Asia/Dubai')->toDateString(),
        );

        $filed = $filingRecord->refresh();
        $this->assertSame(FilingStatus::Filed, $filed->status);
        $this->assertSame('SYNTH-REF-001', $filed->filing_reference);
        $this->assertNotNull($filed->filed_on);

        app(TransitionFilingRecord::class)->handle(
            $fixture['manager'],
            $filingRecord,
            FilingStatus::Acknowledged,
            'Synthetic acknowledgement recorded.',
        );

        $this->assertSame(FilingStatus::Acknowledged, $filingRecord->refresh()->status);
        $this->assertSame(3, $filingRecord->transitions()->count());
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'filing_record.status_transitioned',
            'auditable_id' => $filingRecord->id,
        ]);
    }

    public function test_filed_requires_a_reference_and_a_filed_date(): void
    {
        $fixture = $this->fixture();
        $filingRecord = $this->openFiling($fixture);

        try {
            app(TransitionFilingRecord::class)->handle(
                $fixture['manager'],
                $filingRecord,
                FilingStatus::Filed,
                'Synthetic submission without evidence.',
            );
            $this->fail('Filed must require an authority reference.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('filingReference', $exception->errors());
        }

        try {
            app(TransitionFilingRecord::class)->handle(
                $fixture['manager'],
                $filingRecord,
                FilingStatus::Filed,
                'Synthetic submission without a date.',
                'SYNTH-REF-002',
            );
            $this->fail('Filed must require the filed date.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('filedOn', $exception->errors());
        }

        $this->assertSame(FilingStatus::NotFiled, $filingRecord->refresh()->status);
    }

    public function test_invalid_filing_state_skip_and_blank_reason_are_rejected_without_history(): void
    {
        $fixture = $this->fixture();
        $filingRecord = $this->openFiling($fixture);

        try {
            app(TransitionFilingRecord::class)->handle(
                $fixture['manager'],
                $filingRecord,
                FilingStatus::Acknowledged,
                'Synthetic invalid skip.',
                'SYNTH-REF-003',
            );
            $this->fail('Filing cannot skip from not filed to acknowledged.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('targetStatus', $exception->errors());
        }

        try {
            app(TransitionFilingRecord::class)->handle(
                $fixture['manager'],
                $filingRecord,
                FilingStatus::NotRequired,
                ' ',
            );
            $this->fail('A blank filing reason must fail.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('reason', $exception->errors());
        }

        $this->assertSame(1, $filingRecord->transitions()->count());
    }

    public function test_filing_state_does_not_change_work_state(): void
    {
        $fixture = $this->fixture();
        $filingRecord = $this->openFiling($fixture);

        app(TransitionFilingRecord::class)->handle(
            $fixture['manager'],
            $filingRecord,
            FilingStatus::Filed,
            'Synthetic submission recorded.',
            'SYNTH-REF-004',
            now('Asia/Dubai')->toDateString(),
        );

        $this->assertSame(WorkItemStatus::NotStarted, $fixture['workItem']->refresh()->status);
    }

    public function test_member_without_the_filing_permission_cannot_open_a_filing(): void
    {
        $fixture = $this->fixture();
        $this->activateFirmMembership($fixture['preparerMembership']);

        $this->expectException(AuthorizationException::class);
        app(CreateFilingRecord::class)->handle(
            $fixture['preparer'],
            $fixture['obligation'],
            FilingStatus::NotFiled,
            'Synthetic unauthorised filing.',
        );
    }

    public function test_a_manager_from_another_firm_cannot_advance_this_firms_filing(): void
    {
        $fixture = $this->fixture();
        $filingRecord = $this->openFiling($fixture);

        $otherFirm = Firm::factory()->create();
        $otherManager = User::factory()->create();
        $otherMembership = $this->createFirmMembership($otherFirm, $otherManager, FirmRole::Manager);
        $this->activateFirmMembership($otherMembership);

        $this->expectException(AuthorizationException::class);
        app(TransitionFilingRecord::class)->handle(
            $otherManager,
            $filingRecord,
            FilingStatus::NotRequired,
            'Synthetic cross-firm attempt.',
        );
    }

    public function test_manager_cannot_open_a_filing_for_another_firms_obligation(): void
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
        app(CreateFilingRecord::class)->handle(
            $fixture['manager'],
            $otherObligation,
            FilingStatus::NotFiled,
            'Synthetic cross-firm create attempt.',
        );
    }

    public function test_filing_history_rejects_model_and_raw_query_builder_mutations(): void
    {
        $fixture = $this->fixture();
        $filingRecord = $this->openFiling($fixture);
        $transition = $filingRecord->transitions()->sole();

        try {
            $transition->update(['reason' => 'Attempted overwrite']);
            $this->fail('Filing history updates must fail.');
        } catch (LogicException $exception) {
            $this->assertSame('Filing record transition history is append-only.', $exception->getMessage());
        }

        try {
            DB::table('filing_record_transitions')
                ->where('id', $transition->id)
                ->update(['reason' => 'Attempted bulk overwrite']);
            $this->fail('A database trigger must reject a raw filing history update.');
        } catch (QueryException) {
            // Expected: the trigger rejects the mutation independently of Eloquent.
        }

        try {
            DB::table('filing_record_transitions')->where('id', $transition->id)->delete();
            $this->fail('A database trigger must reject a raw filing history delete.');
        } catch (QueryException) {
            // Expected.
        }

        $this->assertSame('Synthetic filing opened.', $transition->refresh()->reason);
    }

    public function test_manager_opens_and_advances_a_filing_through_the_livewire_interface(): void
    {
        $fixture = $this->fixture();

        Livewire::actingAs($fixture['manager'])
            ->test(Index::class)
            ->call('openFiling', $fixture['obligation']->id)
            ->assertSet('showFilingModal', true)
            ->set('filingStatus', FilingStatus::NotFiled->value)
            ->set('filingReason', 'Synthetic Livewire filing opened.')
            ->call('saveFiling')
            ->assertHasNoErrors()
            ->assertSet('showFilingModal', false)
            ->assertSee('Filing: Not filed');

        $filingRecord = FilingRecord::query()->sole();

        Livewire::actingAs($fixture['manager'])
            ->test(Index::class)
            ->call('openFiling', $fixture['obligation']->id)
            ->set('filingStatus', FilingStatus::Filed->value)
            ->set('filingReference', 'SYNTH-REF-LW-1')
            ->set('filingFiledOn', now('Asia/Dubai')->toDateString())
            ->set('filingReason', 'Synthetic Livewire submission.')
            ->call('saveFiling')
            ->assertHasNoErrors()
            ->assertSee('Filing: Filed');

        $this->assertSame(FilingStatus::Filed, $filingRecord->refresh()->status);
        $this->assertSame('SYNTH-REF-LW-1', $filingRecord->filing_reference);
    }

    public function test_preparer_does_not_see_a_filing_control(): void
    {
        $fixture = $this->fixture();
        $this->activateFirmMembership($fixture['preparerMembership']);

        Livewire::actingAs($fixture['preparer'])
            ->test(Index::class)
            ->assertDontSeeHtml("wire:click=\"openFiling('{$fixture['obligation']->id}')\"");
    }

    /**
     * @param  array{manager: User, obligation: Obligation, managerMembership: FirmMembership}  $fixture
     */
    private function openFiling(array $fixture): FilingRecord
    {
        $this->activateFirmMembership($fixture['managerMembership']);

        return app(CreateFilingRecord::class)->handle(
            $fixture['manager'],
            $fixture['obligation'],
            FilingStatus::NotFiled,
            'Synthetic filing opened.',
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
