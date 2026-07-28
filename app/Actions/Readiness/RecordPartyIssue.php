<?php

declare(strict_types=1);

namespace App\Actions\Readiness;

use App\Actions\Audit\RecordAudit;
use App\Enums\Feature;
use App\Enums\ReadinessDataDomain;
use App\Enums\RuleVersionStatus;
use App\Models\DataQualityRuleVersion;
use App\Models\PartyFieldVersion;
use App\Models\PartyIssue;
use App\Models\PartyRecord;
use App\Models\User;
use App\Support\FeatureFlags;
use App\Tenancy\FirmContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final readonly class RecordPartyIssue
{
    public function __construct(private FirmContext $context, private FeatureFlags $flags, private RecordAudit $audit) {}

    public function handle(
        User $actor,
        PartyRecord $party,
        ?PartyFieldVersion $field,
        DataQualityRuleVersion $rule,
        mixed $evidenceNote,
    ): PartyIssue {
        $firmId = $this->context->firmId();
        if (! $this->flags->enabled(Feature::EInvoicingReadiness, $firmId)) {
            throw new AuthorizationException('E-invoicing readiness is not enabled.');
        }
        if ($party->firm_id !== $firmId || $rule->firm_id !== $firmId || ($field !== null && $field->firm_id !== $firmId)) {
            throw new AuthorizationException('All issue inputs must belong to the active firm.');
        }
        Gate::forUser($actor)->authorize('update', $party);
        /** @var array{evidence: string} $validated */
        $validated = Validator::make(['evidence' => $evidenceNote], ['evidence' => ['required', 'string', 'max:1000']])->validate();
        $rule->loadMissing('definition');
        if ($rule->status !== RuleVersionStatus::Published || $rule->definition->data_domain !== ReadinessDataDomain::PartyMaster) {
            throw ValidationException::withMessages(['ruleVersion' => 'Select a published party-master readiness rule.']);
        }
        if ($field !== null) {
            if ($field->party_record_id !== $party->id) {
                throw new AuthorizationException('The field does not belong to the selected party.');
            }
            if ($party->currentField($field->field_key->value)?->id !== $field->id) {
                throw ValidationException::withMessages(['fieldVersion' => 'An issue may reference only the current field version.']);
            }
        }
        $fieldIdentity = $field === null ? 'party' : $field->id;
        $issueKey = hash('sha256', implode('|', [$firmId, $party->id, $rule->id, $fieldIdentity]));

        return DB::transaction(function () use ($actor, $party, $field, $rule, $validated, $issueKey): PartyIssue {
            $issue = PartyIssue::query()->firstOrCreate(
                ['issue_key' => $issueKey],
                [
                    'party_record_id' => $party->id, 'party_field_version_id' => $field?->id,
                    'data_quality_rule_version_id' => $rule->id,
                    'severity_snapshot' => $rule->severity, 'behavior_snapshot' => $rule->behavior,
                    'explanation_snapshot' => $rule->explanation, 'remediation_snapshot' => $rule->remediation_guidance,
                    'evidence_note' => trim($validated['evidence']), 'recorded_by' => $actor->id, 'recorded_at' => now(),
                ],
            );
            if ($issue->wasRecentlyCreated) {
                $this->audit->handle('party_issue.recorded', $actor, $issue, [], [
                    'party_record_id' => $party->id, 'field_version_id' => $field?->id,
                    'rule_version_id' => $rule->id, 'severity' => $rule->severity->value, 'behavior' => $rule->behavior->value,
                ]);
            }

            return $issue->refresh();
        }, 3);
    }
}
