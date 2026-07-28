<?php

declare(strict_types=1);

namespace Tests\Feature\Compliance;

use App\Actions\Compliance\OverrideObligationDeadline;
use App\Enums\FirmRole;
use App\Enums\ObligationOrigin;
use App\Enums\ObligationStatus;
use App\Livewire\Obligations\Index;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Firm;
use App\Models\Obligation;
use App\Models\ObligationDeadlineOverride;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Concerns\BuildsFirmTenancy;
use Tests\TestCase;

final class ObligationDeadlineOverrideTest extends TestCase
{
    use BuildsFirmTenancy;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'platform.features.compliance_operations.enabled' => true,
            'platform.features.compliance_operations.firm_ids' => [],
        ]);
    }

    public function test_manager_records_append_only_override_without_changing_statutory_date(): void
    {
        [$manager, $obligation] = $this->fixture();

        $override = app(OverrideObligationDeadline::class)->handle($manager, $obligation, [
            'effectiveDueDate' => '2026-10-05',
            'reason' => 'Synthetic authority extension evidence reviewed.',
        ]);

        $obligation->refresh();
        $this->assertSame('2026-09-28', $obligation->statutory_due_date->toDateString());
        $this->assertSame('2026-10-05', $obligation->effectiveDueDate()->toDateString());
        $this->assertSame('2026-09-28', $override->previous_effective_due_date->toDateString());
        $this->assertSame('2026-10-05', $override->new_effective_due_date->toDateString());

        $audit = AuditLog::query()->where('action', 'obligation.deadline_overridden')->sole();
        $this->assertSame('2026-09-28', $audit->before_values['statutory_due_date']);
        $this->assertSame('2026-10-05', $audit->after_values['effective_due_date']);
    }

    public function test_repeated_override_chains_from_current_effective_date(): void
    {
        [$manager, $obligation] = $this->fixture();
        $action = app(OverrideObligationDeadline::class);
        $action->handle($manager, $obligation, [
            'effectiveDueDate' => '2026-10-05',
            'reason' => 'Synthetic first extension.',
        ]);
        $second = $action->handle($manager, $obligation->refresh(), [
            'effectiveDueDate' => '2026-10-12',
            'reason' => 'Synthetic replacement extension.',
        ]);

        $this->assertSame('2026-10-05', $second->previous_effective_due_date->toDateString());
        $this->assertSame(2, ObligationDeadlineOverride::query()->count());
    }

    public function test_no_op_closed_and_internally_inconsistent_overrides_are_rejected(): void
    {
        [$manager, $obligation] = $this->fixture();
        $action = app(OverrideObligationDeadline::class);

        foreach ([
            ['effectiveDueDate' => '2026-09-28', 'reason' => 'Synthetic no-op.'],
            ['effectiveDueDate' => '2026-09-20', 'reason' => 'Synthetic date before internal target.'],
        ] as $input) {
            try {
                $action->handle($manager, $obligation->refresh(), $input);
                $this->fail('The invalid override should fail.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('effectiveDueDate', $exception->errors());
            }
        }

        $obligation->update(['status' => ObligationStatus::Cancelled]);
        $this->expectException(ValidationException::class);
        $action->handle($manager, $obligation->refresh(), [
            'effectiveDueDate' => '2026-10-05',
            'reason' => 'Synthetic closed obligation attempt.',
        ]);
    }

    public function test_preparer_and_foreign_firm_cannot_override_deadline(): void
    {
        [$manager, $obligation, $firm] = $this->fixture();
        $preparer = User::factory()->create();
        $membership = $this->createFirmMembership($firm, $preparer, FirmRole::Preparer);
        $this->activateFirmMembership($membership);

        try {
            app(OverrideObligationDeadline::class)->handle($preparer, $obligation, [
                'effectiveDueDate' => '2026-10-05',
                'reason' => 'Synthetic denied attempt.',
            ]);
            $this->fail('A preparer should not override a deadline.');
        } catch (AuthorizationException) {
            $this->assertTrue(true);
        }

        $otherFirm = Firm::factory()->create();
        $otherMembership = $this->createFirmMembership($otherFirm, $manager, FirmRole::Manager);
        $this->activateFirmMembership($otherMembership);
        $this->expectException(AuthorizationException::class);
        app(OverrideObligationDeadline::class)->handle($manager, $obligation, [
            'effectiveDueDate' => '2026-10-05',
            'reason' => 'Synthetic cross-firm attempt.',
        ]);
    }

    public function test_history_is_protected_from_database_update_and_delete(): void
    {
        [$manager, $obligation] = $this->fixture();
        $override = app(OverrideObligationDeadline::class)->handle($manager, $obligation, [
            'effectiveDueDate' => '2026-10-05',
            'reason' => 'Synthetic retained event.',
        ]);

        try {
            ObligationDeadlineOverride::withoutGlobalScopes()->whereKey($override->id)->update(['reason' => 'Changed']);
            $this->fail('Database update should be blocked.');
        } catch (QueryException) {
            $this->assertTrue(true);
        }

        $this->expectException(QueryException::class);
        ObligationDeadlineOverride::withoutGlobalScopes()->whereKey($override->id)->delete();
    }

    public function test_livewire_override_keeps_both_dates_visible(): void
    {
        [$manager, $obligation] = $this->fixture();

        Livewire::actingAs($manager)
            ->test(Index::class)
            ->call('openDeadlineOverride', $obligation->id)
            ->set('deadlineOverrideDate', '2026-10-05')
            ->set('deadlineOverrideReason', 'Synthetic UI extension evidence.')
            ->call('overrideDeadline')
            ->assertHasNoErrors()
            ->assertSee('5 Oct 2026')
            ->assertSee('Statutory 28 Sep 2026');
    }

    /** @return array{User, Obligation, Firm} */
    private function fixture(): array
    {
        $manager = User::factory()->create();
        $firm = Firm::factory()->create();
        $membership = $this->createFirmMembership($firm, $manager, FirmRole::Manager);
        $this->activateFirmMembership($membership);
        $client = Client::factory()->createForFirm($firm, ['created_by' => $manager->id]);
        $this->activateFirmMembership($membership);
        $obligation = Obligation::factory()->createForFirm($firm, $client, [
            'statutory_due_date' => '2026-09-28',
            'effective_due_date' => '2026-09-28',
            'internal_target_date' => '2026-09-21',
            'origin' => ObligationOrigin::Manual,
            'status' => ObligationStatus::Open,
            'verified_by' => $manager->id,
            'created_by' => $manager->id,
        ]);

        return [$manager, $obligation, $firm];
    }
}
