<?php

declare(strict_types=1);

namespace App\Actions\Readiness;

use App\Actions\Audit\RecordAudit;
use App\Enums\DataQualityBehavior;
use App\Enums\DataQualitySeverity;
use App\Enums\Feature;
use App\Enums\RuleVersionStatus;
use App\Models\DataQualityRuleDefinition;
use App\Models\DataQualityRuleVersion;
use App\Models\User;
use App\Support\FeatureFlags;
use App\Support\OfficialSourceUrl;
use App\Tenancy\FirmContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final readonly class DraftDataQualityRuleVersion
{
    public function __construct(
        private FirmContext $firmContext,
        private FeatureFlags $featureFlags,
        private OfficialSourceUrl $officialSourceUrl,
        private RecordAudit $recordAudit,
    ) {}

    /** @param array<string, mixed> $input */
    public function handle(User $actor, DataQualityRuleDefinition $definition, array $input): DataQualityRuleVersion
    {
        $firmId = $this->firmContext->firmId();
        if (! $this->featureFlags->enabled(Feature::EInvoicingReadiness, $firmId)) {
            throw new AuthorizationException('E-invoicing readiness is not enabled.');
        }
        Gate::forUser($actor)->authorize('update', $definition);
        /** @var array{applicability: string, severity: string, behavior: string, explanation: string, remediation: string, sourceKind: string, sourceTitle: string, sourceUrl: string|null, formulaEffect: string, changeSummary: string} $validated */
        $validated = Validator::make([...$input, 'sourceUrl' => $input['sourceUrl'] ?? null], [
            'applicability' => ['required', 'string', 'max:4000'],
            'severity' => ['required', Rule::enum(DataQualitySeverity::class)],
            'behavior' => ['required', Rule::enum(DataQualityBehavior::class)],
            'explanation' => ['required', 'string', 'max:4000'],
            'remediation' => ['required', 'string', 'max:4000'],
            'sourceKind' => ['required', Rule::in(['official', 'internal'])],
            'sourceTitle' => ['required', 'string', 'max:255'],
            'sourceUrl' => ['nullable', 'url:https', 'max:2000'],
            'formulaEffect' => ['required', 'string', 'max:500'],
            'changeSummary' => ['required', 'string', 'max:500'],
        ])->validate();
        if ($validated['sourceKind'] === 'official' && ($validated['sourceUrl'] === null || ! $this->officialSourceUrl->allowed($validated['sourceUrl']))) {
            throw ValidationException::withMessages(['sourceUrl' => 'Official rules require an HTTPS source on a configured UAE government host.']);
        }

        return DB::transaction(function () use ($actor, $definition, $validated): DataQualityRuleVersion {
            $versionNumber = ((int) DataQualityRuleVersion::query()->where('data_quality_rule_definition_id', $definition->id)->lockForUpdate()->max('version')) + 1;
            $version = DataQualityRuleVersion::query()->create([
                'data_quality_rule_definition_id' => $definition->id, 'version' => $versionNumber,
                'status' => RuleVersionStatus::Draft, 'applicability_criteria' => trim($validated['applicability']),
                'severity' => $validated['severity'], 'behavior' => $validated['behavior'],
                'explanation' => trim($validated['explanation']), 'remediation_guidance' => trim($validated['remediation']),
                'source_kind' => $validated['sourceKind'], 'source_title' => trim($validated['sourceTitle']),
                'source_url' => $validated['sourceUrl'], 'formula_version_effect' => trim($validated['formulaEffect']),
                'prepared_by' => $actor->id, 'change_summary' => trim($validated['changeSummary']),
            ]);
            $this->recordAudit->handle('data_quality_rule.version_drafted', $actor, $version, [], [
                'definition_id' => $definition->id, 'version' => $versionNumber,
                'severity' => $version->severity->value, 'behavior' => $version->behavior->value,
            ]);

            return $version->refresh();
        }, 3);
    }
}
