<?php

declare(strict_types=1);

namespace Tests\Feature\Operations;

use App\Actions\Operations\DeleteOperationalFilter;
use App\Enums\FirmRole;
use App\Livewire\Dashboard\Index as DashboardIndex;
use App\Livewire\WorkItems\Index as WorkItemsIndex;
use App\Models\Client;
use App\Models\Firm;
use App\Models\FirmMembership;
use App\Models\Obligation;
use App\Models\SavedOperationalFilter;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\BuildsFirmTenancy;
use Tests\TestCase;

final class SavedOperationalFilterTest extends TestCase
{
    use BuildsFirmTenancy;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'platform.features.compliance_operations.enabled' => true,
            'platform.features.compliance_operations.firm_ids' => [],
        ]);
    }

    public function test_work_register_filter_can_be_saved_applied_and_deleted_by_owner(): void
    {
        $fixture = $this->fixture();
        $component = Livewire::actingAs($fixture['manager'])->test(WorkItemsIndex::class)
            ->set('search', 'Synthetic review')
            ->set('status', 'under_review')
            ->set('savedFilterName', 'My review queue')
            ->call('saveFilter')
            ->assertHasNoErrors();
        $saved = SavedOperationalFilter::query()->sole();

        $component->set('search', '')
            ->set('status', '')
            ->set('selectedSavedFilterId', $saved->id)
            ->call('applySavedFilter')
            ->assertSet('search', 'Synthetic review')
            ->assertSet('status', 'under_review')
            ->call('deleteSavedFilter')
            ->assertSet('selectedSavedFilterId', '');

        $this->assertDatabaseCount('saved_operational_filters', 0);
        $this->assertDatabaseHas('audit_logs', ['action' => 'operational_filter.deleted']);
    }

    public function test_dashboard_filter_changes_client_scope_and_horizon_then_round_trips(): void
    {
        $fixture = $this->fixture();
        $client = Client::factory()->createForFirm($fixture['firm'], [
            'internal_code' => 'SYN-FILTER',
            'legal_name' => 'Synthetic Filter Client',
            'created_by' => $fixture['manager']->id,
        ]);
        Obligation::factory()->createForFirm($fixture['firm'], $client, [
            'obligation_type' => 'Synthetic twenty-day deadline',
            'statutory_due_date' => today()->addDays(20),
            'internal_target_date' => today()->addDays(10),
            'verified_by' => $fixture['manager']->id,
            'created_by' => $fixture['manager']->id,
        ]);

        $component = Livewire::actingAs($fixture['manager'])->test(DashboardIndex::class)
            ->set('clientId', $client->id)
            ->set('horizonDays', 30)
            ->assertSet('summary.due_soon', 1)
            ->set('savedFilterName', 'Synthetic client month')
            ->call('saveFilter')
            ->assertHasNoErrors();
        $saved = SavedOperationalFilter::query()->sole();

        $component->set('clientId', '')
            ->set('horizonDays', 7)
            ->assertSet('summary.due_soon', 0)
            ->set('selectedSavedFilterId', $saved->id)
            ->call('applySavedFilter')
            ->assertSet('clientId', $client->id)
            ->assertSet('horizonDays', 30)
            ->assertSet('summary.due_soon', 1);
    }

    public function test_same_firm_other_user_and_other_firm_cannot_use_or_delete_owner_filter(): void
    {
        $fixture = $this->fixture();
        Livewire::actingAs($fixture['manager'])->test(WorkItemsIndex::class)
            ->set('savedFilterName', 'Owner only')
            ->call('saveFilter');
        $saved = SavedOperationalFilter::query()->sole();

        $otherUser = User::factory()->create();
        $otherMembership = $this->createFirmMembership($fixture['firm'], $otherUser, FirmRole::Manager);
        $this->activateFirmMembership($otherMembership);
        Livewire::actingAs($otherUser)->test(WorkItemsIndex::class)->assertDontSee('Owner only');
        try {
            app(DeleteOperationalFilter::class)->handle($otherUser, $saved);
            $this->fail('Another user must not delete an owner-only saved filter.');
        } catch (AuthorizationException) {
            $this->assertDatabaseHas('saved_operational_filters', ['id' => $saved->id]);
        }

        $otherFirm = Firm::factory()->create();
        $foreignUser = User::factory()->create();
        $foreignMembership = $this->createFirmMembership($otherFirm, $foreignUser, FirmRole::Manager);
        $this->activateFirmMembership($foreignMembership);
        Livewire::actingAs($foreignUser)->test(WorkItemsIndex::class)->assertDontSee('Owner only');
    }

    /** @return array{firm: Firm, manager: User, managerMembership: FirmMembership} */
    private function fixture(): array
    {
        $firm = Firm::factory()->create();
        $manager = User::factory()->create();
        $managerMembership = $this->createFirmMembership($firm, $manager, FirmRole::Manager);
        $this->activateFirmMembership($managerMembership);

        return compact('firm', 'manager', 'managerMembership');
    }
}
