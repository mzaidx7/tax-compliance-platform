<?php

declare(strict_types=1);

namespace Tests\Feature\Payments;

use App\Actions\Filings\CreateFilingRecord;
use App\Actions\Payments\CreatePaymentRecord;
use App\Actions\Payments\TransitionPaymentRecord;
use App\Enums\FilingStatus;
use App\Enums\FirmRole;
use App\Enums\PaymentStatus;
use App\Enums\WorkItemStatus;
use App\Livewire\Obligations\Index;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Firm;
use App\Models\FirmMembership;
use App\Models\Obligation;
use App\Models\PaymentRecord;
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

final class PaymentRecordTest extends TestCase
{
    use BuildsFirmTenancy;
    use RefreshDatabase;

    public function test_manager_opens_a_payment_record_with_opening_history_and_audit(): void
    {
        $fixture = $this->fixture();

        $paymentRecord = app(CreatePaymentRecord::class)->handle(
            $fixture['manager'],
            $fixture['obligation'],
            PaymentStatus::Pending,
            'Synthetic payment opened.',
        );

        $this->assertSame(PaymentStatus::Pending, $paymentRecord->status);
        $this->assertNull($paymentRecord->payment_reference);
        $this->assertNull($paymentRecord->paid_on);

        $transition = $paymentRecord->transitions()->sole();
        $this->assertNull($transition->from_status);
        $this->assertSame(PaymentStatus::Pending, $transition->to_status);

        $audit = AuditLog::query()->where('action', 'payment_record.created')->sole();
        $this->assertSame(PaymentStatus::Pending->value, $audit->after_values['status']);
    }

    public function test_payment_cannot_be_opened_directly_as_paid(): void
    {
        $fixture = $this->fixture();

        try {
            app(CreatePaymentRecord::class)->handle(
                $fixture['manager'],
                $fixture['obligation'],
                PaymentStatus::Paid,
                'Synthetic invalid opening state.',
            );
            $this->fail('A payment must not open as paid.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('status', $exception->errors());
        }

        $this->assertDatabaseCount('payment_records', 0);
    }

    public function test_one_obligation_has_at_most_one_payment_record(): void
    {
        $fixture = $this->fixture();
        app(CreatePaymentRecord::class)->handle(
            $fixture['manager'],
            $fixture['obligation'],
            PaymentStatus::Pending,
            'Synthetic first payment.',
        );

        $this->expectException(ValidationException::class);
        app(CreatePaymentRecord::class)->handle(
            $fixture['manager'],
            $fixture['obligation'],
            PaymentStatus::Pending,
            'Synthetic duplicate payment.',
        );
    }

    public function test_payment_advances_through_overdue_and_paid_with_evidence(): void
    {
        $fixture = $this->fixture();
        $paymentRecord = $this->openPayment($fixture);

        app(TransitionPaymentRecord::class)->handle(
            $fixture['manager'],
            $paymentRecord,
            PaymentStatus::Overdue,
            'Synthetic overdue observation.',
        );

        $this->assertSame(PaymentStatus::Overdue, $paymentRecord->refresh()->status);

        app(TransitionPaymentRecord::class)->handle(
            $fixture['manager'],
            $paymentRecord,
            PaymentStatus::Paid,
            'Synthetic settlement observed.',
            'SYNTH-PAY-001',
            now('Asia/Dubai')->toDateString(),
        );

        $paid = $paymentRecord->refresh();
        $this->assertSame(PaymentStatus::Paid, $paid->status);
        $this->assertSame('SYNTH-PAY-001', $paid->payment_reference);
        $this->assertNotNull($paid->paid_on);
        $this->assertSame(3, $paymentRecord->transitions()->count());
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'payment_record.status_transitioned',
            'auditable_id' => $paymentRecord->id,
        ]);
    }

    public function test_paid_requires_a_reference_and_a_settlement_date(): void
    {
        $fixture = $this->fixture();
        $paymentRecord = $this->openPayment($fixture);

        try {
            app(TransitionPaymentRecord::class)->handle(
                $fixture['manager'],
                $paymentRecord,
                PaymentStatus::Paid,
                'Synthetic settlement without evidence.',
            );
            $this->fail('Paid must require a payment reference.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('paymentReference', $exception->errors());
        }

        try {
            app(TransitionPaymentRecord::class)->handle(
                $fixture['manager'],
                $paymentRecord,
                PaymentStatus::Paid,
                'Synthetic settlement without a date.',
                'SYNTH-PAY-002',
            );
            $this->fail('Paid must require the settlement date.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('paidOn', $exception->errors());
        }

        $this->assertSame(PaymentStatus::Pending, $paymentRecord->refresh()->status);
    }

    public function test_paid_is_terminal_and_invalid_skips_are_rejected(): void
    {
        $fixture = $this->fixture();
        $paymentRecord = $this->openPayment($fixture);
        app(TransitionPaymentRecord::class)->handle(
            $fixture['manager'],
            $paymentRecord,
            PaymentStatus::Paid,
            'Synthetic settlement observed.',
            'SYNTH-PAY-003',
            now('Asia/Dubai')->toDateString(),
        );

        $this->assertSame([], $paymentRecord->refresh()->allowedTransitions());

        try {
            app(TransitionPaymentRecord::class)->handle(
                $fixture['manager'],
                $paymentRecord,
                PaymentStatus::Pending,
                'Synthetic reopen attempt.',
            );
            $this->fail('Paid must be terminal.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('targetStatus', $exception->errors());
        }
    }

    public function test_blank_payment_reason_is_rejected_without_writing_history(): void
    {
        $fixture = $this->fixture();
        $paymentRecord = $this->openPayment($fixture);

        try {
            app(TransitionPaymentRecord::class)->handle(
                $fixture['manager'],
                $paymentRecord,
                PaymentStatus::Overdue,
                ' ',
            );
            $this->fail('A blank payment reason must fail.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('reason', $exception->errors());
        }

        $this->assertSame(1, $paymentRecord->transitions()->count());
    }

    public function test_payment_state_does_not_change_work_or_filing_state(): void
    {
        $fixture = $this->fixture();
        $filingRecord = app(CreateFilingRecord::class)->handle(
            $fixture['manager'],
            $fixture['obligation'],
            FilingStatus::NotFiled,
            'Synthetic filing opened.',
        );
        $paymentRecord = $this->openPayment($fixture);

        app(TransitionPaymentRecord::class)->handle(
            $fixture['manager'],
            $paymentRecord,
            PaymentStatus::Paid,
            'Synthetic settlement observed.',
            'SYNTH-PAY-004',
            now('Asia/Dubai')->toDateString(),
        );

        $this->assertSame(WorkItemStatus::NotStarted, $fixture['workItem']->refresh()->status);
        $this->assertSame(FilingStatus::NotFiled, $filingRecord->refresh()->status);
    }

    public function test_member_without_the_payment_permission_cannot_open_a_payment(): void
    {
        $fixture = $this->fixture();
        $this->activateFirmMembership($fixture['preparerMembership']);

        $this->expectException(AuthorizationException::class);
        app(CreatePaymentRecord::class)->handle(
            $fixture['preparer'],
            $fixture['obligation'],
            PaymentStatus::Pending,
            'Synthetic unauthorised payment.',
        );
    }

    public function test_a_manager_from_another_firm_cannot_advance_this_firms_payment(): void
    {
        $fixture = $this->fixture();
        $paymentRecord = $this->openPayment($fixture);

        $otherFirm = Firm::factory()->create();
        $otherManager = User::factory()->create();
        $otherMembership = $this->createFirmMembership($otherFirm, $otherManager, FirmRole::Manager);
        $this->activateFirmMembership($otherMembership);

        $this->expectException(AuthorizationException::class);
        app(TransitionPaymentRecord::class)->handle(
            $otherManager,
            $paymentRecord,
            PaymentStatus::Overdue,
            'Synthetic cross-firm attempt.',
        );
    }

    public function test_manager_cannot_open_a_payment_for_another_firms_obligation(): void
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
        app(CreatePaymentRecord::class)->handle(
            $fixture['manager'],
            $otherObligation,
            PaymentStatus::Pending,
            'Synthetic cross-firm create attempt.',
        );
    }

    public function test_payment_history_rejects_model_and_raw_query_builder_mutations(): void
    {
        $fixture = $this->fixture();
        $paymentRecord = $this->openPayment($fixture);
        $transition = $paymentRecord->transitions()->sole();

        try {
            $transition->update(['reason' => 'Attempted overwrite']);
            $this->fail('Payment history updates must fail.');
        } catch (LogicException $exception) {
            $this->assertSame('Payment record transition history is append-only.', $exception->getMessage());
        }

        try {
            DB::table('payment_record_transitions')
                ->where('id', $transition->id)
                ->update(['reason' => 'Attempted bulk overwrite']);
            $this->fail('A database trigger must reject a raw payment history update.');
        } catch (QueryException) {
            // Expected: the trigger rejects the mutation independently of Eloquent.
        }

        try {
            DB::table('payment_record_transitions')->where('id', $transition->id)->delete();
            $this->fail('A database trigger must reject a raw payment history delete.');
        } catch (QueryException) {
            // Expected.
        }

        $this->assertSame('Synthetic payment opened.', $transition->refresh()->reason);
    }

    public function test_manager_opens_and_advances_a_payment_through_the_livewire_interface(): void
    {
        $fixture = $this->fixture();

        Livewire::actingAs($fixture['manager'])
            ->test(Index::class)
            ->call('openPayment', $fixture['obligation']->id)
            ->assertSet('showPaymentModal', true)
            ->set('paymentStatus', PaymentStatus::Pending->value)
            ->set('paymentReason', 'Synthetic Livewire payment opened.')
            ->call('savePayment')
            ->assertHasNoErrors()
            ->assertSet('showPaymentModal', false)
            ->assertSee('Payment: Pending');

        $paymentRecord = PaymentRecord::query()->sole();

        Livewire::actingAs($fixture['manager'])
            ->test(Index::class)
            ->call('openPayment', $fixture['obligation']->id)
            ->set('paymentStatus', PaymentStatus::Paid->value)
            ->set('paymentReference', 'SYNTH-PAY-LW-1')
            ->set('paymentPaidOn', now('Asia/Dubai')->toDateString())
            ->set('paymentReason', 'Synthetic Livewire settlement.')
            ->call('savePayment')
            ->assertHasNoErrors()
            ->assertSee('Payment: Paid');

        $this->assertSame(PaymentStatus::Paid, $paymentRecord->refresh()->status);
        $this->assertSame('SYNTH-PAY-LW-1', $paymentRecord->payment_reference);
    }

    public function test_preparer_does_not_see_a_payment_control(): void
    {
        $fixture = $this->fixture();
        $this->activateFirmMembership($fixture['preparerMembership']);

        Livewire::actingAs($fixture['preparer'])
            ->test(Index::class)
            ->assertDontSeeHtml("wire:click=\"openPayment('{$fixture['obligation']->id}')\"");
    }

    /**
     * @param  array{manager: User, obligation: Obligation, managerMembership: FirmMembership}  $fixture
     */
    private function openPayment(array $fixture): PaymentRecord
    {
        $this->activateFirmMembership($fixture['managerMembership']);

        return app(CreatePaymentRecord::class)->handle(
            $fixture['manager'],
            $fixture['obligation'],
            PaymentStatus::Pending,
            'Synthetic payment opened.',
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
