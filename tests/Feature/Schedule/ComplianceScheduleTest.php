<?php

declare(strict_types=1);

namespace Tests\Feature\Schedule;

use App\Enums\FirmRole;
use App\Livewire\Schedule\Index;
use App\Models\Client;
use App\Models\Firm;
use App\Models\FirmMembership;
use App\Models\Obligation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\BuildsFirmTenancy;
use Tests\TestCase;

final class ComplianceScheduleTest extends TestCase
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

    public function test_manager_sees_month_week_and_list_views_of_firm_deadlines(): void
    {
        $fixture = $this->fixture();

        Livewire::actingAs($fixture['manager'])->test(Index::class)
            ->assertSee('Synthetic schedule obligation')
            ->call('setMode', 'week')
            ->assertSet('mode', 'week')
            ->set('anchorDate', today()->toDateString())
            ->assertSee('Synthetic schedule obligation')
            ->call('setMode', 'list')
            ->assertSet('mode', 'list')
            ->assertSee(today()->format('d M Y'))
            ->assertSee('Synthetic schedule obligation');
    }

    public function test_client_and_status_filters_are_accessible_and_client_timeline_is_retained(): void
    {
        $fixture = $this->fixture();
        $otherClient = Client::factory()->createForFirm($fixture['firm'], [
            'internal_code' => 'SYN-OTHER',
            'legal_name' => 'Synthetic Other Client',
            'created_by' => $fixture['manager']->id,
        ]);
        Obligation::factory()->createForFirm($fixture['firm'], $otherClient, [
            'obligation_type' => 'Synthetic other obligation',
            'statutory_due_date' => today(),
            'internal_target_date' => today()->subDay(),
            'verified_by' => $fixture['manager']->id,
            'created_by' => $fixture['manager']->id,
        ]);

        Livewire::actingAs($fixture['manager'])->test(Index::class)
            ->set('mode', 'list')
            ->set('clientId', $fixture['client']->id)
            ->assertSee('Synthetic schedule obligation')
            ->assertDontSee('Synthetic other obligation')
            ->assertSee('Client timeline')
            ->assertSee('Obligation recorded')
            ->set('status', 'cancelled')
            ->assertSee('No deadlines in this period');
    }

    public function test_period_navigation_is_deterministic(): void
    {
        $fixture = $this->fixture();
        $component = Livewire::actingAs($fixture['manager'])->test(Index::class)
            ->set('anchorDate', '2026-07-15')
            ->set('mode', 'month')
            ->call('nextPeriod')
            ->assertSet('anchorDate', '2026-08-15')
            ->call('previousPeriod')
            ->assertSet('anchorDate', '2026-07-15')
            ->set('mode', 'week')
            ->call('nextPeriod')
            ->assertSet('anchorDate', '2026-07-22');

        $component->call('goToToday')->assertSet('anchorDate', today()->toDateString());
    }

    public function test_invalid_calendar_layout_falls_back_to_month(): void
    {
        $fixture = $this->fixture();

        Livewire::actingAs($fixture['manager'])->test(Index::class)
            ->call('setMode', 'unsupported')
            ->assertSet('mode', 'month');
    }

    public function test_schedule_excludes_other_firms_and_rejects_non_operational_roles(): void
    {
        $fixture = $this->fixture();
        $otherFirm = Firm::factory()->create();
        $otherManager = User::factory()->create();
        $otherMembership = $this->createFirmMembership($otherFirm, $otherManager, FirmRole::Manager);
        $this->activateFirmMembership($otherMembership);
        $otherClient = Client::factory()->createForFirm($otherFirm, ['created_by' => $otherManager->id]);
        Obligation::factory()->createForFirm($otherFirm, $otherClient, [
            'obligation_type' => 'Foreign schedule obligation',
            'statutory_due_date' => today(),
            'internal_target_date' => today()->subDay(),
            'verified_by' => $otherManager->id,
            'created_by' => $otherManager->id,
        ]);
        $this->activateFirmMembership($fixture['managerMembership']);

        Livewire::actingAs($fixture['manager'])->test(Index::class)
            ->assertSee('Synthetic schedule obligation')
            ->assertDontSee('Foreign schedule obligation');

        $readOnly = User::factory()->create();
        $readOnlyMembership = $this->createFirmMembership($fixture['firm'], $readOnly, FirmRole::ReadOnly);
        $this->activateFirmMembership($readOnlyMembership);
        Livewire::actingAs($readOnly)->test(Index::class)->assertForbidden();
    }

    /**
     * @return array{firm: Firm, manager: User, managerMembership: FirmMembership, client: Client}
     */
    private function fixture(): array
    {
        $firm = Firm::factory()->create();
        $manager = User::factory()->create();
        $managerMembership = $this->createFirmMembership($firm, $manager, FirmRole::Manager);
        $this->activateFirmMembership($managerMembership);
        $client = Client::factory()->createForFirm($firm, [
            'internal_code' => 'SYN-SCHEDULE',
            'legal_name' => 'Synthetic Schedule Client',
            'created_by' => $manager->id,
        ]);
        Obligation::factory()->createForFirm($firm, $client, [
            'obligation_type' => 'Synthetic schedule obligation',
            'period_label' => 'Synthetic current period',
            'statutory_due_date' => today(),
            'internal_target_date' => today()->subDay(),
            'verified_by' => $manager->id,
            'created_by' => $manager->id,
        ]);

        return compact('firm', 'manager', 'managerMembership', 'client');
    }
}
