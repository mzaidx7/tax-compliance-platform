<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Actions\Payments\CreatePaymentRecord;
use App\Actions\Payments\TransitionPaymentRecord;
use App\Actions\Workflows\CreateAssignedWorkItem;
use App\Actions\Workflows\PublishChecklistVersion;
use App\Actions\Workflows\PublishCoreWorkflowVersion;
use App\Actions\Workflows\ReassignWorkItem;
use App\Actions\Workflows\SetWorkItemRiskStatus;
use App\Enums\AssignmentRole;
use App\Enums\FirmMembershipStatus;
use App\Enums\FirmRole;
use App\Enums\PaymentStatus;
use App\Enums\RiskLevel;
use App\Models\ChecklistTemplate;
use App\Models\Client;
use App\Models\Firm;
use App\Models\FirmMembership;
use App\Models\NotificationRequest;
use App\Models\Obligation;
use App\Models\PaymentRecord;
use App\Models\User;
use App\Models\WorkItem;
use App\Notifications\PaymentOverdueNotification;
use App\Notifications\WorkItemHighRiskNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\BuildsFirmTenancy;
use Tests\TestCase;

final class OperationalNotificationTest extends TestCase
{
    use BuildsFirmTenancy;
    use RefreshDatabase;

    public function test_recording_high_risk_notifies_the_responsible_manager(): void
    {
        Queue::fake();
        $fixture = $this->fixture();
        $this->activateFirmMembership($fixture['managerMembership']);

        app(SetWorkItemRiskStatus::class)->handle(
            $fixture['manager'],
            $fixture['workItem'],
            RiskLevel::High,
            'Synthetic risk escalation.',
        );

        $request = NotificationRequest::query()->sole();
        $this->assertSame('work_item_high_risk', $request->template_key);
        $this->assertSame(1, $request->template_version);
        $this->assertSame($fixture['manager']->id, $request->recipient_user_id);
    }

    public function test_lower_risk_levels_do_not_notify(): void
    {
        Queue::fake();
        $fixture = $this->fixture();
        $this->activateFirmMembership($fixture['managerMembership']);

        app(SetWorkItemRiskStatus::class)->handle(
            $fixture['manager'],
            $fixture['workItem'],
            RiskLevel::Medium,
            'Synthetic medium risk.',
        );

        $this->assertSame(0, NotificationRequest::query()->count());
    }

    public function test_recording_an_overdue_payment_notifies_the_responsible_manager(): void
    {
        Queue::fake();
        $fixture = $this->fixture();
        $paymentRecord = $this->openPayment($fixture);

        app(TransitionPaymentRecord::class)->handle(
            $fixture['manager'],
            $paymentRecord,
            PaymentStatus::Overdue,
            'Synthetic overdue observation.',
        );

        $request = NotificationRequest::query()->sole();
        $this->assertSame('payment_overdue', $request->template_key);
        $this->assertSame($fixture['manager']->id, $request->recipient_user_id);
    }

    public function test_other_payment_states_do_not_notify(): void
    {
        Queue::fake();
        $fixture = $this->fixture();
        $paymentRecord = $this->openPayment($fixture);

        app(TransitionPaymentRecord::class)->handle(
            $fixture['manager'],
            $paymentRecord,
            PaymentStatus::Paid,
            'Synthetic settlement observed.',
            'SYNTH-PAY-NOTIFY-1',
            now('Asia/Dubai')->toDateString(),
        );

        $this->assertSame(0, NotificationRequest::query()->count());
    }

    public function test_the_notification_addresses_the_current_manager_after_reassignment(): void
    {
        Queue::fake();
        $fixture = $this->fixture();
        $replacementManager = User::factory()->create(['name' => 'Synthetic Replacement Manager']);
        $replacementMembership = $this->createFirmMembership(
            $fixture['firm'],
            $replacementManager,
            FirmRole::Manager,
        );

        $this->activateFirmMembership($fixture['managerMembership']);
        app(ReassignWorkItem::class)->handle(
            $fixture['manager'],
            $fixture['workItem'],
            AssignmentRole::ResponsibleManager,
            $replacementMembership->id,
            'Synthetic manager handover.',
        );

        app(SetWorkItemRiskStatus::class)->handle(
            $fixture['manager'],
            $fixture['workItem'],
            RiskLevel::High,
            'Synthetic risk escalation after handover.',
        );

        $request = NotificationRequest::query()->sole();
        $this->assertSame($replacementManager->id, $request->recipient_user_id);
    }

    public function test_each_recorded_escalation_is_its_own_occurrence(): void
    {
        Queue::fake();
        $fixture = $this->fixture();
        $this->activateFirmMembership($fixture['managerMembership']);
        app(SetWorkItemRiskStatus::class)->handle(
            $fixture['manager'],
            $fixture['workItem'],
            RiskLevel::High,
            'Synthetic risk escalation.',
        );
        $firstKey = NotificationRequest::query()->sole()->deterministic_key;

        app(SetWorkItemRiskStatus::class)->handle(
            $fixture['manager'],
            $fixture['workItem'],
            RiskLevel::Medium,
            'Synthetic downgrade.',
        );
        app(SetWorkItemRiskStatus::class)->handle(
            $fixture['manager'],
            $fixture['workItem'],
            RiskLevel::High,
            'Synthetic second escalation.',
        );

        $keys = NotificationRequest::query()->pluck('deterministic_key');
        $this->assertCount(2, $keys);
        $this->assertCount(2, $keys->unique());
        $this->assertContains($firstKey, $keys->all());
    }

    public function test_work_without_an_active_manager_records_the_change_without_notifying(): void
    {
        Queue::fake();
        $fixture = $this->fixture();
        $fixture['managerMembership']->forceFill(['status' => FirmMembershipStatus::Suspended])->save();
        $this->activateFirmMembership($fixture['adminMembership']);

        app(SetWorkItemRiskStatus::class)->handle(
            $fixture['admin'],
            $fixture['workItem'],
            RiskLevel::High,
            'Synthetic escalation without an active manager.',
        );

        $this->assertSame(RiskLevel::High, $fixture['workItem']->refresh()->risk_status);
        $this->assertSame(0, NotificationRequest::query()->count());
    }

    public function test_notifications_carry_no_message_contents_or_payment_reference(): void
    {
        Queue::fake();
        $fixture = $this->fixture();
        $paymentRecord = $this->openPayment($fixture);
        app(TransitionPaymentRecord::class)->handle(
            $fixture['manager'],
            $paymentRecord,
            PaymentStatus::Overdue,
            'Synthetic overdue observation.',
            'SYNTH-SECRET-REF',
        );

        $request = NotificationRequest::query()->sole();
        $encoded = json_encode($request->getAttributes());

        $this->assertIsString($encoded);
        $this->assertStringNotContainsString('SYNTH-SECRET-REF', $encoded);
        $this->assertStringNotContainsString('Synthetic overdue observation.', $encoded);
    }

    public function test_the_templates_declare_stable_keys_versions_and_one_channel(): void
    {
        $fixture = $this->fixture();
        $riskNotification = new WorkItemHighRiskNotification(
            $fixture['firm']->id,
            (int) $fixture['manager']->getKey(),
            $fixture['workItem']->id,
        );
        $paymentNotification = new PaymentOverdueNotification(
            $fixture['firm']->id,
            (int) $fixture['manager']->getKey(),
            $fixture['obligation']->id,
        );

        $this->assertSame('work_item_high_risk', $riskNotification->templateKey());
        $this->assertSame(1, $riskNotification->templateVersion());
        $this->assertSame(['mail'], $riskNotification->via($fixture['manager']));
        $this->assertSame('payment_overdue', $paymentNotification->templateKey());
        $this->assertSame(1, $paymentNotification->templateVersion());
        $this->assertSame(['mail'], $paymentNotification->via($fixture['manager']));
    }

    /**
     * @param  array{manager: User, managerMembership: FirmMembership, obligation: Obligation}  $fixture
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
     *   firm: Firm, admin: User, adminMembership: FirmMembership,
     *   manager: User, managerMembership: FirmMembership,
     *   preparer: User, preparerMembership: FirmMembership,
     *   reviewer: User, reviewerMembership: FirmMembership,
     *   obligation: Obligation, workItem: WorkItem
     * }
     */
    private function fixture(): array
    {
        config([
            'platform.features.compliance_operations.enabled' => true,
            'platform.features.compliance_operations.firm_ids' => [],
        ]);
        $firm = Firm::factory()->create();
        $admin = User::factory()->create(['name' => 'Synthetic Administrator']);
        $manager = User::factory()->create(['name' => 'Synthetic Manager']);
        $preparer = User::factory()->create(['name' => 'Synthetic Preparer']);
        $reviewer = User::factory()->create(['name' => 'Synthetic Reviewer']);
        $adminMembership = $this->createFirmMembership($firm, $admin, FirmRole::FirmAdministrator);
        $managerMembership = $this->createFirmMembership($firm, $manager, FirmRole::Manager);
        $preparerMembership = $this->createFirmMembership($firm, $preparer, FirmRole::Preparer);
        $reviewerMembership = $this->createFirmMembership($firm, $reviewer, FirmRole::Reviewer);
        $this->activateFirmMembership($managerMembership);
        $client = Client::factory()->createForFirm($firm, ['created_by' => $manager->id]);
        $obligation = Obligation::factory()->createForFirm($firm, $client, [
            'created_by' => $manager->id,
            'verified_by' => $manager->id,
        ]);
        app(PublishCoreWorkflowVersion::class)->handle($manager, 'Synthetic core workflow');
        app(PublishChecklistVersion::class)->handle(
            $manager,
            ChecklistTemplate::CORE_KEY,
            'Synthetic core checklist',
            [['key' => 'prepare-records', 'label' => 'Prepare synthetic records']],
        );
        $workItem = app(CreateAssignedWorkItem::class)->handle(
            $manager,
            $obligation,
            $preparerMembership->id,
            $reviewerMembership->id,
            $managerMembership->id,
            'Synthetic initial ownership.',
        );

        return compact(
            'firm',
            'admin',
            'adminMembership',
            'manager',
            'managerMembership',
            'preparer',
            'preparerMembership',
            'reviewer',
            'reviewerMembership',
            'obligation',
            'workItem',
        );
    }
}
