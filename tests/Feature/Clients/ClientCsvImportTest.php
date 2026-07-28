<?php

declare(strict_types=1);

namespace Tests\Feature\Clients;

use App\Actions\Clients\PreviewClientCsvImport;
use App\Enums\FirmRole;
use App\Livewire\Clients\Index;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Firm;
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
}
