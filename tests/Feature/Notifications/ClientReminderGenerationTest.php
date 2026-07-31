<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Actions\Clients\GenerateClientReminderRequests;
use App\Enums\ClientReminderMode;
use App\Enums\ClientReminderStatus;
use App\Enums\FirmRole;
use App\Jobs\SendClientReminderEmail;
use App\Models\Client;
use App\Models\ClientReminderRequest;
use App\Models\Firm;
use App\Models\Obligation;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\BuildsFirmTenancy;
use Tests\TestCase;

final class ClientReminderGenerationTest extends TestCase
{
    use BuildsFirmTenancy;
    use RefreshDatabase;

    public function test_vat_reminder_enters_review_queue_thirty_days_before_deadline(): void
    {
        Queue::fake();
        [$administrator, $firm] = $this->administratorContext();
        $client = Client::factory()->createForFirm($firm, [
            'primary_email' => 'client@example.test',
            'created_by' => $administrator->id,
        ]);
        Obligation::factory()->createForFirm($firm, $client, [
            'obligation_type' => 'VAT Return',
            'statutory_due_date' => '2026-08-29',
            'effective_due_date' => '2026-08-29',
            'created_by' => $administrator->id,
            'verified_by' => $administrator->id,
        ]);

        $action = app(GenerateClientReminderRequests::class);
        self::assertSame(1, $action->handle(CarbonImmutable::parse('2026-07-30')));
        self::assertSame(0, $action->handle(CarbonImmutable::parse('2026-07-30')));

        $request = ClientReminderRequest::query()->sole();
        self::assertSame(ClientReminderStatus::AwaitingReview, $request->status);
        self::assertSame(30, $request->days_before);
        Queue::assertNothingPushed();
    }

    public function test_automatic_corporate_tax_reminder_is_queued_240_days_before_deadline(): void
    {
        Queue::fake();
        [$administrator, $firm] = $this->administratorContext();
        $client = Client::factory()->createForFirm($firm, [
            'primary_email' => 'client@example.test',
            'corporate_tax_reminder_mode' => ClientReminderMode::Automatic,
            'automatic_reminders_confirmed_at' => now(),
            'automatic_reminders_confirmed_by' => $administrator->id,
            'created_by' => $administrator->id,
        ]);
        Obligation::factory()->createForFirm($firm, $client, [
            'obligation_type' => 'Corporate Tax Return',
            'statutory_due_date' => '2027-03-27',
            'effective_due_date' => '2027-03-27',
            'created_by' => $administrator->id,
            'verified_by' => $administrator->id,
        ]);

        self::assertSame(
            1,
            app(GenerateClientReminderRequests::class)->handle(CarbonImmutable::parse('2026-07-30')),
        );

        self::assertSame(ClientReminderStatus::Queued, ClientReminderRequest::query()->sole()->status);
        Queue::assertPushed(SendClientReminderEmail::class, 1);
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
