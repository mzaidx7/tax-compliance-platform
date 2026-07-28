<?php

namespace Tests\Feature;

use App\Actions\Tenancy\CreateFirmMembership;
use App\Enums\AssignmentRole;
use App\Enums\FirmRole;
use App\Enums\PaymentStatus;
use App\Enums\RiskLevel;
use App\Enums\WorkItemStatus;
use App\Livewire\Dashboard\Index;
use App\Models\AssignmentHistory;
use App\Models\Client;
use App\Models\Firm;
use App\Models\FirmMembership;
use App\Models\Obligation;
use App\Models\PaymentRecord;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Models\WorkItem;
use App\Tenancy\FirmContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\BuildsFirmTenancy;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use BuildsFirmTenancy;
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_the_dashboard(): void
    {
        $firm = Firm::factory()->create();
        $user = User::factory()->create();
        app(CreateFirmMembership::class)->handle($firm, $user, FirmRole::FirmAdministrator);

        $this->actingAs($user);

        $response = $this->get(route('dashboard'));
        $response->assertOk();
    }

    public function test_authenticated_users_without_a_firm_cannot_visit_the_dashboard(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertForbidden();
    }

    public function test_manager_sees_separate_measures_derived_from_recorded_state(): void
    {
        $fixture = $this->operationalFixture();
        $this->activateFirmMembership($fixture['managerMembership']);

        Livewire::actingAs($fixture['manager'])
            ->test(Index::class)
            ->assertSet('summary.due_soon', 1)
            ->assertSet('summary.overdue', 1)
            ->assertSet('summary.high_risk', 1)
            ->assertSet('summary.overdue_payments', 1)
            ->assertSee('Synthetic overdue obligation')
            ->assertSee('Synthetic due-soon obligation')
            ->assertDontSee('Synthetic later obligation');
    }

    public function test_dashboard_excludes_other_firms_operational_records(): void
    {
        $fixture = $this->operationalFixture();
        $otherFirm = Firm::factory()->create();
        $otherManager = User::factory()->create();
        $otherMembership = $this->createFirmMembership($otherFirm, $otherManager, FirmRole::Manager);
        $this->activateFirmMembership($otherMembership);

        Livewire::actingAs($otherManager)
            ->test(Index::class)
            ->assertSet('summary.due_soon', 0)
            ->assertSet('summary.overdue', 0)
            ->assertSet('summary.high_risk', 0)
            ->assertSet('summary.overdue_payments', 0)
            ->assertDontSee('Synthetic overdue obligation');
    }

    public function test_preparer_sees_only_operations_assigned_to_them(): void
    {
        $fixture = $this->operationalFixture();
        $preparer = User::factory()->create(['name' => 'Synthetic Assigned Preparer']);
        $membership = $this->createFirmMembership($fixture['firm'], $preparer, FirmRole::Preparer);
        AssignmentHistory::factory()->createForWorkItem(
            $fixture['firm'],
            $fixture['highRiskWork'],
            $membership,
            [
                'assignment_role' => AssignmentRole::Preparer,
                'assigned_by' => $fixture['manager']->id,
            ],
        );
        $this->activateFirmMembership($membership);

        Livewire::actingAs($preparer)
            ->test(Index::class)
            ->assertSet('summary.due_soon', 0)
            ->assertSet('summary.overdue', 1)
            ->assertSet('summary.high_risk', 1)
            ->assertSet('summary.overdue_payments', 1)
            ->assertSee('Synthetic overdue obligation')
            ->assertDontSee('Synthetic due-soon obligation');
    }

    public function test_member_without_operational_permission_sees_safe_empty_state(): void
    {
        $fixture = $this->operationalFixture();
        $readOnly = User::factory()->create();
        $membership = $this->createFirmMembership($fixture['firm'], $readOnly, FirmRole::ReadOnly);
        $this->activateFirmMembership($membership);

        Livewire::actingAs($readOnly)
            ->test(Index::class)
            ->assertSet('summary.due_soon', 0)
            ->assertSet('summary.overdue', 0)
            ->assertSet('summary.high_risk', 0)
            ->assertSet('summary.overdue_payments', 0)
            ->assertSee('No deadlines need attention')
            ->assertDontSee('Synthetic overdue obligation');
    }

    /**
     * @return array{
     *   firm: Firm,
     *   manager: User,
     *   managerMembership: FirmMembership,
     *   highRiskWork: WorkItem
     * }
     */
    private function operationalFixture(): array
    {
        $firm = Firm::factory()->create();
        $manager = User::factory()->create(['name' => 'Synthetic Manager']);
        $managerMembership = $this->createFirmMembership($firm, $manager, FirmRole::Manager);
        $this->activateFirmMembership($managerMembership);
        $client = Client::factory()->createForFirm($firm, [
            'internal_code' => 'SYN-DASH',
            'created_by' => $manager->id,
        ]);
        $workflow = app(FirmContext::class)->runForFirm(
            $firm,
            fn (): WorkflowDefinition => WorkflowDefinition::factory()->create([
                'published_by' => $manager->id,
            ]),
        );
        $overdue = $this->obligation(
            $firm,
            $client,
            $manager,
            'Synthetic overdue obligation',
            today()->subDays(4)->toDateString(),
        );
        $dueSoon = $this->obligation(
            $firm,
            $client,
            $manager,
            'Synthetic due-soon obligation',
            today()->addDays(10)->toDateString(),
        );
        $this->obligation(
            $firm,
            $client,
            $manager,
            'Synthetic later obligation',
            today()->addDays(60)->toDateString(),
        );
        $highRiskWork = WorkItem::factory()->createForFirm($firm, $overdue, [
            'workflow_definition_id' => $workflow->id,
            'risk_status' => RiskLevel::High,
            'status' => WorkItemStatus::InPreparation,
            'created_by' => $manager->id,
        ]);
        WorkItem::factory()->createForFirm($firm, $dueSoon, [
            'workflow_definition_id' => $workflow->id,
            'risk_status' => RiskLevel::High,
            'status' => WorkItemStatus::Completed,
            'created_by' => $manager->id,
        ]);
        PaymentRecord::factory()->createForFirm($firm, $overdue, [
            'status' => PaymentStatus::Overdue,
            'created_by' => $manager->id,
        ]);

        return compact('firm', 'manager', 'managerMembership', 'highRiskWork');
    }

    private function obligation(
        Firm $firm,
        Client $client,
        User $manager,
        string $type,
        string $dueDate,
    ): Obligation {
        return Obligation::factory()->createForFirm($firm, $client, [
            'obligation_type' => $type,
            'statutory_due_date' => $dueDate,
            'created_by' => $manager->id,
            'verified_by' => $manager->id,
        ]);
    }
}
