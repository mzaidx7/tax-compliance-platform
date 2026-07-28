<?php

declare(strict_types=1);

namespace Tests\Feature\Clients;

use App\Actions\Clients\AddClientContact;
use App\Actions\Clients\AddClientServiceEnrollment;
use App\Actions\Clients\TransitionClientServiceEnrollment;
use App\Actions\Clients\TransitionClientStatus;
use App\Enums\ClientContactPurpose;
use App\Enums\ClientService;
use App\Enums\ClientStatus;
use App\Enums\FirmRole;
use App\Enums\PreferredContactChannel;
use App\Enums\ServiceEnrollmentStatus;
use App\Livewire\Clients\Index;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\ClientServiceEnrollment;
use App\Models\Firm;
use App\Models\FirmMembership;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Concerns\BuildsFirmTenancy;
use Tests\TestCase;

final class ClientLifecycleTest extends TestCase
{
    use BuildsFirmTenancy;
    use RefreshDatabase;

    public function test_administrator_adds_contact_without_personal_details_in_audit(): void
    {
        $fixture = $this->fixture();
        $contact = app(AddClientContact::class)->handle(
            $fixture['admin'],
            $fixture['client'],
            'Synthetic Contact',
            'Tax lead',
            ClientContactPurpose::Tax,
            PreferredContactChannel::Email,
            'synthetic.contact@example.test',
            null,
        );

        $this->assertSame('synthetic.contact@example.test', $contact->email);
        $audit = AuditLog::query()->where('action', 'client.contact_added')->sole();
        $encoded = json_encode($audit->after_values, JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('synthetic.contact@example.test', $encoded);
        $this->assertStringNotContainsString('Synthetic Contact', $encoded);
    }

    public function test_preferred_channel_requires_matching_contact_detail(): void
    {
        $fixture = $this->fixture();
        $this->expectException(ValidationException::class);

        app(AddClientContact::class)->handle(
            $fixture['admin'],
            $fixture['client'],
            'Synthetic Missing Channel',
            null,
            ClientContactPurpose::Primary,
            PreferredContactChannel::WhatsApp,
            'synthetic@example.test',
            null,
        );
    }

    public function test_client_and_service_status_changes_are_explicit_and_retained(): void
    {
        $fixture = $this->fixture();
        $clientChange = app(TransitionClientStatus::class)->handle(
            $fixture['admin'],
            $fixture['client'],
            ClientStatus::Inactive,
            'Synthetic engagement pause.',
        );
        $enrollment = $this->enrollment($fixture);
        $serviceChange = app(TransitionClientServiceEnrollment::class)->handle(
            $fixture['admin'],
            $enrollment,
            ServiceEnrollmentStatus::Paused,
            '2026-02-01',
            'Synthetic service pause.',
        );

        $this->assertSame(ClientStatus::Inactive, $fixture['client']->refresh()->status);
        $this->assertSame(ClientStatus::Active, $clientChange->previous_status);
        $this->assertSame(ServiceEnrollmentStatus::Paused, $enrollment->refresh()->status);
        $this->assertSame('2026-02-01', $serviceChange->effective_on->toDateString());
        $this->assertDatabaseHas('audit_logs', ['action' => 'client.status_changed']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'client.service_status_changed']);
    }

    public function test_ended_service_is_terminal(): void
    {
        $fixture = $this->fixture();
        $enrollment = $this->enrollment($fixture);
        $action = app(TransitionClientServiceEnrollment::class);
        $action->handle(
            $fixture['admin'],
            $enrollment,
            ServiceEnrollmentStatus::Ended,
            '2026-02-01',
            'Synthetic engagement ended.',
        );

        $this->expectException(ValidationException::class);
        $action->handle(
            $fixture['admin'],
            $enrollment->refresh(),
            ServiceEnrollmentStatus::Active,
            '2026-02-02',
            'Synthetic invalid restart.',
        );
    }

    public function test_foreign_firm_cannot_add_contact_or_transition_service(): void
    {
        $fixture = $this->fixture();
        $enrollment = $this->enrollment($fixture);
        $otherFirm = Firm::factory()->create();
        $otherAdmin = User::factory()->create();
        $otherMembership = $this->createFirmMembership($otherFirm, $otherAdmin, FirmRole::FirmAdministrator);
        $this->activateFirmMembership($otherMembership);

        try {
            app(AddClientContact::class)->handle(
                $otherAdmin,
                $fixture['client'],
                'Synthetic Foreign Contact',
                null,
                ClientContactPurpose::Primary,
                PreferredContactChannel::Email,
                'foreign@example.test',
                null,
            );
            $this->fail('A foreign firm must not add a client contact.');
        } catch (AuthorizationException) {
            // Expected.
        }

        $this->expectException(AuthorizationException::class);
        app(TransitionClientServiceEnrollment::class)->handle(
            $otherAdmin,
            $enrollment,
            ServiceEnrollmentStatus::Paused,
            '2026-02-01',
            'Synthetic foreign transition.',
        );
    }

    public function test_lifecycle_history_rejects_raw_database_mutation(): void
    {
        $fixture = $this->fixture();
        $change = app(TransitionClientStatus::class)->handle(
            $fixture['admin'],
            $fixture['client'],
            ClientStatus::Inactive,
            'Synthetic immutable history.',
        );

        $this->expectException(QueryException::class);
        DB::table('client_status_changes')->where('id', $change->id)->delete();
    }

    public function test_service_lifecycle_history_rejects_raw_database_mutation(): void
    {
        $fixture = $this->fixture();
        $change = app(TransitionClientServiceEnrollment::class)->handle(
            $fixture['admin'],
            $this->enrollment($fixture),
            ServiceEnrollmentStatus::Paused,
            '2026-02-01',
            'Synthetic immutable service history.',
        );

        $this->expectException(QueryException::class);
        DB::table('client_service_enrollment_status_changes')
            ->where('id', $change->id)
            ->update(['reason' => 'Attempted rewrite']);
    }

    public function test_administrator_uses_client_profile_for_contacts_and_lifecycle(): void
    {
        $fixture = $this->fixture();

        Livewire::actingAs($fixture['admin'])
            ->test(Index::class)
            ->call('openProfile', $fixture['client']->id)
            ->set('contactName', 'Synthetic UI Contact')
            ->set('contactPurpose', ClientContactPurpose::Primary->value)
            ->set('contactPreferredChannel', PreferredContactChannel::Email->value)
            ->set('contactEmail', 'ui.contact@example.test')
            ->call('addContact')
            ->assertHasNoErrors()
            ->assertSee('Synthetic UI Contact')
            ->set('clientStatus', ClientStatus::Inactive->value)
            ->set('clientStatusReason', 'Synthetic UI lifecycle change.')
            ->call('transitionClient')
            ->assertHasNoErrors()
            ->assertSee('Inactive');
    }

    /** @param array{admin: User, client: Client, managerMembership: FirmMembership} $fixture */
    private function enrollment(array $fixture): ClientServiceEnrollment
    {
        return app(AddClientServiceEnrollment::class)->handle(
            $fixture['admin'],
            $fixture['client'],
            ClientService::VatCompliance,
            '2026-01-01',
            null,
            $fixture['managerMembership']->id,
        );
    }

    /**
     * @return array{admin: User, client: Client, managerMembership: FirmMembership}
     */
    private function fixture(): array
    {
        config([
            'platform.features.client_master.enabled' => true,
            'platform.features.client_master.firm_ids' => [],
        ]);
        $firm = Firm::factory()->create();
        $admin = User::factory()->create();
        $manager = User::factory()->create();
        $adminMembership = $this->createFirmMembership($firm, $admin, FirmRole::FirmAdministrator);
        $managerMembership = $this->createFirmMembership($firm, $manager, FirmRole::Manager);
        $this->activateFirmMembership($adminMembership);
        $client = Client::factory()->createForFirm($firm, ['created_by' => $admin->id]);

        return compact('admin', 'client', 'managerMembership');
    }
}
