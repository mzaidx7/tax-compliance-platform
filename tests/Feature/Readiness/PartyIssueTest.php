<?php

declare(strict_types=1);

namespace Tests\Feature\Readiness;

use App\Actions\Readiness\AddInitialPartyField;
use App\Actions\Readiness\CreatePartyRecord;
use App\Actions\Readiness\RecordPartyIssue;
use App\Actions\Readiness\ResolvePartyIssue;
use App\Enums\FirmRole;
use App\Enums\RuleVersionStatus;
use App\Livewire\Readiness\Parties\Index;
use App\Models\Client;
use App\Models\DataQualityRuleDefinition;
use App\Models\DataQualityRuleVersion;
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

final class PartyIssueTest extends TestCase
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

    public function test_manual_issue_snapshots_published_party_rule_and_is_idempotent(): void
    {
        $fixture = $this->fixture();
        $action = app(RecordPartyIssue::class);
        $first = $action->handle($fixture['operator'], $fixture['party'], $fixture['field'], $fixture['rule'], 'Synthetic manual review finding.');
        $second = $action->handle($fixture['operator'], $fixture['party'], $fixture['field'], $fixture['rule'], 'Synthetic repeated finding.');

        $this->assertSame($first->id, $second->id);
        $this->assertSame('medium', $first->severity_snapshot->value);
        $this->assertSame('warning', $first->behavior_snapshot->value);
        $this->assertSame('Synthetic missing-name explanation.', $first->explanation_snapshot);
        $this->assertDatabaseCount('party_issues', 1);
    }

    public function test_independent_manager_resolves_issue_without_changing_issue_snapshot(): void
    {
        $fixture = $this->fixture();
        $issue = app(RecordPartyIssue::class)->handle(
            $fixture['operator'], $fixture['party'], $fixture['field'], $fixture['rule'], 'Synthetic issue evidence.',
        );
        $this->activateFirmMembership($fixture['managerMembership']);
        $resolution = app(ResolvePartyIssue::class)->handle($fixture['manager'], $issue, 'resolved', 'Synthetic source corrected and reviewed.');

        $this->assertSame('resolved', $resolution->outcome);
        $this->assertSame('Synthetic missing-name explanation.', $issue->refresh()->explanation_snapshot);
        $this->assertDatabaseHas('audit_logs', ['action' => 'party_issue.resolved']);
    }

    public function test_invoice_rule_stale_field_repeat_resolution_and_raw_mutation_fail_closed(): void
    {
        $fixture = $this->fixture();
        $invoiceRule = $this->rule($fixture['operator'], $fixture['manager'], 'invoice_transaction');
        try {
            app(RecordPartyIssue::class)->handle($fixture['operator'], $fixture['party'], $fixture['field'], $invoiceRule, 'Synthetic wrong-domain finding.');
            $this->fail('Invoice rules must not create party issues.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('ruleVersion', $exception->errors());
        }

        $issue = app(RecordPartyIssue::class)->handle(
            $fixture['operator'], $fixture['party'], $fixture['field'], $fixture['rule'], 'Synthetic retained issue.',
        );
        $this->activateFirmMembership($fixture['managerMembership']);
        app(ResolvePartyIssue::class)->handle($fixture['manager'], $issue, 'not_applicable', 'Synthetic not-applicable decision.');
        try {
            app(ResolvePartyIssue::class)->handle($fixture['manager'], $issue, 'resolved', 'Synthetic repeated decision.');
            $this->fail('Issue resolution must be terminal.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('issue', $exception->errors());
        }

        $this->expectException(QueryException::class);
        DB::table('party_issues')->where('id', $issue->id)->delete();
    }

    public function test_livewire_operator_records_explainable_issue(): void
    {
        $fixture = $this->fixture();

        Livewire::actingAs($fixture['operator'])->test(Index::class)
            ->set('issuePartyId', $fixture['party']->id)
            ->set('issueFieldVersionId', $fixture['field']->id)
            ->set('issueRuleVersionId', $fixture['rule']->id)
            ->set('issueEvidenceNote', 'Synthetic UI issue evidence.')
            ->call('recordIssue')
            ->assertHasNoErrors()
            ->assertSee('Synthetic missing-name explanation.')
            ->assertSee('Open');
    }

    /**
     * @return array{
     *  operator: User, manager: User, managerMembership: FirmMembership,
     *  party: PartyRecord, field: PartyFieldVersion, rule: DataQualityRuleVersion
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
        $this->activateFirmMembership($operatorMembership);
        $party = app(CreatePartyRecord::class)->handle($operator, $client, 'SYN-ISSUE-PARTY', true, false, true);
        $field = app(AddInitialPartyField::class)->handle($operator, $party, 'legal_name', 'Synthetic Name', 'unverified', 'Synthetic source.');
        $rule = $this->rule($operator, $manager, 'party_master');

        return compact('operator', 'manager', 'managerMembership', 'party', 'field', 'rule');
    }

    private function rule(User $preparer, User $verifier, string $domain): DataQualityRuleVersion
    {
        $definition = DataQualityRuleDefinition::query()->create([
            'key' => "synthetic_{$domain}_issue",
            'name' => 'Synthetic issue rule',
            'data_domain' => $domain,
            'field_or_scenario' => $domain === 'party_master' ? 'party.legal_name' : 'invoice.number',
            'created_by' => $preparer->id,
        ]);

        return DataQualityRuleVersion::query()->create([
            'data_quality_rule_definition_id' => $definition->id,
            'version' => 1,
            'status' => RuleVersionStatus::Published,
            'applicability_criteria' => 'Synthetic records.',
            'severity' => 'medium',
            'behavior' => 'warning',
            'explanation' => 'Synthetic missing-name explanation.',
            'remediation_guidance' => 'Synthetic remediation guidance.',
            'source_kind' => 'internal',
            'source_title' => 'Synthetic internal rule source',
            'source_url' => null,
            'formula_version_effect' => 'No score effect.',
            'prepared_by' => $preparer->id,
            'verified_by' => $verifier->id,
            'source_last_verified_on' => '2026-07-28',
            'verified_at' => now(),
            'approved_at' => now(),
            'published_at' => now(),
            'change_summary' => 'Synthetic published rule.',
        ]);
    }
}
