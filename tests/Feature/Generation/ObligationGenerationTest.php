<?php

declare(strict_types=1);

namespace Tests\Feature\Generation;

use App\Actions\Clients\AddClientServiceEnrollment;
use App\Actions\Clients\AddTaxPeriod;
use App\Actions\Clients\AddTaxRegistration;
use App\Actions\Generation\CommitGeneratedObligation;
use App\Actions\Generation\PreviewGeneratedObligation;
use App\Actions\Rules\ApproveRuleVersion;
use App\Actions\Rules\CreateRuleTemplate;
use App\Actions\Rules\DraftRuleVersion;
use App\Actions\Rules\PublishRuleVersion;
use App\Actions\Rules\SubmitRuleVersionForReview;
use App\Enums\ClientService;
use App\Enums\FirmRole;
use App\Enums\ObligationOrigin;
use App\Enums\ObligationStatus;
use App\Enums\RuleVersionStatus;
use App\Enums\TaxRegistrationStatus;
use App\Enums\TaxType;
use App\Livewire\Generation\Index;
use App\Models\Client;
use App\Models\ClientServiceEnrollment;
use App\Models\Firm;
use App\Models\FirmMembership;
use App\Models\Obligation;
use App\Models\ObligationGenerationRun;
use App\Models\ObligationRuleTemplate;
use App\Models\ObligationRuleVersion;
use App\Models\TaxPeriod;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Concerns\BuildsFirmTenancy;
use Tests\TestCase;

final class ObligationGenerationTest extends TestCase
{
    use BuildsFirmTenancy;
    use RefreshDatabase;

    public function test_preview_and_commit_retain_complete_governed_snapshots(): void
    {
        $fixture = $this->fixture();
        $preview = $this->preview($fixture);
        $obligation = app(CommitGeneratedObligation::class)->handle($fixture['verifier'], $preview);

        $this->assertSame(ObligationOrigin::GovernedRule, $obligation->origin);
        $this->assertSame($fixture['service']->id, $obligation->client_service_enrollment_id);
        $this->assertSame($fixture['rule']->id, $obligation->obligation_rule_version_id);
        $this->assertSame($preview->deterministic_key, $obligation->generation_key);
        $this->assertSame('2026-09-30', $obligation->statutory_due_date->toDateString());
        $this->assertSame('Synthetic Q3 2026', $obligation->calculation_input_snapshot['period_label']);
        $this->assertSame([], $obligation->calculation_parameter_snapshot);
        $this->assertSame(
            ['statutory_due_date' => '2026-09-30'],
            $obligation->calculation_result_snapshot,
        );
        $this->assertStringContainsString('No statutory calculation was performed', $obligation->calculation_explanation);
        $this->assertDatabaseHas('audit_logs', ['action' => 'obligation_generation.committed']);
    }

    public function test_preview_and_commit_are_idempotent_for_same_inputs(): void
    {
        $fixture = $this->fixture();
        $firstPreview = $this->preview($fixture);
        $secondPreview = $this->preview($fixture);
        $first = app(CommitGeneratedObligation::class)->handle($fixture['verifier'], $firstPreview);
        $second = app(CommitGeneratedObligation::class)->handle($fixture['verifier'], $secondPreview);

        $this->assertSame($firstPreview->id, $secondPreview->id);
        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, Obligation::query()->where('origin', ObligationOrigin::GovernedRule)->count());
        $this->assertSame(2, ObligationGenerationRun::query()->count());
    }

    public function test_explicit_actual_tax_period_is_retained_without_inference(): void
    {
        $fixture = $this->fixture();
        $preview = app(PreviewGeneratedObligation::class)->handle(
            $fixture['verifier'],
            $fixture['client'],
            $fixture['service'],
            $fixture['taxPeriod'],
            $fixture['rule'],
            $this->inputs('2026-09-30'),
        );
        $obligation = app(CommitGeneratedObligation::class)->handle($fixture['verifier'], $preview);

        $this->assertSame($fixture['taxPeriod']->id, $preview->tax_period_id);
        $this->assertSame($fixture['taxPeriod']->id, $obligation->tax_period_id);
    }

    public function test_changed_input_creates_distinct_preview_without_overwriting_obligation(): void
    {
        $fixture = $this->fixture();
        $firstPreview = $this->preview($fixture);
        $first = app(CommitGeneratedObligation::class)->handle($fixture['verifier'], $firstPreview);
        $secondPreview = $this->preview($fixture, '2026-10-15');
        $second = app(CommitGeneratedObligation::class)->handle($fixture['verifier'], $secondPreview);

        $this->assertNotSame($firstPreview->deterministic_key, $secondPreview->deterministic_key);
        $this->assertNotSame($first->id, $second->id);
        $this->assertSame('2026-09-30', $first->statutory_due_date->toDateString());
        $this->assertSame('2026-10-15', $second->statutory_due_date->toDateString());
    }

    public function test_unpublished_rule_and_mismatched_service_are_rejected(): void
    {
        $fixture = $this->fixture();
        $draft = $this->draftRule($fixture, $fixture['template']);

        try {
            $this->preview($fixture, rule: $draft);
            $this->fail('An unpublished rule must not generate a preview.');
        } catch (ValidationException) {
            // Expected.
        }

        $otherClient = Client::factory()->createForFirm($fixture['firm'], ['created_by' => $fixture['preparer']->id]);
        $this->expectException(ValidationException::class);
        app(PreviewGeneratedObligation::class)->handle(
            $fixture['verifier'],
            $otherClient,
            $fixture['service'],
            null,
            $fixture['rule'],
            $this->inputs('2026-09-30'),
        );
    }

    public function test_superseded_rule_preview_cannot_be_committed(): void
    {
        $fixture = $this->fixture();
        $preview = $this->preview($fixture);
        $next = $this->publishRule($fixture, $this->draftRule($fixture, $fixture['template']));
        $this->assertSame(RuleVersionStatus::Published, $next->status);
        $this->activateFirmMembership($fixture['verifierMembership']);
        $this->expectException(ValidationException::class);

        app(CommitGeneratedObligation::class)->handle($fixture['verifier'], $preview);
    }

    public function test_foreign_firm_cannot_commit_preview(): void
    {
        $fixture = $this->fixture();
        $preview = $this->preview($fixture);
        $otherFirm = Firm::factory()->create();
        $otherAdmin = User::factory()->create();
        $membership = $this->createFirmMembership($otherFirm, $otherAdmin, FirmRole::FirmAdministrator);
        $this->activateFirmMembership($membership);
        $this->expectException(AuthorizationException::class);

        app(CommitGeneratedObligation::class)->handle($otherAdmin, $preview);
    }

    public function test_generation_run_and_generated_obligation_snapshots_reject_raw_mutation(): void
    {
        $fixture = $this->fixture();
        $preview = $this->preview($fixture);
        $obligation = app(CommitGeneratedObligation::class)->handle($fixture['verifier'], $preview);

        try {
            DB::table('obligation_generation_runs')
                ->where('id', $preview->id)
                ->update(['statutory_due_date' => '2027-01-01']);
            $this->fail('Generation runs must be immutable.');
        } catch (QueryException) {
            // Expected.
        }

        $this->expectException(QueryException::class);
        DB::table('obligations')
            ->where('id', $obligation->id)
            ->update(['statutory_due_date' => '2027-01-01']);
    }

    public function test_generated_obligation_workflow_status_can_change_without_rewriting_snapshot(): void
    {
        $fixture = $this->fixture();
        $obligation = app(CommitGeneratedObligation::class)->handle($fixture['verifier'], $this->preview($fixture));
        $obligation->update(['status' => ObligationStatus::Cancelled]);

        $this->assertSame(ObligationStatus::Cancelled, $obligation->refresh()->status);
        $this->assertSame('2026-09-30', $obligation->statutory_due_date->toDateString());
    }

    public function test_livewire_requires_preview_before_committing_generated_obligation(): void
    {
        $fixture = $this->fixture();

        Livewire::actingAs($fixture['verifier'])
            ->test(Index::class)
            ->set('clientId', $fixture['client']->id)
            ->set('serviceEnrollmentId', $fixture['service']->id)
            ->set('ruleVersionId', $fixture['rule']->id)
            ->set('applicabilityDate', '2026-09-30')
            ->set('periodLabel', 'Synthetic UI Q3')
            ->set('statutoryDueDate', '2026-09-30')
            ->set('internalTargetDate', '2026-09-25')
            ->call('preview')
            ->assertHasNoErrors()
            ->assertSee('No statutory calculation was performed')
            ->call('commit')
            ->assertHasNoErrors()
            ->assertSee('Obligation committed');
    }

    /**
     * @param array{
     *   verifier: User,
     *   client: Client,
     *   service: ClientServiceEnrollment,
     *   rule: ObligationRuleVersion,
     *   verifierMembership: FirmMembership
     * } $fixture
     */
    private function preview(
        array $fixture,
        string $statutoryDueDate = '2026-09-30',
        ?ObligationRuleVersion $rule = null,
    ): ObligationGenerationRun {
        $this->activateFirmMembership($fixture['verifierMembership']);

        return app(PreviewGeneratedObligation::class)->handle(
            $fixture['verifier'],
            $fixture['client'],
            $fixture['service'],
            null,
            $rule ?? $fixture['rule'],
            $this->inputs($statutoryDueDate),
        );
    }

    /**
     * @return array{
     *   applicabilityDate: string,
     *   statutoryDueDate: string,
     *   internalTargetDate: string,
     *   periodLabel: string
     * }
     */
    private function inputs(string $statutoryDueDate): array
    {
        return [
            'applicabilityDate' => '2026-09-30',
            'statutoryDueDate' => $statutoryDueDate,
            'internalTargetDate' => '2026-09-25',
            'periodLabel' => 'Synthetic Q3 2026',
        ];
    }

    /**
     * @param  array{preparer: User, preparerMembership: FirmMembership}  $fixture
     */
    private function draftRule(array $fixture, ObligationRuleTemplate $template): ObligationRuleVersion
    {
        $this->activateFirmMembership($fixture['preparerMembership']);

        return app(DraftRuleVersion::class)->handle(
            $fixture['preparer'],
            $template,
            '2026-01-01',
            null,
            'Synthetic manual-date applicability.',
            'manual_date_passthrough',
            [],
            'FTA legislation register',
            'https://tax.gov.ae/en/legislation.aspx',
            '2026-07-22',
            'Synthetic generation rule.',
        );
    }

    /**
     * @param array{
     *   preparer: User,
     *   verifier: User,
     *   preparerMembership: FirmMembership,
     *   verifierMembership: FirmMembership
     * } $fixture
     */
    private function publishRule(array $fixture, ObligationRuleVersion $rule): ObligationRuleVersion
    {
        $this->activateFirmMembership($fixture['preparerMembership']);
        app(SubmitRuleVersionForReview::class)->handle($fixture['preparer'], $rule, 'Synthetic review.');
        $this->activateFirmMembership($fixture['verifierMembership']);
        app(ApproveRuleVersion::class)->handle(
            $fixture['verifier'],
            $rule->refresh(),
            '2026-07-28',
            'Synthetic verification.',
        );
        app(PublishRuleVersion::class)->handle(
            $fixture['verifier'],
            $rule->refresh(),
            'Synthetic publication.',
        );

        return $rule->refresh();
    }

    /**
     * @return array{
     *   firm: Firm,
     *   preparer: User,
     *   verifier: User,
     *   preparerMembership: FirmMembership,
     *   verifierMembership: FirmMembership,
     *   client: Client,
     *   service: ClientServiceEnrollment,
     *   template: ObligationRuleTemplate,
     *   rule: ObligationRuleVersion,
     *   taxPeriod: TaxPeriod
     * }
     */
    private function fixture(): array
    {
        config([
            'platform.features.client_master.enabled' => true,
            'platform.features.client_master.firm_ids' => [],
            'platform.features.compliance_operations.enabled' => true,
            'platform.features.compliance_operations.firm_ids' => [],
        ]);
        $firm = Firm::factory()->create();
        $preparer = User::factory()->create();
        $verifier = User::factory()->create();
        $preparerMembership = $this->createFirmMembership($firm, $preparer, FirmRole::FirmAdministrator);
        $verifierMembership = $this->createFirmMembership($firm, $verifier, FirmRole::Manager);
        $this->activateFirmMembership($preparerMembership);
        $client = Client::factory()->createForFirm($firm, ['created_by' => $preparer->id]);
        $service = app(AddClientServiceEnrollment::class)->handle(
            $preparer,
            $client,
            ClientService::VatCompliance,
            '2026-01-01',
            null,
            $verifierMembership->id,
        );
        $registration = app(AddTaxRegistration::class)->handle(
            $preparer,
            $client,
            TaxType::Vat,
            'SYNTHETIC-GENERATION-TRN',
            TaxRegistrationStatus::Active,
            '2026-01-01',
            null,
        );
        $taxPeriod = app(AddTaxPeriod::class)->handle(
            $preparer,
            $registration,
            'Synthetic Q3 2026',
            '2026-07-01',
            '2026-09-30',
        );
        $template = app(CreateRuleTemplate::class)->handle(
            $preparer,
            'manual_filing_generation',
            'Manual filing generation',
            'Manual filing',
            'United Arab Emirates',
            'Federal Tax Authority',
        );
        $rule = $this->publishRule(
            compact('preparer', 'verifier', 'preparerMembership', 'verifierMembership'),
            $this->draftRule(compact('preparer', 'preparerMembership'), $template),
        );

        return compact(
            'firm',
            'preparer',
            'verifier',
            'preparerMembership',
            'verifierMembership',
            'client',
            'service',
            'template',
            'rule',
            'taxPeriod',
        );
    }
}
