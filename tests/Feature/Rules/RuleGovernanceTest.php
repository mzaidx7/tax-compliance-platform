<?php

declare(strict_types=1);

namespace Tests\Feature\Rules;

use App\Actions\Rules\ApproveRuleVersion;
use App\Actions\Rules\CreateRuleTemplate;
use App\Actions\Rules\DraftRuleVersion;
use App\Actions\Rules\PublishRuleVersion;
use App\Actions\Rules\RetireRuleVersion;
use App\Actions\Rules\SubmitRuleVersionForReview;
use App\Actions\Rules\UpdateDraftRuleVersion;
use App\Calculators\ManualDatePassthroughCalculator;
use App\Enums\FirmRole;
use App\Enums\RuleVersionStatus;
use App\Livewire\Rules\Index;
use App\Models\Firm;
use App\Models\FirmMembership;
use App\Models\ObligationRuleTemplate;
use App\Models\ObligationRuleVersion;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Concerns\BuildsFirmTenancy;
use Tests\TestCase;

final class RuleGovernanceTest extends TestCase
{
    use BuildsFirmTenancy;
    use RefreshDatabase;

    public function test_manual_date_calculator_explicitly_performs_no_statutory_calculation(): void
    {
        $result = app(ManualDatePassthroughCalculator::class)->calculate(
            ['statutory_due_date' => '2026-09-30'],
            [],
        );

        $this->assertSame('2026-09-30', $result->statutoryDueDate);
        $this->assertStringContainsString('No statutory calculation was performed', $result->explanation);
    }

    public function test_draft_requires_configured_official_https_source_host(): void
    {
        $fixture = $this->fixture();
        $template = $this->template($fixture);
        $this->expectException(ValidationException::class);

        $this->draft($fixture, $template, 'https://example.com/synthetic-source');
    }

    public function test_separate_preparer_and_verifier_complete_governed_lifecycle(): void
    {
        $fixture = $this->fixture();
        $version = $this->draft($fixture, $this->template($fixture));

        app(SubmitRuleVersionForReview::class)->handle(
            $fixture['preparer'],
            $version,
            'Synthetic review submission.',
        );
        $this->activateFirmMembership($fixture['verifierMembership']);
        app(ApproveRuleVersion::class)->handle(
            $fixture['verifier'],
            $version->refresh(),
            '2026-07-28',
            'Synthetic source verification.',
        );
        app(PublishRuleVersion::class)->handle(
            $fixture['verifier'],
            $version->refresh(),
            'Synthetic publication approval.',
        );

        $published = $version->refresh();
        $this->assertSame(RuleVersionStatus::Published, $published->status);
        $this->assertSame($fixture['verifier']->id, $published->verified_by);
        $this->assertNotNull($published->approved_at);
        $this->assertNotNull($published->published_at);
        $this->assertSame(4, $published->events()->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'obligation_rule.published']);
    }

    public function test_preparer_cannot_approve_own_rule(): void
    {
        $fixture = $this->fixture();
        $version = $this->draft($fixture, $this->template($fixture));
        app(SubmitRuleVersionForReview::class)->handle($fixture['preparer'], $version, 'Synthetic review.');
        $this->expectException(ValidationException::class);

        app(ApproveRuleVersion::class)->handle(
            $fixture['preparer'],
            $version->refresh(),
            '2026-07-28',
            'Synthetic self approval.',
        );
    }

    public function test_unregistered_calculator_cannot_enter_review(): void
    {
        $fixture = $this->fixture();
        $version = $this->draft(
            $fixture,
            $this->template($fixture),
            calculatorKey: 'unimplemented_vat_formula',
        );
        $this->expectException(ValidationException::class);

        app(SubmitRuleVersionForReview::class)->handle(
            $fixture['preparer'],
            $version,
            'Synthetic blocked review.',
        );
    }

    public function test_draft_content_can_be_updated_but_freezes_on_review(): void
    {
        $fixture = $this->fixture();
        $version = $this->draft($fixture, $this->template($fixture));
        $updated = app(UpdateDraftRuleVersion::class)->handle(
            $fixture['preparer'],
            $version,
            '2026-02-01',
            null,
            'Updated synthetic applicability.',
            'manual_date_passthrough',
            [],
            'Updated FTA legislation register',
            'https://tax.gov.ae/en/legislation.aspx',
            '2026-07-22',
            'Synthetic draft correction.',
        );
        $this->assertSame('Updated synthetic applicability.', $updated->applicability_criteria);
        app(SubmitRuleVersionForReview::class)->handle($fixture['preparer'], $updated, 'Synthetic review.');
        $this->expectException(ValidationException::class);

        app(UpdateDraftRuleVersion::class)->handle(
            $fixture['preparer'],
            $updated->refresh(),
            '2026-03-01',
            null,
            'Attempted reviewed edit.',
            'manual_date_passthrough',
            [],
            'FTA legislation register',
            'https://tax.gov.ae/en/legislation.aspx',
            '2026-07-22',
            'Attempted reviewed edit.',
        );
    }

    public function test_database_rejects_skipping_rule_lifecycle_states(): void
    {
        $fixture = $this->fixture();
        $version = $this->draft($fixture, $this->template($fixture));
        $this->expectException(QueryException::class);

        DB::table('obligation_rule_versions')
            ->where('id', $version->id)
            ->update(['status' => RuleVersionStatus::Published->value, 'published_at' => now()]);
    }

    public function test_publishing_new_version_supersedes_prior_published_version(): void
    {
        $fixture = $this->fixture();
        $template = $this->template($fixture);
        $first = $this->publish($fixture, $this->draft($fixture, $template));
        $second = $this->publish($fixture, $this->draft($fixture, $template));

        $this->assertSame(RuleVersionStatus::Superseded, $first->refresh()->status);
        $this->assertSame(RuleVersionStatus::Published, $second->refresh()->status);
        $this->assertDatabaseHas('obligation_rule_version_events', [
            'obligation_rule_version_id' => $first->id,
            'to_status' => RuleVersionStatus::Superseded->value,
        ]);
    }

    public function test_published_rule_can_be_explicitly_retired(): void
    {
        $fixture = $this->fixture();
        $version = $this->publish($fixture, $this->draft($fixture, $this->template($fixture)));

        app(RetireRuleVersion::class)->handle(
            $fixture['verifier'],
            $version,
            'Synthetic retirement.',
        );

        $this->assertSame(RuleVersionStatus::Retired, $version->refresh()->status);
    }

    public function test_rule_content_and_lifecycle_history_reject_raw_mutation_after_review(): void
    {
        $fixture = $this->fixture();
        $version = $this->draft($fixture, $this->template($fixture));
        app(SubmitRuleVersionForReview::class)->handle($fixture['preparer'], $version, 'Synthetic review.');

        try {
            DB::table('obligation_rule_versions')
                ->where('id', $version->id)
                ->update(['official_source_title' => 'Attempted rewrite']);
            $this->fail('Reviewed rule content must reject raw mutation.');
        } catch (QueryException) {
            // Expected database trigger.
        }

        $eventId = $version->events()->latest('id')->value('id');
        $this->expectException(QueryException::class);
        DB::table('obligation_rule_version_events')->where('id', $eventId)->delete();
    }

    public function test_foreign_firm_cannot_submit_rule_for_review(): void
    {
        $fixture = $this->fixture();
        $version = $this->draft($fixture, $this->template($fixture));
        $otherFirm = Firm::factory()->create();
        $otherAdmin = User::factory()->create();
        $otherMembership = $this->createFirmMembership($otherFirm, $otherAdmin, FirmRole::FirmAdministrator);
        $this->activateFirmMembership($otherMembership);
        $this->expectException(AuthorizationException::class);

        app(SubmitRuleVersionForReview::class)->handle(
            $otherAdmin,
            $version,
            'Synthetic foreign attempt.',
        );
    }

    public function test_livewire_governance_register_creates_and_submits_source_linked_rule(): void
    {
        $fixture = $this->fixture();

        Livewire::actingAs($fixture['preparer'])
            ->test(Index::class)
            ->set('templateKey', 'manual_tax_filing')
            ->set('templateName', 'Manual tax filing')
            ->set('obligationType', 'Manual filing')
            ->set('authority', 'Federal Tax Authority')
            ->call('createTemplate')
            ->assertHasNoErrors()
            ->set('applicabilityCriteria', 'Synthetic applicability confirmed by a human.')
            ->set('officialSourceTitle', 'FTA legislation register')
            ->set('officialSourceUrl', 'https://tax.gov.ae/en/legislation.aspx')
            ->set('sourcePublishedOn', '2026-07-22')
            ->set('changeSummary', 'Synthetic initial governance version.')
            ->call('draftVersion')
            ->assertHasNoErrors()
            ->assertSee('FTA legislation register')
            ->set('lifecycleReason', 'Synthetic UI review submission.')
            ->call('submitReview')
            ->assertHasNoErrors()
            ->assertSee('Under review');
    }

    /**
     * @param  array{preparer: User, preparerMembership: FirmMembership}  $fixture
     */
    private function template(array $fixture): ObligationRuleTemplate
    {
        $this->activateFirmMembership($fixture['preparerMembership']);

        return app(CreateRuleTemplate::class)->handle(
            $fixture['preparer'],
            'manual_tax_filing',
            'Manual tax filing',
            'Manual filing',
            'United Arab Emirates',
            'Federal Tax Authority',
        );
    }

    /**
     * @param  array{preparer: User, preparerMembership: FirmMembership}  $fixture
     */
    private function draft(
        array $fixture,
        ObligationRuleTemplate $template,
        string $sourceUrl = 'https://tax.gov.ae/en/legislation.aspx',
        string $calculatorKey = 'manual_date_passthrough',
    ): ObligationRuleVersion {
        $this->activateFirmMembership($fixture['preparerMembership']);

        return app(DraftRuleVersion::class)->handle(
            $fixture['preparer'],
            $template,
            '2026-01-01',
            null,
            'Synthetic applicability confirmed by a human.',
            $calculatorKey,
            [],
            'FTA legislation register',
            $sourceUrl,
            '2026-07-22',
            'Synthetic governed change.',
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
    private function publish(array $fixture, ObligationRuleVersion $version): ObligationRuleVersion
    {
        $this->activateFirmMembership($fixture['preparerMembership']);
        app(SubmitRuleVersionForReview::class)->handle($fixture['preparer'], $version, 'Synthetic review.');
        $this->activateFirmMembership($fixture['verifierMembership']);
        app(ApproveRuleVersion::class)->handle(
            $fixture['verifier'],
            $version->refresh(),
            '2026-07-28',
            'Synthetic approval.',
        );
        app(PublishRuleVersion::class)->handle(
            $fixture['verifier'],
            $version->refresh(),
            'Synthetic publication.',
        );

        return $version->refresh();
    }

    /**
     * @return array{
     *   firm: Firm,
     *   preparer: User,
     *   verifier: User,
     *   preparerMembership: FirmMembership,
     *   verifierMembership: FirmMembership
     * }
     */
    private function fixture(): array
    {
        config([
            'platform.features.compliance_operations.enabled' => true,
            'platform.features.compliance_operations.firm_ids' => [],
        ]);
        $firm = Firm::factory()->create();
        $preparer = User::factory()->create();
        $verifier = User::factory()->create();
        $preparerMembership = $this->createFirmMembership($firm, $preparer, FirmRole::FirmAdministrator);
        $verifierMembership = $this->createFirmMembership($firm, $verifier, FirmRole::Manager);
        $this->activateFirmMembership($preparerMembership);

        return compact('firm', 'preparer', 'verifier', 'preparerMembership', 'verifierMembership');
    }
}
