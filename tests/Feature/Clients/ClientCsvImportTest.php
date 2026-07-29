<?php

declare(strict_types=1);

namespace Tests\Feature\Clients;

use App\Actions\Clients\PreviewClientCsvImport;
use App\Actions\Exports\ExportClientMasterData;
use App\Enums\FirmRole;
use App\Livewire\Clients\Index;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Firm;
use App\Models\FirmMembership;
use App\Models\Obligation;
use App\Models\TaxPeriod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\Concerns\BuildsFirmTenancy;
use Tests\TestCase;

final class ClientCsvImportTest extends TestCase
{
    use BuildsFirmTenancy;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'platform.features.client_master.enabled' => true,
            'platform.features.client_master.firm_ids' => [],
        ]);
    }

    public function test_preview_reconciles_valid_invalid_duplicate_and_existing_rows(): void
    {
        [$administrator, $firm] = $this->administratorContext();
        Client::factory()->createForFirm($firm, [
            'internal_code' => 'EXISTING-01',
            'internal_code_normalized' => 'EXISTING-01',
            'created_by' => $administrator->id,
        ]);
        $file = UploadedFile::fake()->createWithContent('clients.csv', implode("\n", [
            'internal_code,legal_name,trade_name,entity_type',
            'NEW-001,Synthetic First LLC,,Limited liability company',
            'NEW-001,Synthetic Duplicate LLC,,',
            'EXISTING-01,Synthetic Existing LLC,,',
            ',Missing Code LLC,,',
            'NEW-002,Synthetic Second LLC,Synthetic Second,Free zone company',
        ]));

        $preview = app(PreviewClientCsvImport::class)->handle($administrator, $file);

        $this->assertSame(2, $preview['accepted']);
        $this->assertSame(3, $preview['rejected']);
        $this->assertCount(5, $preview['rows']);
        $this->assertSame('Internal code is duplicated in this file.', $preview['rows'][1]['errors'][0]);
        $this->assertSame('Internal code already exists in this firm.', $preview['rows'][2]['errors'][0]);
        $this->assertSame('Internal code is required.', $preview['rows'][3]['errors'][0]);
    }

    public function test_administrator_previews_and_atomically_commits_200_clients(): void
    {
        [$administrator] = $this->administratorContext();
        $lines = ['internal_code,legal_name,trade_name,entity_type'];
        for ($number = 1; $number <= 200; $number++) {
            $lines[] = sprintf('IMP-%04d,Synthetic Imported Client %d LLC,,Limited liability company', $number, $number);
        }
        $file = UploadedFile::fake()->createWithContent('clients.csv', implode("\n", $lines));

        Livewire::actingAs($administrator)
            ->test(Index::class)
            ->set('clientImportFile', $file)
            ->call('previewClientImport')
            ->assertSet('clientImportAccepted', 200)
            ->assertSet('clientImportRejected', 0)
            ->call('commitClientImport')
            ->assertHasNoErrors()
            ->assertSet('showImportModal', false);

        $this->assertDatabaseCount('clients', 200);
        $this->assertDatabaseCount('audit_logs', 201);
        $this->assertSame(1, AuditLog::query()->where('action', 'client.csv_import_committed')->count());
    }

    public function test_rejected_preview_cannot_be_committed_and_other_firm_codes_do_not_conflict(): void
    {
        [$administrator] = $this->administratorContext();
        $otherFirm = Firm::factory()->create();
        $otherUser = User::factory()->create();
        $otherMembership = $this->createFirmMembership($otherFirm, $otherUser, FirmRole::FirmAdministrator);
        $this->activateFirmMembership($otherMembership);
        Client::factory()->createForFirm($otherFirm, [
            'internal_code' => 'SHARED-01',
            'internal_code_normalized' => 'SHARED-01',
            'created_by' => $otherUser->id,
        ]);
        $this->administratorContext($administrator);
        $file = UploadedFile::fake()->createWithContent('clients.csv', implode("\n", [
            'internal_code,legal_name',
            'SHARED-01,Synthetic Same Code Different Firm LLC',
            ',Synthetic Invalid LLC',
        ]));

        Livewire::actingAs($administrator)
            ->test(Index::class)
            ->set('clientImportFile', $file)
            ->call('previewClientImport')
            ->assertSet('clientImportAccepted', 1)
            ->assertSet('clientImportRejected', 1)
            ->call('commitClientImport')
            ->assertHasErrors('clientImportFile');
    }

    public function test_master_import_stores_sensitive_details_and_generates_tax_schedule(): void
    {
        [$administrator] = $this->administratorContext();
        $file = UploadedFile::fake()->createWithContent('client-master.csv', implode("\n", [
            'internal_code,legal_name,email,mobile,vat_trn,ct_trn,vat_frequency,vat_period_start,vat_period_end,ct_period_start,ct_period_end,trade_license_number,trade_license_authority,trade_license_expiry_date,passport_number,passport_expiry_date,emirates_id_number,emirates_id_expiry_date',
            'MASTER-001,Synthetic Master LLC,accounts@example.test,+971501234567,100000000000003,100000000000004,quarterly,2026-09-01,2026-11-30,2025-01-01,2025-12-31,LIC-1234,DED,2027-12-31,P1234567,2030-01-01,784-1990-1234567-1,2029-01-01',
        ]));

        Livewire::actingAs($administrator)
            ->test(Index::class)
            ->set('clientImportFile', $file)
            ->call('previewClientImport')
            ->assertSet('clientImportAccepted', 1)
            ->call('commitClientImport')
            ->assertHasNoErrors();

        $client = Client::query()->where('internal_code', 'MASTER-001')->firstOrFail();
        self::assertSame('P1234567', $client->passport_number);
        self::assertSame('784-1990-1234567-1', $client->emirates_id_number);
        self::assertSame('LIC-1234', $client->trade_license_number);
        self::assertSame('100000000000003', $client->vat_trn);
        self::assertSame('100000000000004', $client->corporate_tax_trn);
        self::assertSame(8, TaxPeriod::query()->whereHas('registration', fn ($query) => $query->where('client_id', $client->id))->count());
        self::assertSame(8, Obligation::query()->where('client_id', $client->id)->count());
        $this->assertDatabaseMissing('clients', ['passport_number' => 'P1234567']);
        $this->assertDatabaseMissing('clients', ['emirates_id_number' => '784-1990-1234567-1']);
    }

    public function test_administrator_can_download_an_audited_client_master_export(): void
    {
        [$administrator, $firm] = $this->administratorContext();
        $client = Client::factory()->createForFirm($firm, [
            'internal_code' => 'EXPORT-001',
            'internal_code_normalized' => 'EXPORT-001',
            'passport_number' => 'P1234567',
            'created_by' => $administrator->id,
        ]);

        $this->activateFirmMembership($this->firmMembershipFor($administrator, $firm));
        $artifact = app(ExportClientMasterData::class)->handle($administrator);

        $response = $this->actingAs($administrator)->get(route('exports.download', ['exportAuditLog' => $artifact->auditLogId]));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('P1234567', (string) $response->streamedContent());
    }

    /**
     * @return array{User, Firm}
     */
    private function administratorContext(?User $administrator = null): array
    {
        $administrator ??= User::factory()->create();
        $firm = Firm::factory()->create();
        $membership = $this->createFirmMembership($firm, $administrator, FirmRole::FirmAdministrator);
        $this->activateFirmMembership($membership);

        return [$administrator, $firm];
    }

    private function firmMembershipFor(User $user, Firm $firm): FirmMembership
    {
        return FirmMembership::query()
            ->where('user_id', $user->id)
            ->where('firm_id', $firm->id)
            ->firstOrFail();
    }
}
