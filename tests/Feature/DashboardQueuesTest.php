<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\AssignmentRole;
use App\Enums\FirmRole;
use App\Livewire\Dashboard\Index;
use App\Models\AssignmentHistory;
use App\Models\Client;
use App\Models\Firm;
use App\Models\FirmMembership;
use App\Models\Obligation;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Models\WorkItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\BuildsFirmTenancy;
use Tests\TestCase;

final class DashboardQueuesTest extends TestCase
{
    use BuildsFirmTenancy;
    use RefreshDatabase;

    public function test_manager_sees_distinct_stored_work_queues_and_workload(): void
    {
        $fixture = $this->fixture();

        Livewire::actingAs($fixture['manager'])->test(Index::class)
            ->assertSet('summary.awaiting_client', 1)
            ->assertSet('summary.under_review', 1)
            ->assertSet('summary.unassigned', 1)
            ->assertSet('summary.active_workload', 3)
            ->assertSee('Synthetic awaiting-client work')
            ->assertSee('Synthetic under-review work')
            ->assertSee('Synthetic unassigned work')
            ->assertSee('Synthetic Queue Preparer');
    }

    public function test_preparer_queue_and_workload_preserve_existing_assignment_visibility(): void
    {
        $fixture = $this->fixture();
        $this->activateFirmMembership($fixture['preparerMembership']);

        Livewire::actingAs($fixture['preparer'])->test(Index::class)
            ->assertSet('summary.awaiting_client', 1)
            ->assertSet('summary.under_review', 0)
            ->assertSet('summary.unassigned', 0)
            ->assertSet('summary.active_workload', 1)
            ->assertSee('Synthetic awaiting-client work')
            ->assertDontSee('Synthetic under-review work')
            ->assertDontSee('Synthetic unassigned work');
    }

    public function test_client_saved_filter_also_scopes_new_work_queues(): void
    {
        $fixture = $this->fixture();
        $otherClient = Client::factory()->createForFirm($fixture['firm'], [
            'internal_code' => 'SYN-QUEUE-OTHER', 'created_by' => $fixture['manager']->id,
        ]);

        Livewire::actingAs($fixture['manager'])->test(Index::class)
            ->set('clientId', $otherClient->id)
            ->assertSet('summary.awaiting_client', 0)
            ->assertSet('summary.under_review', 0)
            ->assertSet('summary.unassigned', 0)
            ->assertSet('summary.active_workload', 0);
    }

    /**
     * @return array{
     *  firm: Firm, manager: User, managerMembership: FirmMembership,
     *  preparer: User, preparerMembership: FirmMembership
     * }
     */
    private function fixture(): array
    {
        $firm = Firm::factory()->create();
        $manager = User::factory()->create();
        $preparer = User::factory()->create(['name' => 'Synthetic Queue Preparer']);
        $managerMembership = $this->createFirmMembership($firm, $manager, FirmRole::Manager);
        $preparerMembership = $this->createFirmMembership($firm, $preparer, FirmRole::Preparer);
        $this->activateFirmMembership($managerMembership);
        $client = Client::factory()->createForFirm($firm, [
            'internal_code' => 'SYN-QUEUES', 'created_by' => $manager->id,
        ]);
        $workflow = WorkflowDefinition::factory()->create(['published_by' => $manager->id]);
        $awaiting = $this->work($firm, $client, $workflow, $manager, 'Synthetic awaiting-client work', 'awaiting_client');
        $underReview = $this->work($firm, $client, $workflow, $manager, 'Synthetic under-review work', 'under_review');
        $this->work($firm, $client, $workflow, $manager, 'Synthetic unassigned work', 'not_started');
        AssignmentHistory::factory()->createForWorkItem($firm, $awaiting, $preparerMembership, [
            'assignment_role' => AssignmentRole::Preparer, 'assigned_by' => $manager->id,
        ]);
        AssignmentHistory::factory()->createForWorkItem($firm, $underReview, $managerMembership, [
            'assignment_role' => AssignmentRole::Reviewer, 'assigned_by' => $manager->id,
        ]);

        return compact('firm', 'manager', 'managerMembership', 'preparer', 'preparerMembership');
    }

    private function work(
        Firm $firm,
        Client $client,
        WorkflowDefinition $workflow,
        User $manager,
        string $type,
        string $status,
    ): WorkItem {
        $obligation = Obligation::factory()->createForFirm($firm, $client, [
            'obligation_type' => $type, 'statutory_due_date' => today()->addDays(10),
            'internal_target_date' => today()->addDays(5), 'verified_by' => $manager->id, 'created_by' => $manager->id,
        ]);

        return WorkItem::factory()->createForFirm($firm, $obligation, [
            'workflow_definition_id' => $workflow->id, 'status' => $status, 'created_by' => $manager->id,
        ]);
    }
}
