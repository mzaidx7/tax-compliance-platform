<?php

declare(strict_types=1);

namespace App\Actions\Rules;

use App\Actions\Audit\RecordAudit;
use App\Enums\Feature;
use App\Enums\RuleVersionStatus;
use App\Models\Obligation;
use App\Models\ObligationRuleVersion;
use App\Models\User;
use App\Support\FeatureFlags;
use App\Support\OfficialSourceUrl;
use App\Tenancy\FirmContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final readonly class UpdateDraftRuleVersion
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
        ObligationRuleVersion $version,
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
        $firmId = $this->firmContext->firm()->id;
        if (! $this->featureFlags->enabled(Feature::ComplianceOperations, $firmId)) {
            throw new AuthorizationException('Compliance operations are not enabled for this firm.');
        }
        if ($version->firm_id !== $firmId) {
            throw new AuthorizationException('The rule version does not belong to the active firm.');
        }
        Gate::forUser($actor)->authorize('create', Obligation::class);

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

        return DB::transaction(function () use ($actor, $version, $validated): ObligationRuleVersion {
            $locked = ObligationRuleVersion::query()->lockForUpdate()->findOrFail($version->id);
            if ($locked->status !== RuleVersionStatus::Draft) {
                throw ValidationException::withMessages(['status' => 'Only a draft rule version can be edited.']);
            }
            $before = [
                'effective_from' => $locked->effective_from->toDateString(),
                'effective_to' => $locked->effective_to?->toDateString(),
                'calculator_key' => $locked->calculator_key,
                'official_source_url' => $locked->official_source_url,
            ];
            $locked->update($validated);
            $this->recordAudit->handle(
                action: 'obligation_rule.draft_updated',
                actor: $actor,
                auditable: $locked,
                before: $before,
                after: [
                    'effective_from' => $locked->effective_from->toDateString(),
                    'effective_to' => $locked->effective_to?->toDateString(),
                    'calculator_key' => $locked->calculator_key,
                    'official_source_url' => $locked->official_source_url,
                ],
                reason: $locked->change_summary,
            );

            return $locked->refresh();
        }, 3);
    }
}
