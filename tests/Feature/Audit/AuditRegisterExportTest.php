<?php

declare(strict_types=1);

namespace Tests\Feature\Audit;

use App\Actions\Audit\ExportAuditRegister;
use App\Actions\Audit\RecordAudit;
use App\Data\AuditRegisterFilters;
use App\Enums\FirmRole;
use App\Livewire\Audit\Index;
use App\Models\AuditLog;
use App\Models\Firm;
use App\Models\FirmMembership;
use App\Models\User;
use App\Tenancy\TenantStorage;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\BuildsFirmTenancy;
use Tests\TestCase;

final class AuditRegisterExportTest extends TestCase
{
    use BuildsFirmTenancy;
    use RefreshDatabase;

    public function test_permitted_member_exports_the_register_and_the_download_is_recorded_separately(): void
    {
        $fixture = $this->fixture();
        $this->recordAudit($fixture, 'client.created', 'Synthetic export subject.');

        $artifact = app(ExportAuditRegister::class)->handle(
            $fixture['admin'],
            new AuditRegisterFilters,
        );

        $this->assertSame(1, $artifact->rowCount);
        $this->assertStringStartsWith('audit-register-', $artifact->fileName);
        $this->assertTrue(app(TenantStorage::class)->exists($artifact->logicalPath));

        $contents = app(TenantStorage::class)->get($artifact->logicalPath);
        $this->assertStringContainsString('client.created', $contents);
        $this->assertStringContainsString('Synthetic export subject.', $contents);

        $download = AuditLog::query()->where('action', 'audit_register.exported')->sole();
        $this->assertSame($artifact->fileName, $download->after_values['file_name']);
        $this->assertSame($artifact->sha256, $download->after_values['sha256']);
        $this->assertSame(1, $download->after_values['row_count']);
    }

    public function test_the_export_applies_the_registers_current_filters(): void
    {
        $fixture = $this->fixture();
        $this->recordAudit($fixture, 'client.created', 'Synthetic included reason.');
        $this->recordAudit($fixture, 'work_item.status_transitioned', 'Synthetic excluded reason.');

        $artifact = app(ExportAuditRegister::class)->handle(
            $fixture['admin'],
            new AuditRegisterFilters(action: 'client.created'),
        );

        $contents = app(TenantStorage::class)->get($artifact->logicalPath);
        $this->assertSame(1, $artifact->rowCount);
        $this->assertStringContainsString('Synthetic included reason.', $contents);
        $this->assertStringNotContainsString('Synthetic excluded reason.', $contents);
    }

    public function test_the_export_never_contains_another_firms_records(): void
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

        $artifact = app(ExportAuditRegister::class)->handle($otherAdmin, new AuditRegisterFilters);
        $contents = app(TenantStorage::class)->get($artifact->logicalPath);

        $this->assertStringContainsString('Synthetic other firm reason.', $contents);
        $this->assertStringNotContainsString('Synthetic first firm reason.', $contents);
    }

    public function test_redacted_values_are_never_restored_by_the_export(): void
    {
        $fixture = $this->fixture();
        app(RecordAudit::class)->handle(
            action: 'member.credential_rotated',
            actor: $fixture['admin'],
            after: ['api_token' => 'synthetic-secret-value'],
            reason: 'Synthetic redaction check.',
        );

        $artifact = app(ExportAuditRegister::class)->handle($fixture['admin'], new AuditRegisterFilters);
        $contents = app(TenantStorage::class)->get($artifact->logicalPath);

        $this->assertStringNotContainsString('synthetic-secret-value', $contents);
    }

    public function test_member_without_the_audit_permission_cannot_export(): void
    {
        $fixture = $this->fixture();
        $preparer = User::factory()->create();
        $preparerMembership = $this->createFirmMembership($fixture['firm'], $preparer, FirmRole::Preparer);
        $this->activateFirmMembership($preparerMembership);

        $this->expectException(AuthorizationException::class);
        app(ExportAuditRegister::class)->handle($preparer, new AuditRegisterFilters);
    }

    public function test_the_export_is_unavailable_when_the_feature_flag_is_disabled(): void
    {
        $fixture = $this->fixture();
        config(['platform.features.audit_viewer.enabled' => false]);

        $this->expectException(AuthorizationException::class);
        app(ExportAuditRegister::class)->handle($fixture['admin'], new AuditRegisterFilters);
    }

    public function test_the_export_creates_no_edit_or_delete_path_for_the_records_it_reads(): void
    {
        $fixture = $this->fixture();
        $original = $this->recordAudit($fixture, 'client.created', 'Synthetic untouched reason.');

        app(ExportAuditRegister::class)->handle($fixture['admin'], new AuditRegisterFilters);

        $this->assertSame('Synthetic untouched reason.', $original->refresh()->reason);
        $this->assertDatabaseHas('audit_logs', [
            'id' => $original->id,
            'action' => 'client.created',
        ]);
    }

    public function test_member_exports_through_the_livewire_interface(): void
    {
        $fixture = $this->fixture();
        $this->recordAudit($fixture, 'client.created', 'Synthetic Livewire export subject.');

        $component = Livewire::actingAs($fixture['admin'])
            ->test(Index::class)
            ->call('exportRegister')
            ->assertHasNoErrors();

        $export = AuditLog::query()->where('action', 'firm.export.created')->sole();
        $component->assertRedirect(route('exports.download', $export->id));
        $this->assertDatabaseHas('audit_logs', ['action' => 'audit_register.exported']);
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
