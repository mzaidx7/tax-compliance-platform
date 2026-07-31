<?php

declare(strict_types=1);

namespace Tests\Feature\Workflows;

use App\Enums\AssignmentRole;
use App\Enums\FirmRole;
use App\Enums\WorkItemStatus;
use App\Livewire\WorkItems\Index;
use App\Models\AssignmentHistory;
use App\Models\Client;
use App\Models\Firm;
use App\Models\FirmMembership;
use App\Models\Obligation;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Models\WorkItem;
use App\Tenancy\FirmContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\BuildsFirmTenancy;
use Tests\TestCase;

final class WorkRegisterTest extends TestCase
{
    use BuildsFirmTenancy;
    use RefreshDatabase;

    public function test_manager_sees_primary_work_and_follow_ups_as_one_ordered_group(): void
    {
        $fixture = $this->fixture();
        $this->activateFirmMembership($fixture['managerMembership']);

        Livewire::actingAs($fixture['manager'])
            ->test(Index::class)
            ->assertSee('Synthetic VAT review')
            ->assertSeeInOrder(['Main task', 'Follow-up 1'])
            ->assertSee('Original task kept in history')
            ->assertSee('1 corrective follow-up');
    }

    public function test_member_assigned_only_to_follow_up_can_see_the_group(): void
    {
        $fixture = $this->fixture();
        $preparer = User::factory()->create(['name' => 'Synthetic Follow-up Preparer']);
        $membership = $this->createFirmMembership($fixture['firm'], $preparer, FirmRole::Preparer);
        AssignmentHistory::factory()->createForWorkItem(
            $fixture['firm'],
            $fixture['followUp'],
            $membership,
            [
                'assignment_role' => AssignmentRole::Preparer,
                'assigned_by' => $fixture['manager']->id,
            ],
        );
        $this->activateFirmMembership($membership);

        Livewire::actingAs($preparer)
            ->test(Index::class)
            ->assertSee('Synthetic VAT review')
            ->assertSee('Follow-up 1');
    }

    public function test_register_never_lists_another_firms_work(): void
    {
        $fixture = $this->fixture();
        $otherFirm = Firm::factory()->create();
        $otherManager = User::factory()->create();
        $otherMembership = $this->createFirmMembership($otherFirm, $otherManager, FirmRole::Manager);
        $this->activateFirmMembership($otherMembership);

        Livewire::actingAs($otherManager)
            ->test(Index::class)
            ->assertDontSee('Synthetic VAT review')
            ->assertSee('No tasks match these filters');
    }

    public function test_member_without_work_permission_cannot_open_register(): void
    {
        config([
            'platform.features.compliance_operations.enabled' => true,
            'platform.features.compliance_operations.firm_ids' => [],
        ]);
        $firm = Firm::factory()->create();
        $readOnly = User::factory()->create();
        $this->createFirmMembership($firm, $readOnly, FirmRole::ReadOnly);

        $this->actingAs($readOnly)
            ->get(route('work-items.index'))
            ->assertForbidden();
    }

    public function test_status_filter_matches_a_follow_up_without_hiding_its_primary(): void
    {
        $fixture = $this->fixture();
        $this->activateFirmMembership($fixture['managerMembership']);

        Livewire::actingAs($fixture['manager'])
            ->test(Index::class)
            ->set('status', WorkItemStatus::NotStarted->value)
            ->assertSee('Synthetic VAT review')
            ->assertSeeInOrder(['Main task', 'Follow-up 1']);
    }

    /**
     * @return array{
     *   firm: Firm,
     *   manager: User,
     *   managerMembership: FirmMembership,
     *   primary: WorkItem,
     *   followUp: WorkItem
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
        $managerMembership = $this->createFirmMembership($firm, $manager, FirmRole::Manager);
        $this->activateFirmMembership($managerMembership);
        $client = Client::factory()->createForFirm($firm, [
            'internal_code' => 'SYN-REGISTER',
            'legal_name' => 'Synthetic Register Client',
            'created_by' => $manager->id,
        ]);
        $obligation = Obligation::factory()->createForFirm($firm, $client, [
            'obligation_type' => 'Synthetic VAT review',
            'created_by' => $manager->id,
            'verified_by' => $manager->id,
        ]);
        $workflow = app(FirmContext::class)->runForFirm(
            $firm,
            fn (): WorkflowDefinition => WorkflowDefinition::factory()->create([
                'published_by' => $manager->id,
            ]),
        );
        $primary = WorkItem::factory()->createForFirm($firm, $obligation, [
            'workflow_definition_id' => $workflow->id,
            'status' => WorkItemStatus::Completed,
            'created_by' => $manager->id,
        ]);
        $followUp = app(FirmContext::class)->runForFirm(
            $firm,
            fn (): WorkItem => WorkItem::factory()->create([
                'obligation_id' => $obligation->id,
                'parent_work_item_id' => $primary->id,
                'primary_obligation_id' => null,
                'workflow_definition_id' => $workflow->id,
                'status' => WorkItemStatus::NotStarted,
                'created_by' => $manager->id,
                'created_at' => now()->addMinute(),
            ]),
        );

        return compact('firm', 'manager', 'managerMembership', 'primary', 'followUp');
    }
}
