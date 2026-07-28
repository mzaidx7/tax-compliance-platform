<?php

declare(strict_types=1);

namespace App\Actions\Readiness;

use App\Actions\Audit\RecordAudit;
use App\Enums\Feature;
use App\Enums\ReadinessDataDomain;
use App\Enums\RuleVersionStatus;
use App\Models\DataQualityRuleVersion;
use App\Models\InvoiceReadinessIssue;
use App\Models\InvoiceReadinessSample;
use App\Models\InvoiceSampleField;
use App\Models\User;
use App\Support\FeatureFlags;
use App\Tenancy\FirmContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final readonly class RecordInvoiceReadinessIssue
{
    public function __construct(private FirmContext $context, private FeatureFlags $flags, private RecordAudit $audit) {}

    public function handle(
        User $actor,
        InvoiceReadinessSample $sample,
        ?InvoiceSampleField $field,
        DataQualityRuleVersion $rule,
        mixed $evidenceNote,
    ): InvoiceReadinessIssue {
        $firmId = $this->context->firmId();
        if (! $this->flags->enabled(Feature::EInvoicingReadiness, $firmId)) {
            throw new AuthorizationException('E-invoicing readiness is not enabled.');
        }
        if ($sample->firm_id !== $firmId || $rule->firm_id !== $firmId || ($field !== null && $field->firm_id !== $firmId)) {
            throw new AuthorizationException('All issue inputs must belong to the active firm.');
        }
        Gate::forUser($actor)->authorize('update', $sample);
        /** @var array{evidence: string} $validated */
        $validated = Validator::make(['evidence' => $evidenceNote], ['evidence' => ['required', 'string', 'max:1000']])->validate();
        $rule->loadMissing('definition');
        if ($rule->status !== RuleVersionStatus::Published || $rule->definition->data_domain !== ReadinessDataDomain::InvoiceTransaction) {
            throw ValidationException::withMessages(['ruleVersion' => 'Select a published invoice-transaction readiness rule.']);
        }
        if ($field !== null && $field->invoice_readiness_sample_id !== $sample->id) {
            throw new AuthorizationException('The field does not belong to the selected invoice sample.');
        }
        $fieldIdentity = $field === null ? 'sample' : $field->id;
        $issueKey = hash('sha256', implode('|', [$firmId, $sample->id, $rule->id, $fieldIdentity]));

        return DB::transaction(function () use ($actor, $sample, $field, $rule, $validated, $issueKey): InvoiceReadinessIssue {
            $issue = InvoiceReadinessIssue::query()->firstOrCreate(
                ['issue_key' => $issueKey],
                [
                    'invoice_readiness_sample_id' => $sample->id,
                    'invoice_sample_field_id' => $field?->id,
                    'data_quality_rule_version_id' => $rule->id,
                    'severity_snapshot' => $rule->severity,
                    'behavior_snapshot' => $rule->behavior,
                    'explanation_snapshot' => $rule->explanation,
                    'remediation_snapshot' => $rule->remediation_guidance,
                    'evidence_note' => trim($validated['evidence']),
                    'recorded_by' => $actor->id,
                    'recorded_at' => now(),
                ],
            );
            if ($issue->wasRecentlyCreated) {
                $this->audit->handle('invoice_readiness_issue.recorded', $actor, $issue, [], [
                    'invoice_readiness_sample_id' => $sample->id,
                    'invoice_sample_field_id' => $field?->id,
                    'rule_version_id' => $rule->id,
                    'severity' => $rule->severity->value,
                    'behavior' => $rule->behavior->value,
                ]);
            }

            return $issue->refresh();
        }, 3);
    }
}
