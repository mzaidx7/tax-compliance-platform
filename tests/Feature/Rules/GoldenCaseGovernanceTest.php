<?php

declare(strict_types=1);

namespace Tests\Feature\Rules;

use App\Actions\Rules\AddGoldenCase;
use App\Actions\Rules\ApproveGoldenCaseSet;
use App\Actions\Rules\ApproveRuleVersion;
use App\Actions\Rules\CreateGoldenCaseSet;
use App\Actions\Rules\VerifyGoldenCase;
use App\Calculators\ManualDatePassthroughCalculator;
use App\Enums\FirmRole;
use App\Enums\RuleVersionStatus;
use App\Livewire\Rules\Index;
use App\Models\CalculatorGoldenCase;
use App\Models\CalculatorGoldenCaseSet;
use App\Models\Firm;
use App\Models\FirmMembership;
use App\Models\ObligationRuleTemplate;
use App\Models\ObligationRuleVersion;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Concerns\BuildsFirmTenancy;
use Tests\Fixtures\RegulatoryTestCalculator;
use Tests\TestCase;

final class GoldenCaseGovernanceTest extends TestCase
{
    use BuildsFirmTenancy;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'platform.features.compliance_operations.enabled' => true,
            'platform.features.compliance_operations.firm_ids' => [],
            'platform.rules.calculators' => [RegulatoryTestCalculator::class],
        ]);
    }

    public function test_case_set_requires_independent_passing_verification_before_approval(): void
    {
        [$preparer, $verifier, , , $verifierMembership] = $this->context();
        $set = app(CreateGoldenCaseSet::class)->handle($preparer, 'synthetic_regulatory_test', 'Synthetic normal-date cases');
        $case = $this->addCase($preparer, $set->id, '2026-02-28');

        $this->activateFirmMembership($verifierMembership);
        $verification = app(VerifyGoldenCase::class)->handle($verifier, $case);
        $this->assertTrue($verification->passed);
        $approved = app(ApproveGoldenCaseSet::class)->handle($verifier, $set, 'Synthetic case-set approval.');

        $this->assertSame('approved', $approved->status);
        $this->assertSame($verifier->id, $approved->approved_by);
        $this->assertDatabaseHas('audit_logs', ['action' => 'calculator_golden_case_set.approved']);
    }

    public function test_failing_or_unverified_case_blocks_set_approval(): void
    {
        [$preparer, $verifier, , , $verifierMembership] = $this->context();
        $set = app(CreateGoldenCaseSet::class)->handle($preparer, 'synthetic_regulatory_test', 'Synthetic failing cases');
        $case = $this->addCase($preparer, $set->id, '2026-03-01');
        $this->activateFirmMembership($verifierMembership);
        $this->assertFalse(app(VerifyGoldenCase::class)->handle($verifier, $case)->passed);

        $this->expectException(ValidationException::class);
        app(ApproveGoldenCaseSet::class)->handle($verifier, $set, 'Synthetic blocked approval.');
    }

    public function test_regulatory_rule_approval_is_gated_and_records_approved_case_set(): void
    {
        [$preparer, $verifier, $firm, $preparerMembership, $verifierMembership] = $this->context();
        $rule = $this->underReviewRule($firm, $preparer);

        $this->activateFirmMembership($verifierMembership);
        try {
            app(ApproveRuleVersion::class)->handle($verifier, $rule, '2026-07-28', 'Synthetic blocked rule.');
            $this->fail('Regulatory rule approval should require approved cases.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('calculatorKey', $exception->errors());
        }

        $this->activateFirmMembership($preparerMembership);
        $set = app(CreateGoldenCaseSet::class)->handle($preparer, 'synthetic_regulatory_test', 'Synthetic approval cases');
        $case = $this->addCase($preparer, $set->id, '2026-02-28');
        $this->activateFirmMembership($verifierMembership);
        app(VerifyGoldenCase::class)->handle($verifier, $case);
        app(ApproveGoldenCaseSet::class)->handle($verifier, $set, 'Synthetic evidence approval.');
        app(ApproveRuleVersion::class)->handle($verifier, $rule->refresh(), '2026-07-28', 'Synthetic rule approval.');

        $this->assertSame($set->id, $rule->refresh()->calculator_golden_case_set_id);
        $this->assertSame(RuleVersionStatus::Approved, $rule->status);
    }

    public function test_manual_passthrough_rule_remains_explicitly_non_regulatory(): void
    {
        config(['platform.rules.calculators' => [ManualDatePassthroughCalculator::class]]);
        [$preparer, $verifier, $firm, , $verifierMembership] = $this->context();
        $rule = $this->underReviewRule($firm, $preparer, 'manual_date_passthrough', []);

        $this->activateFirmMembership($verifierMembership);
        app(ApproveRuleVersion::class)->handle($verifier, $rule, '2026-07-28', 'Synthetic non-regulatory approval.');

        $this->assertNull($rule->refresh()->calculator_golden_case_set_id);
    }

    public function test_case_and_verification_evidence_reject_raw_mutation(): void
    {
        [$preparer, $verifier, , , $verifierMembership] = $this->context();
        $set = app(CreateGoldenCaseSet::class)->handle($preparer, 'synthetic_regulatory_test', 'Synthetic retained cases');
        $case = $this->addCase($preparer, $set->id, '2026-02-28');
        $this->activateFirmMembership($verifierMembership);
        app(VerifyGoldenCase::class)->handle($verifier, $case);

        $this->expectException(QueryException::class);
        CalculatorGoldenCase::withoutGlobalScopes()->whereKey($case->id)->delete();
    }

    public function test_livewire_verifier_runs_case_and_approves_selected_set(): void
    {
        [$preparer, $verifier, , , $verifierMembership] = $this->context();
        $set = app(CreateGoldenCaseSet::class)->handle($preparer, 'synthetic_regulatory_test', 'Synthetic UI cases');
        $case = $this->addCase($preparer, $set->id, '2026-02-28');
        $this->activateFirmMembership($verifierMembership);

        Livewire::actingAs($verifier)->test(Index::class)
            ->set('verificationCaseId', $case->id)
            ->call('verifyGoldenCase')
            ->assertHasNoErrors()
            ->assertSee('Passed')
            ->set('goldenCaseSetId', $set->id)
            ->set('caseSetApprovalReason', 'Synthetic UI approval.')
            ->call('approveGoldenCaseSet')
            ->assertHasNoErrors()
            ->assertSee('Approved');
    }

    /** @return array{User, User, Firm, FirmMembership, FirmMembership} */
    private function context(): array
    {
        $firm = Firm::factory()->create();
        $preparer = User::factory()->create();
        $verifier = User::factory()->create();
        $preparerMembership = $this->createFirmMembership($firm, $preparer, FirmRole::FirmAdministrator);
        $verifierMembership = $this->createFirmMembership($firm, $verifier, FirmRole::Manager);
        $this->activateFirmMembership($preparerMembership);

        return [$preparer, $verifier, $firm, $preparerMembership, $verifierMembership];
    }

    private function addCase(User $preparer, string $setId, string $expected): CalculatorGoldenCase
    {
        return app(AddGoldenCase::class)->handle(
            $preparer,
            CalculatorGoldenCaseSet::query()->findOrFail($setId),
            'Synthetic month-end case',
            ['period_end' => '2026-01-31'],
            ['days' => 28],
            $expected,
            'FTA legislation register',
            'https://tax.gov.ae/en/legislation.aspx',
            '2026-07-28',
        );
    }

    /** @param array<string, mixed> $parameters */
    private function underReviewRule(
        Firm $firm,
        User $preparer,
        string $calculator = 'synthetic_regulatory_test',
        array $parameters = ['days' => 28],
    ): ObligationRuleVersion {
        $template = ObligationRuleTemplate::query()->create([
            'key' => 'synthetic_golden_case_rule',
            'name' => 'Synthetic golden-case rule',
            'obligation_type' => 'Synthetic filing',
            'jurisdiction' => 'United Arab Emirates',
            'authority' => 'Federal Tax Authority',
            'created_by' => $preparer->id,
        ]);

        return ObligationRuleVersion::query()->create([
            'obligation_rule_template_id' => $template->id,
            'version' => 1,
            'status' => RuleVersionStatus::UnderReview,
            'effective_from' => '2026-01-01',
            'applicability_criteria' => 'Synthetic criteria.',
            'calculator_key' => $calculator,
            'parameters' => $parameters,
            'official_source_title' => 'FTA legislation register',
            'official_source_url' => 'https://tax.gov.ae/en/legislation.aspx',
            'source_published_on' => '2026-07-22',
            'prepared_by' => $preparer->id,
            'change_summary' => 'Synthetic test rule.',
        ]);
    }
}
