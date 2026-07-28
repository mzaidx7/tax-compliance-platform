<?php

declare(strict_types=1);

namespace Tests\Feature\Documents;

use App\Actions\Documents\StoreDocumentEvidence;
use App\Contracts\MalwareScanner;
use App\Enums\DocumentPurpose;
use App\Enums\FirmRole;
use App\Enums\MalwareScanVerdict;
use App\Livewire\WorkItems\Index as WorkRegister;
use App\Models\Client;
use App\Models\DocumentEvidence;
use App\Models\Firm;
use App\Models\Obligation;
use App\Models\User;
use App\Models\WorkItem;
use App\Tenancy\TenantStorage;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use LogicException;
use Tests\Concerns\BuildsFirmTenancy;
use Tests\Fixtures\Documents\FixedMalwareScanner;
use Tests\TestCase;

final class DocumentEvidenceTest extends TestCase
{
    use BuildsFirmTenancy;
    use RefreshDatabase;

    public function test_clean_document_is_retained_downloadable_and_audited(): void
    {
        $fixture = $this->fixture();
        $this->scanner(MalwareScanVerdict::Clean);
        $evidence = $this->store($fixture);

        $this->assertSame(MalwareScanVerdict::Clean, $evidence->latestScan()?->verdict);
        $this->assertTrue(app(TenantStorage::class)->exists($evidence->logical_path));

        $response = $this->actingAs($fixture['manager'])
            ->get(route('documents.download', $evidence));

        $response->assertOk()
            ->assertDownload('synthetic-evidence.pdf')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('%PDF-1.4', $response->streamedContent());
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'document_evidence.downloaded',
            'auditable_id' => $evidence->id,
        ]);
    }

    public function test_unconfigured_scanner_quarantines_document_and_blocks_download(): void
    {
        $fixture = $this->fixture();
        $evidence = $this->store($fixture);

        $this->assertSame(MalwareScanVerdict::Unavailable, $evidence->latestScan()?->verdict);
        $this->assertTrue(app(TenantStorage::class)->exists($evidence->logical_path));

        $this->actingAs($fixture['manager'])
            ->get(route('documents.download', $evidence))
            ->assertStatus(423);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'document_evidence.downloaded']);
    }

    public function test_infected_document_payload_is_removed_and_never_downloadable(): void
    {
        $fixture = $this->fixture();
        $this->scanner(MalwareScanVerdict::Infected);
        $evidence = $this->store($fixture);

        $this->assertSame(MalwareScanVerdict::Infected, $evidence->latestScan()?->verdict);
        $this->assertFalse(app(TenantStorage::class)->exists($evidence->logical_path));

        $this->actingAs($fixture['manager'])
            ->get(route('documents.download', $evidence))
            ->assertStatus(423);
    }

    public function test_extension_and_detected_mime_type_must_agree(): void
    {
        $fixture = $this->fixture();
        $path = tempnam(sys_get_temp_dir(), 'document-evidence-');
        $this->assertNotFalse($path);
        file_put_contents($path, "%PDF-1.4\nSynthetic");
        $file = new UploadedFile($path, 'synthetic-evidence.jpg', 'application/pdf', null, true);

        $this->expectException(ValidationException::class);
        app(StoreDocumentEvidence::class)->handle(
            $fixture['manager'],
            $fixture['workItem'],
            DocumentPurpose::SourceDocument,
            $file,
        );
    }

    public function test_foreign_firm_cannot_attach_evidence_to_work(): void
    {
        $fixture = $this->fixture();
        $otherFirm = Firm::factory()->create();
        $otherManager = User::factory()->create();
        $otherMembership = $this->createFirmMembership($otherFirm, $otherManager, FirmRole::Manager);
        $this->activateFirmMembership($otherMembership);

        $this->expectException(AuthorizationException::class);
        app(StoreDocumentEvidence::class)->handle(
            $otherManager,
            $fixture['workItem'],
            DocumentPurpose::SourceDocument,
            $this->pdf(),
        );
    }

    public function test_unassigned_preparer_cannot_attach_or_download_evidence(): void
    {
        $fixture = $this->fixture();
        $this->scanner(MalwareScanVerdict::Clean);
        $evidence = $this->store($fixture);
        $preparer = User::factory()->create();
        $membership = $this->createFirmMembership($fixture['firm'], $preparer, FirmRole::Preparer);
        $this->activateFirmMembership($membership);

        try {
            app(StoreDocumentEvidence::class)->handle(
                $preparer,
                $fixture['workItem'],
                DocumentPurpose::SourceDocument,
                $this->pdf(),
            );
            $this->fail('An unassigned preparer must not add evidence.');
        } catch (AuthorizationException) {
            // Expected.
        }

        $this->actingAs($preparer)
            ->get(route('documents.download', $evidence))
            ->assertForbidden();
    }

    public function test_foreign_firm_cannot_resolve_document_download(): void
    {
        $fixture = $this->fixture();
        $this->scanner(MalwareScanVerdict::Clean);
        $evidence = $this->store($fixture);
        $otherFirm = Firm::factory()->create();
        $otherManager = User::factory()->create();
        $this->createFirmMembership($otherFirm, $otherManager, FirmRole::Manager);

        $this->actingAs($otherManager)
            ->get(route('documents.download', $evidence))
            ->assertNotFound();
    }

    public function test_document_and_scan_history_reject_model_and_raw_mutation(): void
    {
        $fixture = $this->fixture();
        $evidence = $this->store($fixture);
        $scan = $evidence->scanEvents->sole();

        try {
            $evidence->update(['original_name' => 'overwritten.pdf']);
            $this->fail('Document evidence mutation must fail.');
        } catch (LogicException) {
            // Expected model-level guard.
        }

        try {
            DB::table('document_scan_events')->where('id', $scan->id)->delete();
            $this->fail('Raw scan-history deletion must fail.');
        } catch (QueryException) {
            // Expected database-level guard.
        }

        $this->assertDatabaseHas('document_scan_events', ['id' => $scan->id]);
    }

    public function test_integrity_mismatch_blocks_download_without_audit(): void
    {
        $fixture = $this->fixture();
        $this->scanner(MalwareScanVerdict::Clean);
        $evidence = $this->store($fixture);
        app(TenantStorage::class)->put($evidence->logical_path, 'altered');

        $this->actingAs($fixture['manager'])
            ->get(route('documents.download', $evidence))
            ->assertConflict();
        $this->assertDatabaseMissing('audit_logs', ['action' => 'document_evidence.downloaded']);
    }

    public function test_manager_uploads_document_through_work_register_and_sees_quarantine_state(): void
    {
        $fixture = $this->fixture();

        Livewire::actingAs($fixture['manager'])
            ->test(WorkRegister::class)
            ->call('openEvidence', $fixture['workItem']->id)
            ->assertSet('showEvidenceModal', true)
            ->set('evidencePurpose', DocumentPurpose::ReviewEvidence->value)
            ->set('documentUpload', $this->pdf())
            ->call('saveEvidence')
            ->assertHasNoErrors()
            ->assertSet('showEvidenceModal', false)
            ->assertSee('synthetic-evidence.pdf')
            ->assertSee('Quarantined');
    }

    /**
     * @return array{firm: Firm, manager: User, workItem: WorkItem}
     */
    private function fixture(): array
    {
        config([
            'platform.features.compliance_operations.enabled' => true,
            'platform.features.compliance_operations.firm_ids' => [],
        ]);
        $firm = Firm::factory()->create();
        $manager = User::factory()->create();
        $membership = $this->createFirmMembership($firm, $manager, FirmRole::Manager);
        $this->activateFirmMembership($membership);
        $client = Client::factory()->createForFirm($firm, ['created_by' => $manager->id]);
        $obligation = Obligation::factory()->createForFirm($firm, $client, [
            'created_by' => $manager->id,
            'verified_by' => $manager->id,
        ]);
        $workItem = WorkItem::factory()->createForFirm($firm, $obligation, [
            'created_by' => $manager->id,
        ]);

        return compact('firm', 'manager', 'workItem');
    }

    /**
     * @param  array{manager: User, workItem: WorkItem}  $fixture
     */
    private function store(array $fixture): DocumentEvidence
    {
        return app(StoreDocumentEvidence::class)->handle(
            $fixture['manager'],
            $fixture['workItem'],
            DocumentPurpose::SourceDocument,
            $this->pdf(),
        );
    }

    private function pdf(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            'synthetic-evidence.pdf',
            "%PDF-1.4\nSynthetic evidence only.\n%%EOF",
        );
    }

    private function scanner(MalwareScanVerdict $verdict): void
    {
        $this->app->instance(MalwareScanner::class, new FixedMalwareScanner($verdict));
    }
}
