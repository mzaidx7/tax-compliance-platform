<?php

declare(strict_types=1);

namespace Tests\Feature\Readiness;

use App\Actions\Readiness\AddInvoiceSampleField;
use App\Actions\Readiness\CreateInvoiceReadinessSample;
use App\Actions\Readiness\RecordInvoiceReadinessIssue;
use App\Actions\Readiness\ResolveInvoiceReadinessIssue;
use App\Enums\FirmRole;
use App\Enums\RuleVersionStatus;
use App\Livewire\Readiness\Invoices\Index;
use App\Models\Client;
use App\Models\DataQualityRuleDefinition;
use App\Models\DataQualityRuleVersion;
use App\Models\Firm;
use App\Models\FirmMembership;
use App\Models\InvoiceReadinessSample;
use App\Models\InvoiceSampleField;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Concerns\BuildsFirmTenancy;
use Tests\TestCase;

final class InvoiceReadinessSampleTest extends TestCase
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

    public function test_operator_records_synthetic_sample_and_supplied_field_without_calculation(): void
    {
        $fixture = $this->fixture();

        $this->assertSame('invoice_number', $fixture['field']->field_key->value);
        $this->assertSame('SYN-INV-001', $fixture['field']->supplied_value);
        $this->assertFalse(array_key_exists('calculated_value', $fixture['field']->getAttributes()));
        $this->assertDatabaseHas('audit_logs', ['action' => 'invoice_sample_field.recorded']);
    }

    public function test_invoice_rule_issue_is_idempotent_and_snapshots_explanation(): void
    {
        $fixture = $this->fixture();
        $action = app(RecordInvoiceReadinessIssue::class);
        $first = $action->handle($fixture['operator'], $fixture['sample'], $fixture['field'], $fixture['invoiceRule'], 'Synthetic manual invoice finding.');
        $second = $action->handle($fixture['operator'], $fixture['sample'], $fixture['field'], $fixture['invoiceRule'], 'Repeated synthetic finding.');

        $this->assertSame($first->id, $second->id);
        $this->assertSame('Synthetic invoice explanation.', $first->explanation_snapshot);
        $this->assertDatabaseCount('invoice_readiness_issues', 1);
    }

    public function test_party_rule_is_rejected_and_independent_manager_resolves_invoice_issue(): void
    {
        $fixture = $this->fixture();
        try {
            app(RecordInvoiceReadinessIssue::class)->handle(
                $fixture['operator'], $fixture['sample'], $fixture['field'], $fixture['partyRule'], 'Wrong-domain evidence.',
            );
            $this->fail('Party rules must not create invoice issues.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('ruleVersion', $exception->errors());
        }
        $issue = app(RecordInvoiceReadinessIssue::class)->handle(
            $fixture['operator'], $fixture['sample'], $fixture['field'], $fixture['invoiceRule'], 'Synthetic invoice issue.',
        );
        $this->activateFirmMembership($fixture['managerMembership']);
        $resolution = app(ResolveInvoiceReadinessIssue::class)->handle(
            $fixture['manager'], $issue, 'resolved', 'Synthetic field reviewed manually.',
        );

        $this->assertSame('resolved', $resolution->outcome);
        $this->assertDatabaseHas('audit_logs', ['action' => 'invoice_readiness_issue.resolved']);
    }

    public function test_sample_issue_and_resolution_evidence_is_append_only(): void
    {
        $fixture = $this->fixture();
        $issue = app(RecordInvoiceReadinessIssue::class)->handle(
            $fixture['operator'], $fixture['sample'], $fixture['field'], $fixture['invoiceRule'], 'Synthetic invoice issue.',
        );
        $this->activateFirmMembership($fixture['managerMembership']);
        app(ResolveInvoiceReadinessIssue::class)->handle($fixture['manager'], $issue, 'not_applicable', 'Synthetic rule is not applicable.');
        try {
            app(ResolveInvoiceReadinessIssue::class)->handle($fixture['manager'], $issue, 'resolved', 'Repeated decision.');
            $this->fail('Invoice issue resolution must be terminal.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('issue', $exception->errors());
        }

        $this->expectException(QueryException::class);
        DB::table('invoice_readiness_samples')->where('id', $fixture['sample']->id)->delete();
    }

    public function test_livewire_operator_records_and_sees_explainable_invoice_issue(): void
    {
        $fixture = $this->fixture();

        Livewire::actingAs($fixture['operator'])->test(Index::class)
            ->set('issueSampleId', $fixture['sample']->id)
            ->set('issueFieldId', $fixture['field']->id)
            ->set('issueRuleId', $fixture['invoiceRule']->id)
            ->set('issueEvidence', 'Synthetic UI invoice evidence.')
            ->call('recordIssue')
            ->assertHasNoErrors()
            ->assertSee('Synthetic invoice explanation.')
            ->assertSee('Open');
    }

    /**
     * @return array{
     *  operator: User, manager: User, managerMembership: FirmMembership,
     *  sample: InvoiceReadinessSample, field: InvoiceSampleField,
     *  invoiceRule: DataQualityRuleVersion, partyRule: DataQualityRuleVersion
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
        $sample = app(CreateInvoiceReadinessSample::class)->handle($operator, $client, 'SYN-SAMPLE-001', 'Synthetic manual sample.');
        $field = app(AddInvoiceSampleField::class)->handle($operator, $sample, 'invoice_number', 'SYN-INV-001', 'Synthetic manual source.');
        $invoiceRule = $this->rule($operator, $manager, 'invoice_transaction');
        $partyRule = $this->rule($operator, $manager, 'party_master');

        return compact('operator', 'manager', 'managerMembership', 'sample', 'field', 'invoiceRule', 'partyRule');
    }

    private function rule(User $preparer, User $verifier, string $domain): DataQualityRuleVersion
    {
        $definition = DataQualityRuleDefinition::query()->create([
            'key' => "synthetic_{$domain}_sample_issue",
            'name' => 'Synthetic sample issue',
            'data_domain' => $domain,
            'field_or_scenario' => $domain === 'invoice_transaction' ? 'invoice.number' : 'party.legal_name',
            'created_by' => $preparer->id,
        ]);

        return DataQualityRuleVersion::query()->create([
            'data_quality_rule_definition_id' => $definition->id,
            'version' => 1,
            'status' => RuleVersionStatus::Published,
            'applicability_criteria' => 'Synthetic samples.',
            'severity' => 'medium',
            'behavior' => 'warning',
            'explanation' => 'Synthetic invoice explanation.',
            'remediation_guidance' => 'Synthetic invoice remediation.',
            'source_kind' => 'internal',
            'source_title' => 'Synthetic internal source',
            'source_url' => null,
            'formula_version_effect' => 'No score effect.',
            'prepared_by' => $preparer->id,
            'verified_by' => $verifier->id,
            'source_last_verified_on' => '2026-07-28',
            'verified_at' => now(),
            'approved_at' => now(),
            'published_at' => now(),
            'change_summary' => 'Synthetic invoice rule.',
        ]);
    }
}
