<?php

declare(strict_types=1);

namespace Tests\Feature\Clients;

use App\Actions\Clients\AddClientServiceEnrollment;
use App\Actions\Clients\AddTaxPeriod;
use App\Actions\Clients\AddTaxRegistration;
use App\Enums\ClientService;
use App\Enums\FirmRole;
use App\Enums\TaxRegistrationStatus;
use App\Enums\TaxType;
use App\Livewire\Clients\Index;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Firm;
use App\Models\FirmMembership;
use App\Models\TaxRegistration;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Concerns\BuildsFirmTenancy;
use Tests\TestCase;

final class ClientProfileTest extends TestCase
{
    use BuildsFirmTenancy;
    use RefreshDatabase;

    public function test_administrator_records_service_owner_registration_and_actual_period(): void
    {
        $fixture = $this->fixture();

        $enrollment = app(AddClientServiceEnrollment::class)->handle(
            $fixture['admin'],
            $fixture['client'],
            ClientService::VatCompliance,
            '2026-01-01',
            null,
            $fixture['managerMembership']->id,
        );
        $registration = $this->registration($fixture);
        $period = app(AddTaxPeriod::class)->handle(
            $fixture['admin'],
            $registration,
            'Synthetic January 2026',
            '2026-01-01',
            '2026-01-31',
        );

        $this->assertSame($fixture['managerMembership']->id, $enrollment->responsible_membership_id);
        $this->assertSame('SYNTHETIC-TRN-001', $registration->registration_number_normalized);
        $this->assertSame('Synthetic January 2026', $period->label);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'client.service_enrolled',
            'auditable_id' => $fixture['client']->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'client.tax_registration_added',
            'auditable_id' => $fixture['client']->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'client.tax_period_added',
            'auditable_id' => $fixture['client']->id,
        ]);
    }

    public function test_registration_identifier_is_not_copied_into_audit_metadata(): void
    {
        $fixture = $this->fixture();
        $this->registration($fixture);

        $audit = AuditLog::query()
            ->where('action', 'client.tax_registration_added')
            ->sole();

        $this->assertStringNotContainsString(
            'SYNTHETIC-TRN-001',
            json_encode($audit->after_values, JSON_THROW_ON_ERROR),
        );
    }

    public function test_duplicate_service_and_overlapping_period_are_rejected(): void
    {
        $fixture = $this->fixture();
        $action = app(AddClientServiceEnrollment::class);
        $action->handle(
            $fixture['admin'],
            $fixture['client'],
            ClientService::VatCompliance,
            '2026-01-01',
            null,
            $fixture['managerMembership']->id,
        );

        try {
            $action->handle(
                $fixture['admin'],
                $fixture['client'],
                ClientService::VatCompliance,
                '2026-02-01',
                null,
                $fixture['managerMembership']->id,
            );
            $this->fail('A duplicate service enrollment must fail.');
        } catch (QueryException) {
            // Expected database uniqueness boundary.
        }

        $registration = $this->registration($fixture);
        $periodAction = app(AddTaxPeriod::class);
        $periodAction->handle(
            $fixture['admin'],
            $registration,
            'Synthetic first period',
            '2026-01-01',
            '2026-03-31',
        );

        $this->expectException(ValidationException::class);
        $periodAction->handle(
            $fixture['admin'],
            $registration,
            'Synthetic overlap',
            '2026-03-01',
            '2026-05-31',
        );
    }

    public function test_foreign_firm_cannot_add_service_or_period(): void
    {
        $fixture = $this->fixture();
        $registration = $this->registration($fixture);
        $otherFirm = Firm::factory()->create();
        $otherAdmin = User::factory()->create();
        $otherMembership = $this->createFirmMembership($otherFirm, $otherAdmin, FirmRole::FirmAdministrator);
        $this->activateFirmMembership($otherMembership);

        try {
            app(AddClientServiceEnrollment::class)->handle(
                $otherAdmin,
                $fixture['client'],
                ClientService::Bookkeeping,
                '2026-01-01',
                null,
                $otherMembership->id,
            );
            $this->fail('Cross-firm service enrollment must fail.');
        } catch (AuthorizationException) {
            // Expected.
        }

        $this->expectException(AuthorizationException::class);
        app(AddTaxPeriod::class)->handle(
            $otherAdmin,
            $registration,
            'Synthetic foreign period',
            '2026-01-01',
            '2026-01-31',
        );
    }

    public function test_administrator_manages_service_registration_and_period_through_client_register(): void
    {
        $fixture = $this->fixture();

        Livewire::actingAs($fixture['admin'])
            ->test(Index::class)
            ->call('openProfile', $fixture['client']->id)
            ->assertSet('showProfileModal', true)
            ->set('service', ClientService::VatCompliance->value)
            ->set('serviceStartsOn', '2026-01-01')
            ->set('responsibleMembershipId', $fixture['managerMembership']->id)
            ->call('addService')
            ->assertHasNoErrors()
            ->assertSee('VAT compliance')
            ->set('taxType', TaxType::Vat->value)
            ->set('registrationNumber', 'SYNTHETIC-UI-TRN')
            ->set('registrationStatus', TaxRegistrationStatus::Active->value)
            ->set('registrationEffectiveFrom', '2026-01-01')
            ->call('addRegistration')
            ->assertHasNoErrors()
            ->assertSee('SYNTHETIC-UI-TRN')
            ->set('periodLabel', 'Synthetic UI period')
            ->set('periodStartsOn', '2026-01-01')
            ->set('periodEndsOn', '2026-03-31')
            ->call('addPeriod')
            ->assertHasNoErrors()
            ->assertSee('Synthetic UI period');
    }

    /**
     * @param  array{admin: User, client: Client}  $fixture
     */
    private function registration(array $fixture): TaxRegistration
    {
        return app(AddTaxRegistration::class)->handle(
            $fixture['admin'],
            $fixture['client'],
            TaxType::Vat,
            ' synthetic-trn-001 ',
            TaxRegistrationStatus::Active,
            '2026-01-01',
            null,
        );
    }

    /**
     * @return array{
     *   firm: Firm,
     *   admin: User,
     *   adminMembership: FirmMembership,
     *   managerMembership: FirmMembership,
     *   client: Client
     * }
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

        return compact('firm', 'admin', 'adminMembership', 'managerMembership', 'client');
    }
}
