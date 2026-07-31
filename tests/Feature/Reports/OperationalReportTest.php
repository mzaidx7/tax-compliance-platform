<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use App\Actions\Exports\AuthorizeExportDownload;
use App\Actions\Reports\BuildOperationalReport;
use App\Enums\FirmRole;
use App\Enums\OperationalReportType;
use App\Livewire\Reports\Index;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\ClientDocument;
use App\Models\DocumentTypeVersion;
use App\Models\Firm;
use App\Models\FirmMembership;
use App\Models\Obligation;
use App\Models\TaxPeriod;
use App\Models\TaxRegistration;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Models\WorkItem;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Concerns\BuildsFirmTenancy;
use Tests\TestCase;

final class OperationalReportTest extends TestCase
{
    use BuildsFirmTenancy;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('tenant-private');
    }

    public function test_each_operational_report_uses_stored_firm_scoped_rows_and_expected_columns(): void
    {
        $fixture = $this->fixture();
        $builder = app(BuildOperationalReport::class);
        $expected = [
            OperationalReportType::MonthlySchedule->value => ['client_code', 'effective_due_date'],
            OperationalReportType::TaxPeriods->value => ['tax_type', 'period_status'],
            OperationalReportType::ExpiringDocuments->value => ['document_type', 'timing'],
            OperationalReportType::WorkloadCompletion->value => ['work_status', 'responsible_manager'],
        ];

        foreach (OperationalReportType::cases() as $type) {
            $preview = $builder->preview($fixture['manager'], $type, today()->format('Y-m'));
            $this->assertNotEmpty($preview['rows'], $type->value);
            foreach ($expected[$type->value] as $header) {
                $this->assertContains($header, $preview['headers']);
            }
        }
    }

    public function test_report_export_is_spreadsheet_safe_audited_and_downloadable_by_its_report_owner(): void
    {
        $fixture = $this->fixture();
        $artifact = app(BuildOperationalReport::class)->export(
            $fixture['manager'],
            OperationalReportType::MonthlySchedule,
            today()->format('Y-m'),
        );
        $audit = AuditLog::query()->findOrFail($artifact->auditLogId);
        $download = app(AuthorizeExportDownload::class)->handle($fixture['manager'], $audit);

        $this->assertSame($artifact->fileName, $download->fileName);
        $this->assertStringStartsWith('monthly-schedule-', $artifact->fileName);
        Storage::disk('tenant-private')->assertExists($artifact->storedPath);
        $this->assertDatabaseHas('audit_logs', ['action' => 'firm.export.downloaded']);
    }

    public function test_monthly_schedule_includes_an_obligation_due_on_the_last_day_of_the_month(): void
    {
        $fixture = $this->fixture();

        $preview = app(BuildOperationalReport::class)->preview(
            $fixture['manager'],
            OperationalReportType::MonthlySchedule,
            today()->format('Y-m'),
        );

        $this->assertNotEmpty($preview['rows']);
        $this->assertSame('Synthetic report obligation', $preview['rows'][0][2]);
    }

    public function test_livewire_preview_and_export_reject_member_without_report_permission(): void
    {
        $fixture = $this->fixture();
        Livewire::actingAs($fixture['manager'])->test(Index::class)
            ->assertSee('Synthetic report obligation')
            ->assertSee('Monthly schedule')
            ->set('reportType', 'tax_periods')
            ->assertSee('VAT');

        $preparer = User::factory()->create();
        $preparerMembership = $this->createFirmMembership($fixture['firm'], $preparer, FirmRole::Preparer);
        $this->activateFirmMembership($preparerMembership);
        Livewire::actingAs($preparer)->test(Index::class)->assertForbidden();
        $this->expectException(AuthorizationException::class);
        app(BuildOperationalReport::class)->preview($preparer, OperationalReportType::MonthlySchedule, today()->format('Y-m'));
    }

    public function test_reports_exclude_other_firms_and_sensitive_identifiers(): void
    {
        $fixture = $this->fixture();
        $otherFirm = Firm::factory()->create();
        $otherManager = User::factory()->create();
        $otherMembership = $this->createFirmMembership($otherFirm, $otherManager, FirmRole::Manager);
        $this->activateFirmMembership($otherMembership);
        $otherClient = Client::factory()->createForFirm($otherFirm, [
            'internal_code' => 'FOREIGN-REPORT',
            'created_by' => $otherManager->id,
        ]);
        Obligation::factory()->createForFirm($otherFirm, $otherClient, [
            'obligation_type' => 'Foreign report obligation',
            'statutory_due_date' => today(),
            'internal_target_date' => today()->subDay(),
            'verified_by' => $otherManager->id,
            'created_by' => $otherManager->id,
        ]);
        $this->activateFirmMembership($fixture['managerMembership']);

        $schedule = app(BuildOperationalReport::class)->preview(
            $fixture['manager'], OperationalReportType::MonthlySchedule, today()->format('Y-m'),
        );
        $tax = app(BuildOperationalReport::class)->preview(
            $fixture['manager'], OperationalReportType::TaxPeriods, today()->format('Y-m'),
        );
        $encoded = json_encode([$schedule, $tax], JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('FOREIGN-REPORT', $encoded);
        $this->assertStringNotContainsString('100000000000001', $encoded);
    }

    /**
     * @return array{firm: Firm, manager: User, managerMembership: FirmMembership}
     */
    private function fixture(): array
    {
        $firm = Firm::factory()->create();
        $manager = User::factory()->create();
        $managerMembership = $this->createFirmMembership($firm, $manager, FirmRole::Manager);
        $this->activateFirmMembership($managerMembership);
        $client = Client::factory()->createForFirm($firm, [
            'internal_code' => 'SYN-REPORT',
            'legal_name' => 'Synthetic Report Client',
            'created_by' => $manager->id,
        ]);
        $obligation = Obligation::factory()->createForFirm($firm, $client, [
            'obligation_type' => 'Synthetic report obligation',
            'period_label' => 'Synthetic report period',
            'statutory_due_date' => today(),
            'internal_target_date' => today()->subDay(),
            'verified_by' => $manager->id,
            'created_by' => $manager->id,
        ]);
        $registration = TaxRegistration::query()->create([
            'client_id' => $client->id, 'tax_type' => 'vat',
            'registration_number' => '100000000000001', 'registration_number_normalized' => '100000000000001',
            'status' => 'active', 'effective_from' => today()->startOfMonth(), 'created_by' => $manager->id,
        ]);
        TaxPeriod::query()->create([
            'tax_registration_id' => $registration->id, 'label' => 'Synthetic tax period',
            'starts_on' => today()->startOfMonth(), 'ends_on' => today()->endOfMonth(),
            'status' => 'open', 'created_by' => $manager->id,
        ]);
        $type = DocumentTypeVersion::query()->create([
            'key' => 'synthetic-report-document', 'version' => 1, 'name' => 'Synthetic report document',
            'expiry_required' => true, 'reminder_days' => [30], 'overdue_repeat_days' => null,
            'published_at' => now(), 'created_by' => $manager->id,
        ]);
        ClientDocument::query()->create([
            'client_id' => $client->id, 'document_type_version_id' => $type->id,
            'reference_label' => 'Sensitive synthetic label', 'expires_on' => today()->endOfMonth()->toDateString(),
            'created_by' => $manager->id, 'recorded_at' => now(),
        ]);
        $workflow = WorkflowDefinition::factory()->create(['published_by' => $manager->id]);
        WorkItem::factory()->createForFirm($firm, $obligation, [
            'workflow_definition_id' => $workflow->id,
            'status' => 'under_review',
            'created_by' => $manager->id,
        ]);

        return compact('firm', 'manager', 'managerMembership');
    }
}
