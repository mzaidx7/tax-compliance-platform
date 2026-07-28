<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Actions\Notifications\GenerateManagerOperationalSummary;
use App\Actions\Notifications\MarkNotificationRead;
use App\Enums\FirmRole;
use App\Livewire\Notifications\Index;
use App\Models\Firm;
use App\Models\FirmMembership;
use App\Models\User;
use App\Notifications\ManagerOperationalSummaryNotification;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\Concerns\BuildsFirmTenancy;
use Tests\TestCase;

final class NotificationCentreTest extends TestCase
{
    use BuildsFirmTenancy;
    use RefreshDatabase;

    public function test_explicit_manager_summary_is_encrypted_queued_and_idempotent_for_the_day(): void
    {
        Notification::fake();
        $fixture = $this->fixture();
        $action = app(GenerateManagerOperationalSummary::class);
        $first = $action->handle($fixture['administrator'], $fixture['managerMembership']);
        $second = $action->handle($fixture['administrator'], $fixture['managerMembership']);

        $this->assertSame($first->id, $second->id);
        $this->assertSame('manager_operational_summary', $first->template_key);
        $this->assertDatabaseCount('notifications', 1);
        Notification::assertSentTo($fixture['manager'], ManagerOperationalSummaryNotification::class);
        $this->assertDatabaseMissing('notifications', ['recipient_user_id' => $fixture['administrator']->id]);
    }

    public function test_recipient_sees_only_their_active_firm_notices_and_marks_one_read_once(): void
    {
        Notification::fake();
        $fixture = $this->fixture();
        $request = app(GenerateManagerOperationalSummary::class)->handle($fixture['administrator'], $fixture['managerMembership']);
        $this->activateFirmMembership($fixture['managerMembership']);

        $component = Livewire::actingAs($fixture['manager'])->test(Index::class)
            ->assertSee('Manager operational summary')
            ->assertSee('Unread')
            ->call('markRead', $request->id)
            ->assertDontSee('Unread');
        $component->call('markRead', $request->id);

        $this->assertDatabaseCount('notification_read_receipts', 1);
        $this->assertDatabaseHas('audit_logs', ['action' => 'firm.notification.read']);
    }

    public function test_other_user_and_other_firm_cannot_view_or_mark_notice_read(): void
    {
        Notification::fake();
        $fixture = $this->fixture();
        $request = app(GenerateManagerOperationalSummary::class)->handle($fixture['administrator'], $fixture['managerMembership']);
        $other = User::factory()->create();
        $otherMembership = $this->createFirmMembership($fixture['firm'], $other, FirmRole::Manager);
        $this->activateFirmMembership($otherMembership);

        Livewire::actingAs($other)->test(Index::class)->assertDontSee('Manager operational summary');
        $this->expectException(AuthorizationException::class);
        app(MarkNotificationRead::class)->handle($other, $request);
    }

    public function test_cross_firm_manager_summary_and_raw_receipt_mutation_fail_closed(): void
    {
        Notification::fake();
        $fixture = $this->fixture();
        $otherFirm = Firm::factory()->create();
        $otherManager = User::factory()->create();
        $otherMembership = $this->createFirmMembership($otherFirm, $otherManager, FirmRole::Manager);
        try {
            app(GenerateManagerOperationalSummary::class)->handle($fixture['administrator'], $otherMembership);
            $this->fail('Cross-firm manager summaries must fail closed.');
        } catch (AuthorizationException) {
            $this->assertDatabaseCount('notifications', 0);
        }

        $request = app(GenerateManagerOperationalSummary::class)->handle($fixture['administrator'], $fixture['managerMembership']);
        $this->activateFirmMembership($fixture['managerMembership']);
        $receipt = app(MarkNotificationRead::class)->handle($fixture['manager'], $request);
        $this->expectException(QueryException::class);
        DB::table('notification_read_receipts')->where('id', $receipt->id)->delete();
    }

    /**
     * @return array{
     *  firm: Firm, administrator: User, administratorMembership: FirmMembership,
     *  manager: User, managerMembership: FirmMembership
     * }
     */
    private function fixture(): array
    {
        $firm = Firm::factory()->create();
        $administrator = User::factory()->create();
        $manager = User::factory()->create();
        $administratorMembership = $this->createFirmMembership($firm, $administrator, FirmRole::FirmAdministrator);
        $managerMembership = $this->createFirmMembership($firm, $manager, FirmRole::Manager);
        $this->activateFirmMembership($administratorMembership);

        return compact('firm', 'administrator', 'administratorMembership', 'manager', 'managerMembership');
    }
}
