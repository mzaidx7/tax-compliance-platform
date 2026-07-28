<?php

declare(strict_types=1);

namespace Tests\Feature\Exports;

use App\Actions\Exports\CreateCsvExport;
use App\Enums\FirmRole;
use App\Models\AuditLog;
use App\Models\Firm;
use App\Models\User;
use App\Tenancy\TenantStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsFirmTenancy;
use Tests\TestCase;

final class ExportDownloadTest extends TestCase
{
    use BuildsFirmTenancy;
    use RefreshDatabase;

    public function test_authorized_member_downloads_verified_export_and_action_is_audited(): void
    {
        $fixture = $this->fixture();
        $artifact = app(CreateCsvExport::class)->handle(
            'synthetic-register',
            ['reference', 'amount'],
            [['SAFE-001', '125.00']],
            $fixture['manager'],
        );

        $response = $this->actingAs($fixture['manager'])
            ->get(route('exports.download', $artifact->auditLogId));

        $response->assertOk()
            ->assertDownload($artifact->fileName)
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('SAFE-001', $response->streamedContent());

        $download = AuditLog::query()->where('action', 'firm.export.downloaded')->sole();
        $this->assertSame($artifact->auditLogId, $download->after_values['export_audit_log_id']);
        $this->assertSame($artifact->sha256, $download->after_values['sha256']);
    }

    public function test_member_without_audit_permission_cannot_download_export(): void
    {
        $fixture = $this->fixture();
        $artifact = app(CreateCsvExport::class)->handle(
            'synthetic-register',
            ['reference'],
            [['SAFE-001']],
            $fixture['manager'],
        );
        $preparer = User::factory()->create();
        $this->createFirmMembership($fixture['firm'], $preparer, FirmRole::Preparer);

        $this->actingAs($preparer)
            ->withSession(['active_firm_id' => $fixture['firm']->id])
            ->get(route('exports.download', $artifact->auditLogId))
            ->assertForbidden();

        $this->assertDatabaseMissing('audit_logs', ['action' => 'firm.export.downloaded']);
    }

    public function test_another_firm_cannot_resolve_or_download_export(): void
    {
        $fixture = $this->fixture();
        $artifact = app(CreateCsvExport::class)->handle(
            'synthetic-register',
            ['reference'],
            [['SAFE-001']],
            $fixture['manager'],
        );
        $otherFirm = Firm::factory()->create();
        $otherManager = User::factory()->create();
        $this->createFirmMembership($otherFirm, $otherManager, FirmRole::Manager);

        $this->actingAs($otherManager)
            ->get(route('exports.download', $artifact->auditLogId))
            ->assertNotFound();
    }

    public function test_tampered_artifact_is_rejected_without_download_audit(): void
    {
        $fixture = $this->fixture();
        $artifact = app(CreateCsvExport::class)->handle(
            'synthetic-register',
            ['reference'],
            [['SAFE-001']],
            $fixture['manager'],
        );
        app(TenantStorage::class)->put($artifact->logicalPath, 'tampered');

        $this->actingAs($fixture['manager'])
            ->get(route('exports.download', $artifact->auditLogId))
            ->assertConflict();

        $this->assertDatabaseMissing('audit_logs', ['action' => 'firm.export.downloaded']);
    }

    public function test_missing_artifact_returns_gone_without_download_audit(): void
    {
        $fixture = $this->fixture();
        $artifact = app(CreateCsvExport::class)->handle(
            'synthetic-register',
            ['reference'],
            [['SAFE-001']],
            $fixture['manager'],
        );
        app(TenantStorage::class)->delete($artifact->logicalPath);

        $this->actingAs($fixture['manager'])
            ->get(route('exports.download', $artifact->auditLogId))
            ->assertGone();

        $this->assertDatabaseMissing('audit_logs', ['action' => 'firm.export.downloaded']);
    }

    /**
     * @return array{firm: Firm, manager: User}
     */
    private function fixture(): array
    {
        config([
            'platform.features.audit_viewer.enabled' => true,
            'platform.features.audit_viewer.firm_ids' => [],
        ]);
        $firm = Firm::factory()->create();
        $manager = User::factory()->create();
        $membership = $this->createFirmMembership($firm, $manager, FirmRole::Manager);
        $this->activateFirmMembership($membership);

        return compact('firm', 'manager');
    }
}
