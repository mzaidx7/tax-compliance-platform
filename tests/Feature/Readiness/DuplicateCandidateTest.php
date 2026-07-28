<?php

declare(strict_types=1);

namespace Tests\Feature\Readiness;

use App\Actions\Readiness\CreatePartyRecord;
use App\Actions\Readiness\DecideDuplicateCandidate;
use App\Actions\Readiness\RecordDuplicateCandidateSignal;
use App\Enums\FirmRole;
use App\Livewire\Readiness\Parties\Index;
use App\Models\Client;
use App\Models\Firm;
use App\Models\FirmMembership;
use App\Models\PartyRecord;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Concerns\BuildsFirmTenancy;
use Tests\TestCase;

final class DuplicateCandidateTest extends TestCase
{
    use BuildsFirmTenancy;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'platform.features.e_invoicing_readiness.enabled' => true,
            'platform.features.e_invoicing_readiness.firm_ids' => [],
            'platform.features.client_master.enabled' => true,
            'platform.features.client_master.firm_ids' => [],
        ]);
    }

    public function test_signal_recording_is_deterministic_and_retains_explanation_without_score(): void
    {
        $fixture = $this->fixture();
        $action = app(RecordDuplicateCandidateSignal::class);
        $first = $action->handle(
            $fixture['operator'], $fixture['first'], $fixture['second'],
            'exact_normalized_legal_name', 'synthetic trading llc', 'synthetic trading llc',
            'manual-normalizer-v1', 'Both supplied legal names normalize to the same synthetic value.',
        );
        $second = $action->handle(
            $fixture['operator'], $fixture['second'], $fixture['first'],
            'exact_normalized_legal_name', 'synthetic trading llc', 'synthetic trading llc',
            'manual-normalizer-v1', 'Repeated synthetic evidence.',
        );

        $this->assertSame($first['candidate']->id, $second['candidate']->id);
        $this->assertSame($first['signal']->id, $second['signal']->id);
        $this->assertSame('manual-normalizer-v1', $first['signal']->normalizer_version);
        $this->assertDatabaseCount('duplicate_candidates', 1);
        $this->assertDatabaseCount('duplicate_candidate_signals', 1);
        $this->assertFalse(array_key_exists('score', $first['candidate']->getAttributes()));
    }

    public function test_independent_manager_confirms_candidate_without_merging_parties(): void
    {
        $fixture = $this->fixture();
        $recorded = app(RecordDuplicateCandidateSignal::class)->handle(
            $fixture['operator'], $fixture['first'], $fixture['second'],
            'shared_source_identifier', 'SYN-001', 'SYN-001', 'source-id-v1', 'Same synthetic source identifier.',
        );
        $this->activateFirmMembership($fixture['managerMembership']);
        $decision = app(DecideDuplicateCandidate::class)->handle(
            $fixture['manager'], $recorded['candidate'], 'confirmed', 'Synthetic records require a future governed merge review.',
        );

        $this->assertSame('confirmed', $decision->outcome->value);
        $this->assertDatabaseCount('party_records', 2);
        $this->assertDatabaseHas('audit_logs', ['action' => 'duplicate_candidate.decided']);
    }

    public function test_cross_client_cross_firm_same_party_and_repeated_decisions_fail_closed(): void
    {
        $fixture = $this->fixture();
        $action = app(RecordDuplicateCandidateSignal::class);
        $otherClient = Client::factory()->createForFirm($fixture['firm'], ['created_by' => $fixture['operator']->id]);
        $otherClientParty = app(CreatePartyRecord::class)->handle(
            $fixture['operator'], $otherClient, 'SYN-OTHER-CLIENT', true, false, true,
        );
        foreach ([[$fixture['first'], $fixture['first']], [$fixture['first'], $otherClientParty]] as [$first, $second]) {
            try {
                $action->handle($fixture['operator'], $first, $second, 'shared_address', 'same', 'same', 'v1', 'Synthetic signal.');
                $this->fail('Invalid party pairs must fail closed.');
            } catch (ValidationException $exception) {
                $this->assertNotEmpty($exception->errors());
            }
        }

        $otherFirm = Firm::factory()->create();
        $otherUser = User::factory()->create();
        $otherMembership = $this->createFirmMembership($otherFirm, $otherUser, FirmRole::DataCleanupOperator);
        $this->activateFirmMembership($otherMembership);
        $otherFirmClient = Client::factory()->createForFirm($otherFirm, ['created_by' => $otherUser->id]);
        $otherFirmParty = app(CreatePartyRecord::class)->handle($otherUser, $otherFirmClient, 'SYN-OTHER-FIRM', true, false, true);
        $this->activateFirmMembership($fixture['operatorMembership']);
        $this->expectException(AuthorizationException::class);
        $action->handle($fixture['operator'], $fixture['first'], $otherFirmParty, 'shared_address', 'same', 'same', 'v1', 'Synthetic signal.');
    }

    public function test_decision_is_terminal_and_append_only_at_database_layer(): void
    {
        $fixture = $this->fixture();
        $recorded = app(RecordDuplicateCandidateSignal::class)->handle(
            $fixture['operator'], $fixture['first'], $fixture['second'],
            'exact_normalized_email', 'synthetic@example.test', 'synthetic@example.test',
            'email-v1', 'Same synthetic normalized email.',
        );
        $this->activateFirmMembership($fixture['managerMembership']);
        app(DecideDuplicateCandidate::class)->handle($fixture['manager'], $recorded['candidate'], 'dismissed', 'Synthetic records are known to be distinct.');
        try {
            app(DecideDuplicateCandidate::class)->handle($fixture['manager'], $recorded['candidate'], 'confirmed', 'Repeated decision.');
            $this->fail('A duplicate decision must be terminal.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('candidate', $exception->errors());
        }

        $this->expectException(QueryException::class);
        DB::table('duplicate_candidates')->where('id', $recorded['candidate']->id)->delete();
    }

    public function test_livewire_operator_records_and_sees_explainable_candidate(): void
    {
        $fixture = $this->fixture();

        Livewire::actingAs($fixture['operator'])->test(Index::class)
            ->set('duplicateFirstPartyId', $fixture['first']->id)
            ->set('duplicateSecondPartyId', $fixture['second']->id)
            ->set('duplicateSignalType', 'exact_normalized_legal_name')
            ->set('duplicateFirstValue', 'synthetic trading llc')
            ->set('duplicateSecondValue', 'synthetic trading llc')
            ->set('duplicateNormalizerVersion', 'manual-v1')
            ->set('duplicateExplanation', 'Same deterministic synthetic normalized name.')
            ->call('recordDuplicateSignal')
            ->assertHasNoErrors()
            ->assertSee('Same deterministic synthetic normalized name.')
            ->assertSee('Awaiting decision');
    }

    /**
     * @return array{
     *  firm: Firm, operator: User, manager: User, operatorMembership: FirmMembership,
     *  managerMembership: FirmMembership, first: PartyRecord, second: PartyRecord
     * }
     */
    private function fixture(): array
    {
        $firm = Firm::factory()->create();
        $operator = User::factory()->create();
        $manager = User::factory()->create();
        $operatorMembership = $this->createFirmMembership($firm, $operator, FirmRole::DataCleanupOperator);
        $managerMembership = $this->createFirmMembership($firm, $manager, FirmRole::Manager);
        $this->activateFirmMembership($operatorMembership);
        $client = Client::factory()->createForFirm($firm, ['created_by' => $operator->id]);
        $first = app(CreatePartyRecord::class)->handle($operator, $client, 'SYN-DUP-001', true, false, true);
        $second = app(CreatePartyRecord::class)->handle($operator, $client, 'SYN-DUP-002', false, true, true);

        return compact('firm', 'operator', 'manager', 'operatorMembership', 'managerMembership', 'first', 'second');
    }
}
