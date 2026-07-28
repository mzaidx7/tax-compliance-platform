<?php

declare(strict_types=1);

namespace Tests\Feature\Settings;

use App\Actions\Settings\SetFeatureFlagOverride;
use App\Enums\Feature;
use App\Enums\FirmRole;
use App\Livewire\Settings\FeatureFlags as FeatureFlagsComponent;
use App\Models\AuditLog;
use App\Models\Firm;
use App\Models\FirmMembership;
use App\Models\Obligation;
use App\Models\User;
use App\Support\FeatureFlags;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Concerns\BuildsFirmTenancy;
use Tests\TestCase;

final class FeatureFlagAdministrationTest extends TestCase
{
    use BuildsFirmTenancy;
    use RefreshDatabase;

    public function test_an_override_enables_a_feature_that_configuration_disables(): void
    {
        $fixture = $this->fixture();
        config(['platform.features.audit_viewer.enabled' => false, 'platform.features.audit_viewer.firm_ids' => []]);

        $reader = app(FeatureFlags::class);
        $this->assertFalse($reader->enabled(Feature::AuditViewer, $fixture['firm']->id));

        app(SetFeatureFlagOverride::class)->handle(
            $fixture['admin'],
            Feature::AuditViewer,
            true,
            'Synthetic pilot enablement.',
        );

        $this->assertTrue($reader->enabled(Feature::AuditViewer, $fixture['firm']->id));
    }

    public function test_an_override_disables_a_feature_that_configuration_enables(): void
    {
        $fixture = $this->fixture();
        config(['platform.features.compliance_operations.enabled' => true]);

        $reader = app(FeatureFlags::class);
        $this->assertTrue($reader->enabled(Feature::ComplianceOperations, $fixture['firm']->id));

        app(SetFeatureFlagOverride::class)->handle(
            $fixture['admin'],
            Feature::ComplianceOperations,
            false,
            'Synthetic suspension of operations.',
        );

        $this->assertFalse($reader->enabled(Feature::ComplianceOperations, $fixture['firm']->id));
    }

    public function test_configuration_remains_the_fallback_without_an_override(): void
    {
        $fixture = $this->fixture();
        config(['platform.features.imports.enabled' => true]);

        $this->assertTrue(app(FeatureFlags::class)->enabled(Feature::Imports, $fixture['firm']->id));
    }

    public function test_one_firms_override_never_affects_another_firm(): void
    {
        $fixture = $this->fixture();
        config(['platform.features.audit_viewer.enabled' => false, 'platform.features.audit_viewer.firm_ids' => []]);
        app(SetFeatureFlagOverride::class)->handle(
            $fixture['admin'],
            Feature::AuditViewer,
            true,
            'Synthetic first firm enablement.',
        );

        $otherFirm = Firm::factory()->create();

        $reader = app(FeatureFlags::class);
        $this->assertTrue($reader->enabled(Feature::AuditViewer, $fixture['firm']->id));
        $this->assertFalse($reader->enabled(Feature::AuditViewer, $otherFirm->id));
    }

    public function test_a_change_is_recorded_in_append_only_audit_history(): void
    {
        $fixture = $this->fixture();
        config(['platform.features.audit_viewer.enabled' => false, 'platform.features.audit_viewer.firm_ids' => []]);

        app(SetFeatureFlagOverride::class)->handle(
            $fixture['admin'],
            Feature::AuditViewer,
            true,
            'Synthetic audited enablement.',
        );

        $audit = AuditLog::query()->where('action', 'feature_flag.overridden')->sole();
        $this->assertSame(Feature::AuditViewer->value, $audit->after_values['feature']);
        $this->assertNull($audit->before_values['enabled']);
        $this->assertTrue($audit->after_values['enabled']);
        $this->assertSame('Synthetic audited enablement.', $audit->reason);
    }

    public function test_a_second_change_records_the_previous_state(): void
    {
        $fixture = $this->fixture();
        app(SetFeatureFlagOverride::class)->handle($fixture['admin'], Feature::Imports, true, 'Synthetic on.');
        app(SetFeatureFlagOverride::class)->handle($fixture['admin'], Feature::Imports, false, 'Synthetic off.');

        $audit = AuditLog::query()->where('action', 'feature_flag.overridden')->orderByDesc('created_at')->first();
        $this->assertTrue($audit->before_values['enabled']);
        $this->assertFalse($audit->after_values['enabled']);
    }

    public function test_a_blank_reason_is_rejected(): void
    {
        $fixture = $this->fixture();

        $this->expectException(ValidationException::class);
        app(SetFeatureFlagOverride::class)->handle($fixture['admin'], Feature::Imports, true, ' ');
    }

    public function test_a_member_without_manage_firm_settings_cannot_change_a_flag(): void
    {
        $fixture = $this->fixture();
        $manager = User::factory()->create();
        $managerMembership = $this->createFirmMembership($fixture['firm'], $manager, FirmRole::Manager);
        $this->activateFirmMembership($managerMembership);

        $this->expectException(AuthorizationException::class);
        app(SetFeatureFlagOverride::class)->handle($manager, Feature::Imports, true, 'Synthetic unauthorised change.');
    }

    public function test_enabling_a_feature_does_not_bypass_a_guarded_actions_permission(): void
    {
        $fixture = $this->fixture();
        config(['platform.features.compliance_operations.enabled' => false, 'platform.features.compliance_operations.firm_ids' => []]);
        app(SetFeatureFlagOverride::class)->handle(
            $fixture['admin'],
            Feature::ComplianceOperations,
            true,
            'Synthetic operations enablement.',
        );

        $preparer = User::factory()->create();
        $preparerMembership = $this->createFirmMembership($fixture['firm'], $preparer, FirmRole::Preparer);
        $this->activateFirmMembership($preparerMembership);

        $this->assertTrue(app(FeatureFlags::class)->enabled(Feature::ComplianceOperations, $fixture['firm']->id));
        $this->assertFalse($preparer->can('create', Obligation::class));
    }

    public function test_administrator_changes_a_flag_through_the_livewire_interface(): void
    {
        $fixture = $this->fixture();
        config(['platform.features.audit_viewer.enabled' => false, 'platform.features.audit_viewer.firm_ids' => []]);

        Livewire::actingAs($fixture['admin'])
            ->test(FeatureFlagsComponent::class)
            ->assertOk()
            ->call('openOverride', Feature::AuditViewer->value)
            ->assertSet('showModal', true)
            ->set('desiredEnabled', true)
            ->set('reason', 'Synthetic Livewire enablement.')
            ->call('saveOverride')
            ->assertHasNoErrors()
            ->assertSet('showModal', false);

        $this->assertTrue(app(FeatureFlags::class)->enabled(Feature::AuditViewer, $fixture['firm']->id));
    }

    public function test_a_non_administrator_cannot_open_the_feature_interface(): void
    {
        $fixture = $this->fixture();
        $preparer = User::factory()->create();
        $preparerMembership = $this->createFirmMembership($fixture['firm'], $preparer, FirmRole::Preparer);
        $this->activateFirmMembership($preparerMembership);

        Livewire::actingAs($preparer)
            ->test(FeatureFlagsComponent::class)
            ->assertForbidden();
    }

    /**
     * @return array{firm: Firm, admin: User, adminMembership: FirmMembership}
     */
    private function fixture(): array
    {
        $firm = Firm::factory()->create();
        $admin = User::factory()->create(['name' => 'Synthetic Administrator']);
        $adminMembership = $this->createFirmMembership($firm, $admin, FirmRole::FirmAdministrator);
        $this->activateFirmMembership($adminMembership);

        return compact('firm', 'admin', 'adminMembership');
    }
}
