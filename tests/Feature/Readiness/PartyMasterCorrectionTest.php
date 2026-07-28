<?php

declare(strict_types=1);

namespace Tests\Feature\Readiness;

use App\Actions\Readiness\AddInitialPartyField;
use App\Actions\Readiness\CreatePartyRecord;
use App\Actions\Readiness\DecidePartyFieldCorrection;
use App\Actions\Readiness\ProposePartyFieldCorrection;
use App\Enums\FirmRole;
use App\Livewire\Readiness\Parties\Index;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Firm;
use App\Models\FirmMembership;
use App\Models\PartyFieldVersion;
use App\Models\PartyRecord;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Concerns\BuildsFirmTenancy;
use Tests\TestCase;

final class PartyMasterCorrectionTest extends TestCase
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

    public function test_party_can_explicitly_hold_customer_and_supplier_roles_without_duplicate_identity(): void
    {
        [$operator, , , , , $client] = $this->context();
        $party = app(CreatePartyRecord::class)->handle($operator, $client, 'SYN-PARTY-001', true, true, true);

        $this->assertTrue($party->is_customer);
        $this->assertTrue($party->is_supplier);
        $this->assertSame(1, PartyRecord::query()->count());
    }

    public function test_initial_field_retains_source_and_audit_excludes_personal_value(): void
    {
        [$operator, , , , , $client] = $this->context();
        $party = app(CreatePartyRecord::class)->handle($operator, $client, 'SYN-PARTY-002', true, false, true);
        $field = app(AddInitialPartyField::class)->handle(
            $operator, $party, 'legal_name', 'Synthetic Counterparty LLC', 'unverified', 'Synthetic manual worksheet row 12',
        );

        $this->assertSame('Synthetic Counterparty LLC', $field->value);
        $this->assertSame('Synthetic manual worksheet row 12', $field->source_reference);
        $audit = AuditLog::query()->where('action', 'party_field.initial_recorded')->sole();
        $this->assertArrayNotHasKey('value', $audit->after_values);
        $this->assertArrayNotHasKey('source_reference', $audit->after_values);
    }

    public function test_approved_correction_appends_new_field_version_and_preserves_old_value(): void
    {
        [$operator, $manager, , , $managerMembership, $client] = $this->context();
        [$party, $field] = $this->partyWithField($operator, $client);
        $proposal = app(ProposePartyFieldCorrection::class)->handle(
            $operator, $party, $field, 'Synthetic Counterparty Legal Name LLC', 'Synthetic reviewed source evidence.',
        );
        $this->activateFirmMembership($managerMembership);
        $decision = app(DecidePartyFieldCorrection::class)->handle($manager, $proposal, 'approved', 'Synthetic independent approval.');

        $this->assertNotNull($decision->new_party_field_version_id);
        $this->assertSame('Synthetic Counterparty LLC', $field->refresh()->value);
        $current = $party->currentField('legal_name');
        $this->assertSame('Synthetic Counterparty Legal Name LLC', $current?->value);
        $this->assertSame($field->id, $current?->supersedes_party_field_version_id);
        $this->assertSame(2, PartyFieldVersion::query()->count());
    }

    public function test_rejection_creates_no_field_version_and_proposer_cannot_decide(): void
    {
        [$operator, $manager, , $operatorMembership, $managerMembership, $client] = $this->context();
        [$party, $field] = $this->partyWithField($operator, $client);
        $proposal = app(ProposePartyFieldCorrection::class)->handle($operator, $party, $field, 'Synthetic Proposed Name', 'Synthetic evidence.');
        try {
            app(DecidePartyFieldCorrection::class)->handle($operator, $proposal, 'approved', 'Synthetic self approval.');
            $this->fail('The proposer must not decide.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('approver', $exception->errors());
        }
        $this->activateFirmMembership($managerMembership);
        app(DecidePartyFieldCorrection::class)->handle($manager, $proposal, 'rejected', 'Synthetic rejection.');
        $this->assertSame(1, PartyFieldVersion::query()->count());
        $this->activateFirmMembership($operatorMembership);
    }

    public function test_stale_proposal_and_raw_evidence_mutation_fail_closed(): void
    {
        [$operator, $manager, , , $managerMembership, $client] = $this->context();
        [$party, $field] = $this->partyWithField($operator, $client);
        $first = app(ProposePartyFieldCorrection::class)->handle($operator, $party, $field, 'Synthetic First Name', 'Synthetic first evidence.');
        $second = app(ProposePartyFieldCorrection::class)->handle($operator, $party, $field, 'Synthetic Second Name', 'Synthetic second evidence.');
        $this->activateFirmMembership($managerMembership);
        app(DecidePartyFieldCorrection::class)->handle($manager, $first, 'approved', 'Synthetic first approval.');

        try {
            app(DecidePartyFieldCorrection::class)->handle($manager, $second, 'approved', 'Synthetic stale approval.');
            $this->fail('A stale proposal must fail.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('proposal', $exception->errors());
        }

        $this->expectException(QueryException::class);
        DB::table('party_correction_proposals')->where('id', $first->id)->update(['proposed_value' => 'Changed']);
    }

    public function test_livewire_operator_records_party_field_and_proposal(): void
    {
        [$operator, , , , , $client] = $this->context();

        Livewire::actingAs($operator)->test(Index::class)
            ->set('clientId', $client->id)
            ->set('reference', 'SYN-PARTY-UI')
            ->set('isCustomer', true)
            ->set('isSupplier', true)
            ->call('createParty')
            ->set('fieldKey', 'legal_name')
            ->set('fieldValue', 'Synthetic UI Counterparty LLC')
            ->set('verificationState', 'unverified')
            ->set('sourceReference', 'Synthetic UI source.')
            ->call('addField')
            ->set('proposedValue', 'Synthetic UI Legal Name LLC')
            ->set('evidenceNote', 'Synthetic UI evidence.')
            ->call('proposeCorrection')
            ->assertHasNoErrors()
            ->assertSee('Awaiting decision')
            ->assertSee('Customer')
            ->assertSee('Supplier');
    }

    /** @return array{User, User, Firm, FirmMembership, FirmMembership, Client} */
    private function context(): array
    {
        $firm = Firm::factory()->create();
        $operator = User::factory()->create();
        $manager = User::factory()->create();
        $operatorMembership = $this->createFirmMembership($firm, $operator, FirmRole::DataCleanupOperator);
        $managerMembership = $this->createFirmMembership($firm, $manager, FirmRole::Manager);
        $this->activateFirmMembership($operatorMembership);
        $client = Client::factory()->createForFirm($firm, ['created_by' => $operator->id]);
        $this->activateFirmMembership($operatorMembership);

        return [$operator, $manager, $firm, $operatorMembership, $managerMembership, $client];
    }

    /** @return array{PartyRecord, PartyFieldVersion} */
    private function partyWithField(User $operator, Client $client): array
    {
        $party = app(CreatePartyRecord::class)->handle($operator, $client, 'SYN-PARTY-CORRECTION', true, false, true);
        $field = app(AddInitialPartyField::class)->handle(
            $operator, $party, 'legal_name', 'Synthetic Counterparty LLC', 'unverified', 'Synthetic source.',
        );

        return [$party, $field];
    }
}
