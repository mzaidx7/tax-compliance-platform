<?php

declare(strict_types=1);

namespace Tests\Feature\Clients;

use App\Actions\Clients\SetClientReminderPreferences;
use App\Enums\ClientReminderMode;
use App\Enums\FirmRole;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Firm;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\BuildsFirmTenancy;
use Tests\TestCase;

final class ClientReminderPreferencesTest extends TestCase
{
    use BuildsFirmTenancy;
    use RefreshDatabase;

    public function test_preferences_default_to_review(): void
    {
        [$administrator, $firm] = $this->administratorContext();
        $client = Client::factory()->createForFirm($firm, ['created_by' => $administrator->id]);

        self::assertSame(ClientReminderMode::Review, $client->document_reminder_mode);
        self::assertSame(ClientReminderMode::Review, $client->vat_reminder_mode);
        self::assertSame(ClientReminderMode::Review, $client->corporate_tax_reminder_mode);
    }

    public function test_administrator_confirms_automatic_reminders_with_primary_email(): void
    {
        [$administrator, $firm] = $this->administratorContext();
        $client = Client::factory()->createForFirm($firm, [
            'primary_email' => 'client@example.test',
            'created_by' => $administrator->id,
        ]);

        $updated = app(SetClientReminderPreferences::class)->handle(
            $administrator,
            $client,
            ClientReminderMode::Automatic,
            ClientReminderMode::Review,
            ClientReminderMode::Off,
            true,
        );

        self::assertSame(ClientReminderMode::Automatic, $updated->document_reminder_mode);
        self::assertNotNull($updated->automatic_reminders_confirmed_at);
        self::assertSame($administrator->id, $updated->automatic_reminders_confirmed_by);
        self::assertSame(
            'client.reminder_preferences_changed',
            AuditLog::query()->sole()->action,
        );
    }

    public function test_automatic_reminders_require_email_and_confirmation(): void
    {
        [$administrator, $firm] = $this->administratorContext();
        $client = Client::factory()->createForFirm($firm, ['created_by' => $administrator->id]);

        try {
            app(SetClientReminderPreferences::class)->handle(
                $administrator,
                $client,
                ClientReminderMode::Automatic,
                ClientReminderMode::Review,
                ClientReminderMode::Review,
                true,
            );
            self::fail('Automatic reminders should require a primary email.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('primary_email', $exception->errors());
        }

        $client->forceFill(['primary_email' => 'client@example.test'])->save();

        $this->expectException(ValidationException::class);
        app(SetClientReminderPreferences::class)->handle(
            $administrator,
            $client,
            ClientReminderMode::Automatic,
            ClientReminderMode::Review,
            ClientReminderMode::Review,
        );
    }

    public function test_non_administrator_and_foreign_firm_are_rejected(): void
    {
        [$administrator, $firm] = $this->administratorContext();
        $client = Client::factory()->createForFirm($firm, ['created_by' => $administrator->id]);
        $manager = User::factory()->create();
        $membership = $this->createFirmMembership($firm, $manager, FirmRole::Manager);
        $this->activateFirmMembership($membership);

        try {
            app(SetClientReminderPreferences::class)->handle(
                $manager,
                $client,
                ClientReminderMode::Off,
                ClientReminderMode::Off,
                ClientReminderMode::Off,
            );
            self::fail('A manager should not change reminder preferences.');
        } catch (AuthorizationException) {
            self::assertTrue(true);
        }

        $otherFirm = Firm::factory()->create();
        $otherAdministrator = User::factory()->create();
        $otherMembership = $this->createFirmMembership($otherFirm, $otherAdministrator, FirmRole::FirmAdministrator);
        $this->activateFirmMembership($otherMembership);

        $this->expectException(AuthorizationException::class);
        app(SetClientReminderPreferences::class)->handle(
            $otherAdministrator,
            $client,
            ClientReminderMode::Off,
            ClientReminderMode::Off,
            ClientReminderMode::Off,
        );
    }

    /** @return array{User, Firm} */
    private function administratorContext(): array
    {
        $administrator = User::factory()->create();
        $firm = Firm::factory()->create();
        $membership = $this->createFirmMembership($firm, $administrator, FirmRole::FirmAdministrator);
        $this->activateFirmMembership($membership);

        return [$administrator, $firm];
    }
}
