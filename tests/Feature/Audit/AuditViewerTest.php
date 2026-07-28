<?php

declare(strict_types=1);

namespace Tests\Feature\Audit;

use App\Actions\Audit\RecordAudit;
use App\Enums\FirmRole;
use App\Livewire\Audit\Index;
use App\Models\AuditLog;
use App\Models\Firm;
use App\Models\FirmMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\Concerns\BuildsFirmTenancy;
use Tests\TestCase;

final class AuditViewerTest extends TestCase
{
    use BuildsFirmTenancy;
    use RefreshDatabase;

    public function test_permitted_member_reads_retained_records_with_actor_and_values(): void
    {
        $fixture = $this->fixture();
        $this->recordAudit($fixture, 'client.created', 'Synthetic client creation reason.');

        Livewire::actingAs($fixture['admin'])
            ->test(Index::class)
            ->assertOk()
            ->assertSee('client.created')
            ->assertSee('Synthetic client creation reason.')
            ->assertSee('Synthetic Administrator');
    }

    public function test_records_are_filtered_by_action(): void
    {
        $fixture = $this->fixture();
        $this->recordAudit($fixture, 'client.created', 'Synthetic creation reason.');
        $this->recordAudit($fixture, 'work_item.status_transitioned', 'Synthetic transition reason.');

        Livewire::actingAs($fixture['admin'])
            ->test(Index::class)
            ->set('action', 'client.created')
            ->assertSee('Synthetic creation reason.')
            ->assertDontSee('Synthetic transition reason.');
    }

    public function test_records_are_filtered_by_search_and_date_range(): void
    {
        $fixture = $this->fixture();
        $this->recordAudit($fixture, 'client.created', 'Synthetic distinctive marker.');
        $this->recordAudit($fixture, 'client.created', 'Synthetic other reason.');

        Livewire::actingAs($fixture['admin'])
            ->test(Index::class)
            ->set('search', 'distinctive')
            ->assertSee('Synthetic distinctive marker.')
            ->assertDontSee('Synthetic other reason.');

        $tomorrow = Carbon::now('UTC')->addDay()->toDateString();

        Livewire::actingAs($fixture['admin'])
            ->test(Index::class)
            ->set('fromDate', $tomorrow)
            ->assertDontSee('Synthetic distinctive marker.')
            ->assertSee('No retained records match');
    }

    public function test_clearing_filters_restores_the_full_register(): void
    {
        $fixture = $this->fixture();
        $this->recordAudit($fixture, 'client.created', 'Synthetic restored reason.');

        Livewire::actingAs($fixture['admin'])
            ->test(Index::class)
            ->set('search', 'nothing-matches-this')
            ->assertDontSee('Synthetic restored reason.')
            ->call('clearFilters')
            ->assertSet('search', '')
            ->assertSee('Synthetic restored reason.');
    }

    public function test_one_firm_never_reads_another_firms_retained_records(): void
    {
        $fixture = $this->fixture();
        $this->recordAudit($fixture, 'client.created', 'Synthetic first firm reason.');

        $otherFirm = Firm::factory()->create();
        $otherAdmin = User::factory()->create(['name' => 'Synthetic Other Administrator']);
        $otherMembership = $this->createFirmMembership($otherFirm, $otherAdmin, FirmRole::FirmAdministrator);
        $this->activateFirmMembership($otherMembership);
        app(RecordAudit::class)->handle(
            action: 'client.created',
            actor: $otherAdmin,
            reason: 'Synthetic other firm reason.',
        );

        Livewire::actingAs($otherAdmin)
            ->test(Index::class)
            ->assertSee('Synthetic other firm reason.')
            ->assertDontSee('Synthetic first firm reason.');
    }

    public function test_member_without_the_audit_permission_cannot_open_the_register(): void
    {
        $fixture = $this->fixture();
        $preparer = User::factory()->create();
        $preparerMembership = $this->createFirmMembership($fixture['firm'], $preparer, FirmRole::Preparer);
        $this->activateFirmMembership($preparerMembership);

        Livewire::actingAs($preparer)
            ->test(Index::class)
            ->assertForbidden();
    }

    public function test_the_register_is_unavailable_when_the_feature_flag_is_disabled(): void
    {
        $fixture = $this->fixture();
        config(['platform.features.audit_viewer.enabled' => false]);

        Livewire::actingAs($fixture['admin'])
            ->test(Index::class)
            ->assertNotFound();
    }

    public function test_redacted_values_stay_redacted_in_the_register(): void
    {
        $fixture = $this->fixture();
        app(RecordAudit::class)->handle(
            action: 'member.credential_rotated',
            actor: $fixture['admin'],
            after: ['api_token' => 'synthetic-secret-value', 'status' => 'active'],
            reason: 'Synthetic redaction check.',
        );

        Livewire::actingAs($fixture['admin'])
            ->test(Index::class)
            ->assertSee('[REDACTED]')
            ->assertDontSee('synthetic-secret-value');
    }

    public function test_the_register_exposes_no_write_ability(): void
    {
        $fixture = $this->fixture();
        $auditLog = $this->recordAudit($fixture, 'client.created', 'Synthetic immutability check.');

        $this->assertTrue($fixture['admin']->can('viewAny', AuditLog::class));
        $this->assertFalse($fixture['admin']->can('create', AuditLog::class));
        $this->assertFalse($fixture['admin']->can('update', $auditLog));
        $this->assertFalse($fixture['admin']->can('delete', $auditLog));
        $this->assertFalse($fixture['admin']->can('forceDelete', $auditLog));
    }

    /**
     * @param  array{admin: User, adminMembership: FirmMembership}  $fixture
     */
    private function recordAudit(array $fixture, string $action, string $reason): AuditLog
    {
        $this->activateFirmMembership($fixture['adminMembership']);

        return app(RecordAudit::class)->handle(
            action: $action,
            actor: $fixture['admin'],
            reason: $reason,
        );
    }

    /**
     * @return array{firm: Firm, admin: User, adminMembership: FirmMembership}
     */
    private function fixture(): array
    {
        config([
            'platform.features.audit_viewer.enabled' => true,
            'platform.features.audit_viewer.firm_ids' => [],
        ]);
        $firm = Firm::factory()->create();
        $admin = User::factory()->create(['name' => 'Synthetic Administrator']);
        $adminMembership = $this->createFirmMembership($firm, $admin, FirmRole::FirmAdministrator);
        $this->activateFirmMembership($adminMembership);

        return compact('firm', 'admin', 'adminMembership');
    }
}
