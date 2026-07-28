<?php

declare(strict_types=1);

namespace App\Actions\Readiness;

use App\Actions\Audit\RecordAudit;
use App\Enums\Feature;
use App\Enums\RuleVersionStatus;
use App\Models\DataQualityRuleEvent;
use App\Models\DataQualityRuleVersion;
use App\Models\User;
use App\Support\FeatureFlags;
use App\Tenancy\FirmContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final readonly class TransitionDataQualityRuleVersion
{
    public function __construct(private FirmContext $firmContext, private FeatureFlags $featureFlags, private RecordAudit $recordAudit) {}

    public function handle(
        User $actor,
        DataQualityRuleVersion $version,
        mixed $targetStatus,
        mixed $reason,
        mixed $sourceLastVerifiedOn = null,
    ): DataQualityRuleEvent {
        $firmId = $this->firmContext->firmId();
        if (! $this->featureFlags->enabled(Feature::EInvoicingReadiness, $firmId)) {
            throw new AuthorizationException('E-invoicing readiness is not enabled.');
        }
        if ($version->firm_id !== $firmId) {
            throw new AuthorizationException('The rule version does not belong to the active firm.');
        }
        $version->loadMissing('definition');
        Gate::forUser($actor)->authorize('update', $version->definition);
        /** @var array{target: string, reason: string, verifiedOn: string|null} $validated */
        $validated = Validator::make(['target' => $targetStatus, 'reason' => $reason, 'verifiedOn' => $sourceLastVerifiedOn], [
            'target' => ['required', Rule::enum(RuleVersionStatus::class)],
            'reason' => ['required', 'string', 'max:500'],
            'verifiedOn' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
        ])->validate();
        $target = RuleVersionStatus::from($validated['target']);

        return DB::transaction(function () use ($actor, $version, $target, $validated): DataQualityRuleEvent {
            $locked = DataQualityRuleVersion::query()->with('definition')->lockForUpdate()->findOrFail($version->id);
            $from = $locked->status;
            $allowed = match ($from) {
                RuleVersionStatus::Draft => $target === RuleVersionStatus::UnderReview,
                RuleVersionStatus::UnderReview => $target === RuleVersionStatus::Approved,
                RuleVersionStatus::Approved => $target === RuleVersionStatus::Published,
                RuleVersionStatus::Published => $target === RuleVersionStatus::Retired,
                default => false,
            };
            if (! $allowed) {
                throw ValidationException::withMessages(['target' => 'Invalid readiness rule lifecycle transition.']);
            }
            if ($target === RuleVersionStatus::Approved) {
                if ($locked->prepared_by === $actor->id) {
                    throw ValidationException::withMessages(['verifier' => 'The verifier must be different from the preparer.']);
                }
                if ($validated['verifiedOn'] === null) {
                    throw ValidationException::withMessages(['sourceLastVerifiedOn' => 'Source verification date is required for approval.']);
                }
            }

            $now = now('UTC');
            $attributes = ['status' => $target];
            if ($target === RuleVersionStatus::Approved) {
                $attributes += ['verified_by' => $actor->id, 'source_last_verified_on' => $validated['verifiedOn'], 'verified_at' => $now, 'approved_at' => $now];
            }
            if ($target === RuleVersionStatus::Published) {
                $attributes['published_at'] = $now;
                $prior = DataQualityRuleVersion::query()
                    ->where('data_quality_rule_definition_id', $locked->data_quality_rule_definition_id)
                    ->where('status', RuleVersionStatus::Published)
                    ->lockForUpdate()
                    ->first();
                if ($prior !== null) {
                    $prior->update(['status' => RuleVersionStatus::Superseded]);
                    $this->event($prior, $actor, RuleVersionStatus::Published, RuleVersionStatus::Superseded, 'Superseded by a newly published version.');
                }
            }
            $locked->update($attributes);
            $event = $this->event($locked, $actor, $from, $target, trim($validated['reason']));
            $this->recordAudit->handle(
                action: 'data_quality_rule.status_changed', actor: $actor, auditable: $locked,
                before: ['status' => $from->value], after: ['status' => $target->value],
                reason: trim($validated['reason']),
            );

            return $event;
        }, 3);
    }

    private function event(
        DataQualityRuleVersion $version,
        User $actor,
        RuleVersionStatus $from,
        RuleVersionStatus $to,
        string $reason,
    ): DataQualityRuleEvent {
        return DataQualityRuleEvent::query()->create([
            'data_quality_rule_version_id' => $version->id, 'from_status' => $from, 'to_status' => $to,
            'acted_by' => $actor->id, 'reason' => $reason, 'occurred_at' => now('UTC'),
        ]);
    }
}
