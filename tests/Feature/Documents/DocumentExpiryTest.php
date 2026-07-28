<?php

declare(strict_types=1);

namespace Tests\Feature\Documents;

use App\Actions\Documents\GenerateDocumentExpiryReminders;
use App\Actions\Documents\PublishDocumentTypeVersion;
use App\Actions\Documents\RecordClientDocumentMetadata;
use App\Enums\ExpiryReminderKind;
use App\Enums\FirmRole;
use App\Jobs\RecordFirmScheduledWorkHeartbeat;
use App\Livewire\Documents\Index;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\ClientDocument;
use App\Models\DocumentExpiryReminder;
use App\Models\DocumentTypeVersion;
use App\Models\Firm;
use App\Models\FirmMembership;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Date;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Concerns\BuildsFirmTenancy;
use Tests\TestCase;

final class DocumentExpiryTest extends TestCase
{
    use BuildsFirmTenancy;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Date::setTestNow();
        parent::tearDown();
    }

    public function test_administrator_publishes_immutable_versioned_document_types(): void
    {
        $fixture = $this->fixture();
        $action = app(PublishDocumentTypeVersion::class);
        $first = $action->handle(
            $fixture['admin'],
            'Trade Licence',
            'Trade licence',
            true,
            [30, 90, 30, 7],
            7,
        );
        $second = $action->handle(
            $fixture['admin'],
            'trade_licence',
            'Trade licence',
            true,
            [60, 30, 7],
            14,
        );

        $this->assertSame('trade_licence', $first->key);
        $this->assertSame([90, 30, 7], $first->reminder_days);
        $this->assertSame(1, $first->version);
        $this->assertSame(2, $second->version);
        $this->assertDatabaseHas('audit_logs', ['action' => 'document_type.published']);
    }

    public function test_expiry_required_type_rejects_missing_expiry(): void
    {
        $fixture = $this->fixture();
        $type = $this->type($fixture);
        $this->expectException(ValidationException::class);

        app(RecordClientDocumentMetadata::class)->handle(
            $fixture['admin'],
            $fixture['client'],
            $type,
            'Synthetic missing expiry',
            '2026-01-01',
            null,
        );
    }

    public function test_recorded_metadata_omits_reference_and_dates_from_audit(): void
    {
        $fixture = $this->fixture();
        $document = $this->document($fixture, '2026-09-01');
        $audit = AuditLog::query()->where('action', 'client.document_metadata_recorded')->sole();
        $encoded = json_encode($audit->after_values, JSON_THROW_ON_ERROR);

        $this->assertSame('Synthetic reference', $document->reference_label);
        $this->assertStringNotContainsString('Synthetic reference', $encoded);
        $this->assertStringNotContainsString('2026-09-01', $encoded);
    }

    public function test_renewal_preserves_predecessor_and_marks_only_successor_current(): void
    {
        $fixture = $this->fixture();
        $type = $this->type($fixture);
        $first = $this->document($fixture, '2026-08-01', $type);
        $nextType = app(PublishDocumentTypeVersion::class)->handle(
            $fixture['admin'],
            'trade_licence',
            'Trade licence',
            true,
            [90, 30, 7, 1],
            7,
        );
        $renewal = app(RecordClientDocumentMetadata::class)->handle(
            $fixture['admin'],
            $fixture['client'],
            $nextType,
            'Synthetic renewal',
            '2026-07-15',
            '2027-08-01',
            $first,
        );

        $this->assertSame($first->id, $renewal->supersedes_client_document_id);
        $this->assertSame($renewal->id, $first->successor()->sole()->id);
        $this->assertSame(1, ClientDocument::query()->whereDoesntHave('successor')->count());
    }

    public function test_generator_creates_upcoming_expiry_and_repeated_overdue_reminders_idempotently(): void
    {
        $fixture = $this->fixture();
        $this->document($fixture, '2026-08-27');
        $generator = app(GenerateDocumentExpiryReminders::class);

        $this->assertSame(1, $generator->handle(CarbonImmutable::parse('2026-07-28')));
        $this->assertSame(0, $generator->handle(CarbonImmutable::parse('2026-07-28')));
        $this->assertDatabaseHas('document_expiry_reminders', [
            'kind' => ExpiryReminderKind::Upcoming->value,
            'days_from_expiry' => 30,
        ]);

        $this->assertSame(1, $generator->handle(CarbonImmutable::parse('2026-08-27')));
        $this->assertDatabaseHas('document_expiry_reminders', [
            'kind' => ExpiryReminderKind::ExpiryDay->value,
            'days_from_expiry' => 0,
        ]);

        $this->assertSame(1, $generator->handle(CarbonImmutable::parse('2026-09-03')));
        $this->assertDatabaseHas('document_expiry_reminders', [
            'kind' => ExpiryReminderKind::OverdueEscalation->value,
            'days_from_expiry' => -7,
        ]);
    }

    public function test_firm_scheduled_work_generates_expiry_reminders_in_firm_context(): void
    {
        $fixture = $this->fixture(['timezone' => 'Asia/Dubai']);
        $this->document($fixture, '2026-08-28');
        Date::setTestNow('2026-07-28 20:05:00 UTC');

        Bus::dispatchSync(new RecordFirmScheduledWorkHeartbeat(
            $fixture['firm']->id,
            CarbonImmutable::parse('2026-07-28 20:05:00 UTC'),
        ));

        $this->assertSame(
            1,
            DocumentExpiryReminder::query()
                ->whereDate('scheduled_for', '2026-07-29')
                ->where('days_from_expiry', 30)
                ->count(),
        );
    }

    public function test_foreign_firm_cannot_record_document_metadata(): void
    {
        $fixture = $this->fixture();
        $type = $this->type($fixture);
        $otherFirm = Firm::factory()->create();
        $otherAdmin = User::factory()->create();
        $otherMembership = $this->createFirmMembership($otherFirm, $otherAdmin, FirmRole::FirmAdministrator);
        $this->activateFirmMembership($otherMembership);
        $this->expectException(AuthorizationException::class);

        app(RecordClientDocumentMetadata::class)->handle(
            $otherAdmin,
            $fixture['client'],
            $type,
            null,
            null,
            '2026-12-31',
        );
    }

    public function test_raw_database_mutation_of_document_metadata_is_rejected(): void
    {
        $fixture = $this->fixture();
        $document = $this->document($fixture, '2026-09-01');
        $this->expectException(QueryException::class);

        ClientDocument::withoutGlobalScopes()
            ->where('id', $document->id)
            ->update(['expires_on' => '2027-09-01']);
    }

    public function test_administrator_uses_document_expiry_register_without_file_controls(): void
    {
        $fixture = $this->fixture();

        Livewire::actingAs($fixture['admin'])
            ->test(Index::class)
            ->set('typeKey', 'trade_licence')
            ->set('typeName', 'Trade licence')
            ->set('reminderDays', '90, 30, 7, 1')
            ->call('publishType')
            ->assertHasNoErrors()
            ->set('clientId', $fixture['client']->id)
            ->set('issuedOn', '2026-01-01')
            ->set('expiresOn', '2026-12-31')
            ->call('recordDocument')
            ->assertHasNoErrors()
            ->assertSee('Trade licence')
            ->assertDontSee('Upload file');
    }

    /**
     * @param  array{admin: User, client: Client}  $fixture
     */
    private function type(array $fixture): DocumentTypeVersion
    {
        return app(PublishDocumentTypeVersion::class)->handle(
            $fixture['admin'],
            'trade_licence',
            'Trade licence',
            true,
            [90, 30, 7, 1],
            7,
        );
    }

    /**
     * @param  array{admin: User, client: Client}  $fixture
     */
    private function document(
        array $fixture,
        string $expiresOn,
        ?DocumentTypeVersion $type = null,
    ): ClientDocument {
        return app(RecordClientDocumentMetadata::class)->handle(
            $fixture['admin'],
            $fixture['client'],
            $type ?? $this->type($fixture),
            'Synthetic reference',
            '2026-01-01',
            $expiresOn,
        );
    }

    /**
     * @param  array<string, mixed>  $firmAttributes
     * @return array{firm: Firm, admin: User, membership: FirmMembership, client: Client}
     */
    private function fixture(array $firmAttributes = []): array
    {
        config([
            'platform.features.client_master.enabled' => true,
            'platform.features.client_master.firm_ids' => [],
        ]);
        $firm = Firm::factory()->create($firmAttributes);
        $admin = User::factory()->create();
        $membership = $this->createFirmMembership($firm, $admin, FirmRole::FirmAdministrator);
        $this->activateFirmMembership($membership);
        $client = Client::factory()->createForFirm($firm, ['created_by' => $admin->id]);

        return compact('firm', 'admin', 'membership', 'client');
    }
}
