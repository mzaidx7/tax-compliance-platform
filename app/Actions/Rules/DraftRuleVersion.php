<?php

declare(strict_types=1);

namespace App\Actions\Rules;

use App\Actions\Audit\RecordAudit;
use App\Enums\Feature;
use App\Enums\RuleVersionStatus;
use App\Models\Obligation;
use App\Models\ObligationRuleTemplate;
use App\Models\ObligationRuleVersion;
use App\Models\ObligationRuleVersionEvent;
use App\Models\User;
use App\Support\FeatureFlags;
use App\Support\OfficialSourceUrl;
use App\Tenancy\FirmContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final readonly class DraftRuleVersion
{
    public function __construct(
        private FirmContext $firmContext,
        private FeatureFlags $featureFlags,
        private OfficialSourceUrl $officialSourceUrl,
        private RecordAudit $recordAudit,
    ) {}

    /**
     * @param  array<string, mixed>  $parameters
     */
    public function handle(
        User $actor,
        ObligationRuleTemplate $template,
        string $effectiveFrom,
        ?string $effectiveTo,
        string $applicabilityCriteria,
        string $calculatorKey,
        array $parameters,
        string $officialSourceTitle,
        string $officialSourceUrl,
        ?string $sourcePublishedOn,
        string $changeSummary,
    ): ObligationRuleVersion {
        $firmId = $this->authorize($actor, $template);
        $validated = $this->validate(
            $effectiveFrom,
            $effectiveTo,
            $applicabilityCriteria,
            $calculatorKey,
            $parameters,
            $officialSourceTitle,
            $officialSourceUrl,
            $sourcePublishedOn,
            $changeSummary,
        );

        return DB::transaction(function () use ($actor, $template, $validated): ObligationRuleVersion {
            $latest = ObligationRuleVersion::query()
                ->whereBelongsTo($template, 'template')
                ->lockForUpdate()
                ->max('version');
            $versionNumber = is_numeric($latest) ? (int) $latest + 1 : 1;
            $version = ObligationRuleVersion::query()->create([
                'obligation_rule_template_id' => $template->id,
                'version' => $versionNumber,
                'status' => RuleVersionStatus::Draft,
                ...$validated,
                'source_last_verified_on' => null,
                'prepared_by' => $actor->id,
                'verified_by' => null,
                'verified_at' => null,
                'approved_at' => null,
                'published_at' => null,
            ]);
            ObligationRuleVersionEvent::query()->create([
                'obligation_rule_version_id' => $version->id,
                'from_status' => null,
                'to_status' => RuleVersionStatus::Draft,
                'acted_by' => $actor->id,
                'reason' => 'Initial draft created.',
                'occurred_at' => now('UTC'),
            ]);
            $this->recordAudit->handle(
                action: 'obligation_rule.version_drafted',
                actor: $actor,
                auditable: $version,
                after: [
                    'template_id' => $template->id,
                    'version' => $versionNumber,
                    'calculator_key' => $version->calculator_key,
                    'official_source_url' => $version->official_source_url,
                ],
            );

            return $version->refresh();
        }, 3);
    }

    /**
     * @param  array<string, mixed>  $parameters
     * @return array{
     *   effective_from: string,
     *   effective_to: string|null,
     *   applicability_criteria: string,
     *   calculator_key: string,
     *   parameters: array<string, mixed>,
     *   official_source_title: string,
     *   official_source_url: string,
     *   source_published_on: string|null,
     *   change_summary: string
     * }
     */
    private function validate(
        string $effectiveFrom,
        ?string $effectiveTo,
        string $applicabilityCriteria,
        string $calculatorKey,
        array $parameters,
        string $officialSourceTitle,
        string $officialSourceUrl,
        ?string $sourcePublishedOn,
        string $changeSummary,
    ): array {
        /** @var array{
         *   effective_from: string,
         *   effective_to: string|null,
         *   applicability_criteria: string,
         *   calculator_key: string,
         *   parameters: array<string, mixed>,
         *   official_source_title: string,
         *   official_source_url: string,
         *   source_published_on: string|null,
         *   change_summary: string
         * } $validated
         */
        $validated = Validator::make(
            [
                'effective_from' => $effectiveFrom,
                'effective_to' => $effectiveTo,
                'applicability_criteria' => trim($applicabilityCriteria),
                'calculator_key' => trim($calculatorKey),
                'parameters' => $parameters,
                'official_source_title' => trim($officialSourceTitle),
                'official_source_url' => trim($officialSourceUrl),
                'source_published_on' => $sourcePublishedOn,
                'change_summary' => trim($changeSummary),
            ],
            [
                'effective_from' => ['required', 'date_format:Y-m-d'],
                'effective_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:effective_from'],
                'applicability_criteria' => ['required', 'string', 'max:4000'],
                'calculator_key' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9_]+$/'],
                'parameters' => ['array'],
                'official_source_title' => ['required', 'string', 'max:255'],
                'official_source_url' => ['required', 'url:https', 'max:2000'],
                'source_published_on' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
                'change_summary' => ['required', 'string', 'max:500'],
            ],
        )->validate();

        if (! $this->officialSourceUrl->allowed($validated['official_source_url'])) {
            throw ValidationException::withMessages([
                'officialSourceUrl' => 'Use an HTTPS source on a configured official UAE government host.',
            ]);
        }

        return $validated;
    }

    private function authorize(User $actor, ObligationRuleTemplate $template): string
    {
        $firmId = $this->firmContext->firm()->id;
        if (! $this->featureFlags->enabled(Feature::ComplianceOperations, $firmId)) {
            throw new AuthorizationException('Compliance operations are not enabled for this firm.');
        }
        if ($template->firm_id !== $firmId) {
            throw new AuthorizationException('The rule template does not belong to the active firm.');
        }
        Gate::forUser($actor)->authorize('create', Obligation::class);

        return $firmId;
    }
}
