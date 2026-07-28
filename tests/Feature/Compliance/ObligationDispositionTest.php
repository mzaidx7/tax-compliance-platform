<?php

declare(strict_types=1);

namespace Tests\Feature\Compliance;

use App\Actions\Compliance\DisposeObligation;
use App\Enums\FirmRole;
use App\Enums\ObligationStatus;
use App\Livewire\Obligations\Index;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Firm;
use App\Models\Obligation;
use App\Models\ObligationDisposition;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Concerns\BuildsFirmTenancy;
use Tests\TestCase;

final class ObligationDispositionTest extends TestCase
{
    use BuildsFirmTenancy;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['platform.features.compliance_operations.enabled' => true, 'platform.features.compliance_operations.firm_ids' => []]);
    }

    public function test_manager_cancels_open_obligation_with_retained_history(): void
    {
        [$manager, $original] = $this->fixture();
        $statutory = $original->statutory_due_date->toDateString();

        $event = app(DisposeObligation::class)->handle($manager, $original, [
            'status' => 'cancelled',
            'reason' => 'Synthetic duplicate entered in error.',
        ]);

        $original->refresh();
        $this->assertSame(ObligationStatus::Cancelled, $original->status);
        $this->assertSame($statutory, $original->statutory_due_date->toDateString());
        $this->assertNull($event->replacement_obligation_id);
        $this->assertSame(ObligationStatus::Open, $event->previous_status);
        $this->assertSame('Synthetic duplicate entered in error.', AuditLog::query()->where('action', 'obligation.disposed')->sole()->reason);
    }

    public function test_supersession_requires_and_retains_a_distinct_open_replacement(): void
    {
        [$manager, $original, $firm, $client] = $this->fixture();
        $replacement = $this->obligation($firm, $client, $manager, 'Synthetic corrected obligation', '2026-10-05');

        $event = app(DisposeObligation::class)->handle($manager, $original, [
            'status' => 'superseded',
            'replacementObligationId' => $replacement->id,
            'reason' => 'Synthetic approved replacement.',
        ]);

        $this->assertSame(ObligationStatus::Superseded, $original->refresh()->status);
        $this->assertSame(ObligationStatus::Open, $replacement->refresh()->status);
        $this->assertSame($replacement->id, $event->replacement_obligation_id);
    }

    public function test_invalid_replacement_and_repeat_disposition_are_rejected(): void
    {
        [$manager, $original] = $this->fixture();
        $action = app(DisposeObligation::class);

        foreach ([
            ['status' => 'superseded', 'reason' => 'Synthetic missing replacement.'],
            ['status' => 'superseded', 'replacementObligationId' => $original->id, 'reason' => 'Synthetic self replacement.'],
        ] as $input) {
            try {
                $action->handle($manager, $original, $input);
                $this->fail('Invalid supersession should fail.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('replacementObligationId', $exception->errors());
            }
        }

        $action->handle($manager, $original, ['status' => 'cancelled', 'reason' => 'Synthetic cancellation.']);
        $this->expectException(ValidationException::class);
        $action->handle($manager, $original->refresh(), ['status' => 'cancelled', 'reason' => 'Synthetic repeat.']);
    }

    public function test_foreign_firm_and_preparer_are_denied(): void
    {
        [$manager, $original, $firm] = $this->fixture();
        $preparer = User::factory()->create();
        $membership = $this->createFirmMembership($firm, $preparer, FirmRole::Preparer);
        $this->activateFirmMembership($membership);
        try {
            app(DisposeObligation::class)->handle($preparer, $original, ['status' => 'cancelled', 'reason' => 'Synthetic denied.']);
            $this->fail('Preparer should be denied.');
        } catch (AuthorizationException) {
            $this->assertTrue(true);
        }

        $otherFirm = Firm::factory()->create();
        $otherMembership = $this->createFirmMembership($otherFirm, $manager, FirmRole::Manager);
        $this->activateFirmMembership($otherMembership);
        $this->expectException(AuthorizationException::class);
        app(DisposeObligation::class)->handle($manager, $original, ['status' => 'cancelled', 'reason' => 'Synthetic foreign firm.']);
    }

    public function test_history_rejects_raw_update_and_delete(): void
    {
        [$manager, $original] = $this->fixture();
        $event = app(DisposeObligation::class)->handle($manager, $original, ['status' => 'cancelled', 'reason' => 'Synthetic retention.']);
        try {
            ObligationDisposition::withoutGlobalScopes()->whereKey($event->id)->update(['reason' => 'Changed']);
            $this->fail('Raw update should fail.');
        } catch (QueryException) {
            $this->assertTrue(true);
        }
        $this->expectException(QueryException::class);
        ObligationDisposition::withoutGlobalScopes()->whereKey($event->id)->delete();
    }

    public function test_livewire_manager_can_supersede_and_sees_retained_status(): void
    {
        [$manager, $original, $firm, $client] = $this->fixture();
        $replacement = $this->obligation($firm, $client, $manager, 'Synthetic UI replacement', '2026-10-05');

        Livewire::actingAs($manager)->test(Index::class)
            ->call('openDisposition', $original->id)
            ->set('dispositionStatus', 'superseded')
            ->set('replacementObligationId', $replacement->id)
            ->set('dispositionReason', 'Synthetic UI approval.')
            ->call('disposeObligation')
            ->assertHasNoErrors()
            ->assertSee('Deadline: Superseded');
    }

    /** @return array{User, Obligation, Firm, Client} */
    private function fixture(): array
    {
        $manager = User::factory()->create();
        $firm = Firm::factory()->create();
        $membership = $this->createFirmMembership($firm, $manager, FirmRole::Manager);
        $this->activateFirmMembership($membership);
        $client = Client::factory()->createForFirm($firm, ['created_by' => $manager->id]);
        $this->activateFirmMembership($membership);

        return [$manager, $this->obligation($firm, $client, $manager, 'Synthetic original obligation', '2026-09-28'), $firm, $client];
    }

    private function obligation(Firm $firm, Client $client, User $manager, string $type, string $date): Obligation
    {
        return Obligation::factory()->createForFirm($firm, $client, [
            'obligation_type' => $type,
            'statutory_due_date' => $date,
            'effective_due_date' => $date,
            'internal_target_date' => null,
            'status' => ObligationStatus::Open,
            'verified_by' => $manager->id,
            'created_by' => $manager->id,
        ]);
    }
}
