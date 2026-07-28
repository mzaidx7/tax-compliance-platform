<?php

declare(strict_types=1);

namespace App\Actions\Rules;

use App\Actions\Audit\RecordAudit;
use App\Enums\Feature;
use App\Enums\RuleVersionStatus;
use App\Models\Obligation;
use App\Models\ObligationRuleVersion;
use App\Models\ObligationRuleVersionEvent;
use App\Models\User;
use App\Support\FeatureFlags;
use App\Tenancy\FirmContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final readonly class RetireRuleVersion
{
    public function __construct(
        private FirmContext $firmContext,
        private FeatureFlags $featureFlags,
        private RecordAudit $recordAudit,
    ) {}

    public function handle(User $actor, ObligationRuleVersion $version, string $reason): ObligationRuleVersionEvent
    {
        $firmId = $this->firmContext->firm()->id;
        if (! $this->featureFlags->enabled(Feature::ComplianceOperations, $firmId)) {
            throw new AuthorizationException('Compliance operations are not enabled for this firm.');
        }
        if ($version->firm_id !== $firmId) {
            throw new AuthorizationException('The rule version does not belong to the active firm.');
        }
        Gate::forUser($actor)->authorize('create', Obligation::class);
        /** @var array{reason: string} $validated */
        $validated = Validator::make(['reason' => $reason], [
            'reason' => ['required', 'string', 'max:500'],
        ])->validate();

        return DB::transaction(function () use ($actor, $version, $validated): ObligationRuleVersionEvent {
            $locked = ObligationRuleVersion::query()->lockForUpdate()->findOrFail($version->id);
            if ($locked->status !== RuleVersionStatus::Published) {
                throw ValidationException::withMessages(['status' => 'Only a published rule can be retired.']);
            }
            $locked->update(['status' => RuleVersionStatus::Retired]);
            $event = ObligationRuleVersionEvent::query()->create([
                'obligation_rule_version_id' => $locked->id,
                'from_status' => RuleVersionStatus::Published,
                'to_status' => RuleVersionStatus::Retired,
                'acted_by' => $actor->id,
                'reason' => trim($validated['reason']),
                'occurred_at' => now('UTC'),
            ]);
            $this->recordAudit->handle(
                action: 'obligation_rule.retired',
                actor: $actor,
                auditable: $locked,
                before: ['status' => RuleVersionStatus::Published->value],
                after: ['status' => RuleVersionStatus::Retired->value],
                reason: trim($validated['reason']),
            );

            return $event;
        }, 3);
    }
}
