<?php

declare(strict_types=1);

namespace Tests\Feature\Exports;

use App\Actions\Audit\RecordAudit;
use App\Actions\Exports\CreateCsvExport;
use App\Enums\FirmRole;
use App\Models\AuditLog;
use App\Models\Firm;
use App\Models\FirmMembership;
use App\Models\User;
use App\Tenancy\FirmContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use LogicException;
use RuntimeException;
use Tests\Concerns\BuildsFirmTenancy;
use Tests\TestCase;

class CreateCsvExportTest extends TestCase
{
    use BuildsFirmTenancy;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('tenant-private');
    }

    public function test_it_creates_a_private_audited_artifact_with_no_export_content_in_the_audit(): void
    {
        $actor = User::factory()->create();
        $firm = Firm::factory()->create();
        $membership = $this->createFirmMembership($firm, $actor, FirmRole::FirmAdministrator);
        $this->activateFirmMembership($membership);

        $artifact = app(CreateCsvExport::class)->handle(
            name: 'access-register',
            headers: ['name', 'email', 'status'],
            rows: [
                ['Synthetic User', '=sensitive@example.test', 'Active'],
            ],
            actor: $actor,
        );

        $this->assertStringStartsWith('access-register-', $artifact->fileName);
        $this->assertStringEndsWith('.csv', $artifact->fileName);
        $this->assertSame("exports/{$artifact->fileName}", $artifact->logicalPath);
        $this->assertStringContainsString("tenants/testing/{$firm->id}/exports/", $artifact->storedPath);
        $this->assertSame(1, $artifact->rowCount);
        $this->assertSame(3, $artifact->columnCount);
        $this->assertSame(1, $artifact->neutralizedCellCount);
        $this->assertSame(64, strlen($artifact->sha256));
        Storage::disk('tenant-private')->assertExists($artifact->storedPath);
        $this->assertSame(
            $artifact->sha256,
            hash('sha256', Storage::disk('tenant-private')->get($artifact->storedPath)),
        );

        $audit = AuditLog::query()->where('action', 'firm.export.created')->sole();

        $this->assertSame($firm->id, $audit->firm_id);
        $this->assertSame((string) $actor->id, $audit->actor_id);
        $this->assertSame($artifact->storedPath, $audit->after_values['stored_path']);
        $this->assertSame($artifact->sha256, $audit->after_values['sha256']);
        $this->assertArrayNotHasKey('contents', $audit->after_values);
        $this->assertStringNotContainsString(
            'sensitive@example.test',
            json_encode($audit->after_values, JSON_THROW_ON_ERROR),
        );
    }

    public function test_tenant_scoped_query_excludes_another_firms_rows_from_the_export(): void
    {
        $actor = User::factory()->create();
        $firmA = Firm::factory()->create();
        $membershipA = $this->createFirmMembership($firmA, $actor, FirmRole::FirmAdministrator);
        $userA = User::factory()->create(['email' => 'firm-a@example.test']);
        $this->createFirmMembership($firmA, $userA);
        $firmB = Firm::factory()->create();
        $userB = User::factory()->create(['email' => 'firm-b@example.test']);
        $this->createFirmMembership($firmB, $userB);
        $this->activateFirmMembership($membershipA);

        $rows = FirmMembership::query()
            ->with('user')
            ->get()
            ->map(fn (FirmMembership $membership): array => [$membership->user->email])
            ->all();

        $artifact = app(CreateCsvExport::class)->handle(
            'membership-emails',
            ['email'],
            $rows,
            $actor,
        );
        $contents = Storage::disk('tenant-private')->get($artifact->storedPath);

        $this->assertStringContainsString('firm-a@example.test', $contents);
        $this->assertStringNotContainsString('firm-b@example.test', $contents);
        $this->assertSame(2, $artifact->rowCount);
    }

    public function test_same_export_name_in_two_firms_uses_different_private_namespaces(): void
    {
        $firmA = Firm::factory()->create();
        $firmB = Firm::factory()->create();
        $context = app(FirmContext::class);
        $action = app(CreateCsvExport::class);

        $artifactA = $context->runForFirm(
            $firmA,
            fn () => $action->handle('synthetic-report', ['value'], [['firm-a']]),
        );
        $artifactB = $context->runForFirm(
            $firmB,
            fn () => $action->handle('synthetic-report', ['value'], [['firm-b']]),
        );

        $this->assertNotSame($artifactA->storedPath, $artifactB->storedPath);
        $this->assertStringContainsString("/{$firmA->id}/", $artifactA->storedPath);
        $this->assertStringContainsString("/{$firmB->id}/", $artifactB->storedPath);
        Storage::disk('tenant-private')->assertExists([
            $artifactA->storedPath,
            $artifactB->storedPath,
        ]);
    }

    public function test_export_fails_without_active_firm_context(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('An active firm context is required.');

        app(CreateCsvExport::class)->handle('report', ['value'], [['synthetic']]);
    }

    public function test_actor_must_match_the_active_membership(): void
    {
        $actor = User::factory()->create();
        $otherUser = User::factory()->create();
        $firm = Firm::factory()->create();
        $membership = $this->createFirmMembership($firm, $actor, FirmRole::FirmAdministrator);
        $this->activateFirmMembership($membership);

        $this->expectException(AuthorizationException::class);

        app(CreateCsvExport::class)->handle(
            'report',
            ['value'],
            [['synthetic']],
            $otherUser,
        );
    }

    public function test_failed_audit_removes_the_generated_artifact(): void
    {
        $firm = Firm::factory()->create();
        $this->mock(RecordAudit::class)
            ->shouldReceive('handle')
            ->once()
            ->andThrow(new RuntimeException('Synthetic audit failure.'));

        try {
            app(FirmContext::class)->runForFirm(
                $firm,
                fn () => app(CreateCsvExport::class)->handle(
                    'report',
                    ['value'],
                    [['synthetic']],
                ),
            );

            $this->fail('The synthetic audit failure was not thrown.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Synthetic audit failure.', $exception->getMessage());
        }

        $this->assertSame([], Storage::disk('tenant-private')->allFiles());
    }
}
