<?php

declare(strict_types=1);

namespace Tests\Feature\Readiness;

use App\Actions\Readiness\CreateDataQualityRuleDefinition;
use App\Actions\Readiness\DraftDataQualityRuleVersion;
use App\Actions\Readiness\TransitionDataQualityRuleVersion;
use App\Enums\FirmRole;
use App\Enums\ReadinessDataDomain;
use App\Enums\RuleVersionStatus;
use App\Livewire\Readiness\Rules\Index;
use App\Models\DataQualityRuleDefinition;
use App\Models\DataQualityRuleVersion;
use App\Models\Firm;
use App\Models\FirmMembership;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Concerns\BuildsFirmTenancy;
use Tests\TestCase;

final class DataQualityRuleGovernanceTest extends TestCase
{
    use BuildsFirmTenancy;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['platform.features.e_invoicing_readiness.enabled' => true, 'platform.features.e_invoicing_readiness.firm_ids' => []]);
    }

    public function test_party_and_transaction_rule_identities_remain_explicitly_separate(): void
    {
        [$preparer] = $this->context();
        $party = app(CreateDataQualityRuleDefinition::class)->handle($preparer, 'party_trn_missing', 'Party TRN missing', 'party_master', 'party.trn');
        $transaction = app(CreateDataQualityRuleDefinition::class)->handle($preparer, 'invoice_number_missing', 'Invoice number missing', 'invoice_transaction', 'invoice.number');

        $this->assertSame(ReadinessDataDomain::PartyMaster, $party->data_domain);
        $this->assertSame(ReadinessDataDomain::InvoiceTransaction, $transaction->data_domain);
        $this->assertNotSame($party->id, $transaction->id);
    }

    public function test_rule_version_uses_separate_preparation_verification_and_publication(): void
    {
        [$preparer, $verifier, , $preparerMembership, $verifierMembership] = $this->context();
        $definition = $this->definition($preparer);
        $version = $this->draft($preparer, $definition);
        $transition = app(TransitionDataQualityRuleVersion::class);
        $transition->handle($preparer, $version, 'under_review', 'Synthetic review submission.');
        $this->activateFirmMembership($verifierMembership);
        $transition->handle($verifier, $version->refresh(), 'approved', 'Synthetic independent approval.', '2026-07-28');
        $transition->handle($verifier, $version->refresh(), 'published', 'Synthetic publication.');

        $this->assertSame(RuleVersionStatus::Published, $version->refresh()->status);
        $this->assertSame($verifier->id, $version->verified_by);
        $this->assertDatabaseCount('data_quality_rule_events', 3);
        $this->assertDatabaseHas('audit_logs', ['action' => 'data_quality_rule.status_changed']);
        $this->activateFirmMembership($preparerMembership);
    }

    public function test_new_publication_supersedes_prior_without_changing_content(): void
    {
        [$preparer, $verifier, , $preparerMembership, $verifierMembership] = $this->context();
        $definition = $this->definition($preparer);
        $first = $this->draft($preparer, $definition);
        $this->publish($preparer, $verifier, $first, $preparerMembership, $verifierMembership);
        $this->activateFirmMembership($preparerMembership);
        $second = $this->draft($preparer, $definition, 'high');
        $this->publish($preparer, $verifier, $second, $preparerMembership, $verifierMembership);

        $this->assertSame(RuleVersionStatus::Superseded, $first->refresh()->status);
        $this->assertSame('medium', $first->severity->value);
        $this->assertSame(RuleVersionStatus::Published, $second->refresh()->status);
    }

    public function test_preparer_cannot_approve_and_official_source_must_be_allowed(): void
    {
        [$preparer] = $this->context();
        $definition = $this->definition($preparer);
        $version = $this->draft($preparer, $definition);
        $transition = app(TransitionDataQualityRuleVersion::class);
        $transition->handle($preparer, $version, 'under_review', 'Synthetic review.');
        try {
            $transition->handle($preparer, $version->refresh(), 'approved', 'Synthetic self approval.', '2026-07-28');
            $this->fail('The preparer must not approve.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('verifier', $exception->errors());
        }

        $this->expectException(ValidationException::class);
        app(DraftDataQualityRuleVersion::class)->handle($preparer, $definition, [
            ...$this->input(),
            'sourceUrl' => 'https://example.com/not-official',
        ]);
    }

    public function test_foreign_firm_and_raw_history_mutation_fail_closed(): void
    {
        [$preparer] = $this->context();
        $definition = $this->definition($preparer);
        $version = $this->draft($preparer, $definition);
        app(TransitionDataQualityRuleVersion::class)->handle($preparer, $version, 'under_review', 'Synthetic review.');

        try {
            DB::table('data_quality_rule_versions')->where('id', $version->id)->update(['explanation' => 'Changed']);
            $this->fail('Reviewed content must be immutable.');
        } catch (QueryException) {
            $this->assertTrue(true);
        }

        $otherFirm = Firm::factory()->create();
        $otherUser = User::factory()->create();
        $membership = $this->createFirmMembership($otherFirm, $otherUser, FirmRole::Manager);
        $this->activateFirmMembership($membership);
        $this->expectException(AuthorizationException::class);
        app(TransitionDataQualityRuleVersion::class)->handle($otherUser, $version, 'approved', 'Synthetic foreign attempt.', '2026-07-28');
    }

    public function test_livewire_operator_creates_separate_party_rule_draft(): void
    {
        [$preparer] = $this->context();

        Livewire::actingAs($preparer)->test(Index::class)
            ->set('definitionKey', 'party_email_missing')
            ->set('definitionName', 'Party email missing')
            ->set('dataDomain', 'party_master')
            ->set('fieldOrScenario', 'party.email')
            ->call('createDefinition')
            ->set('applicability', 'Synthetic party records.')
            ->set('severity', 'medium')
            ->set('behavior', 'warning')
            ->set('explanation', 'The supplied party email is empty.')
            ->set('remediation', 'Review source records and propose a correction.')
            ->set('sourceKind', 'internal')
            ->set('sourceTitle', 'Synthetic internal readiness policy')
            ->set('formulaEffect', 'No readiness score effect is approved.')
            ->set('changeSummary', 'Synthetic initial rule.')
            ->call('draftVersion')
            ->assertHasNoErrors()
            ->assertSee('Party master')
            ->assertSee('Party email missing');
    }

    /** @return array{User, User, Firm, FirmMembership, FirmMembership} */
    private function context(): array
    {
        $firm = Firm::factory()->create();
        $preparer = User::factory()->create();
        $verifier = User::factory()->create();
        $preparerMembership = $this->createFirmMembership($firm, $preparer, FirmRole::DataCleanupOperator);
        $verifierMembership = $this->createFirmMembership($firm, $verifier, FirmRole::Manager);
        $this->activateFirmMembership($preparerMembership);

        return [$preparer, $verifier, $firm, $preparerMembership, $verifierMembership];
    }

    private function definition(User $actor): DataQualityRuleDefinition
    {
        return app(CreateDataQualityRuleDefinition::class)->handle($actor, 'party_legal_name_missing', 'Party legal name missing', 'party_master', 'party.legal_name');
    }

    private function draft(User $actor, DataQualityRuleDefinition $definition, string $severity = 'medium'): DataQualityRuleVersion
    {
        return app(DraftDataQualityRuleVersion::class)->handle($actor, $definition, [...$this->input(), 'severity' => $severity]);
    }

    /** @return array<string, mixed> */
    private function input(): array
    {
        return [
            'applicability' => 'Synthetic party records.',
            'severity' => 'medium',
            'behavior' => 'warning',
            'explanation' => 'The supplied party legal name is empty.',
            'remediation' => 'Review the source and propose a legal name for human approval.',
            'sourceKind' => 'official',
            'sourceTitle' => 'UAE Ministry of Finance e-invoicing portal',
            'sourceUrl' => 'https://mof.gov.ae/einvoicing/',
            'formulaEffect' => 'No readiness score effect is approved.',
            'changeSummary' => 'Synthetic readiness rule.',
        ];
    }

    private function publish(
        User $preparer,
        User $verifier,
        DataQualityRuleVersion $version,
        FirmMembership $preparerMembership,
        FirmMembership $verifierMembership,
    ): void {
        $transition = app(TransitionDataQualityRuleVersion::class);
        $this->activateFirmMembership($preparerMembership);
        $transition->handle($preparer, $version, 'under_review', 'Synthetic review.');
        $this->activateFirmMembership($verifierMembership);
        $transition->handle($verifier, $version->refresh(), 'approved', 'Synthetic approval.', '2026-07-28');
        $transition->handle($verifier, $version->refresh(), 'published', 'Synthetic publication.');
    }
}
