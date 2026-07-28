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
use App\Support\CalculatorRegistry;
use App\Support\FeatureFlags;
use App\Tenancy\FirmContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

final readonly class SubmitRuleVersionForReview
{
    public function __construct(
        private FirmContext $firmContext,
        private FeatureFlags $featureFlags,
        private CalculatorRegistry $calculatorRegistry,
        private RecordAudit $recordAudit,
    ) {}

    public function handle(User $actor, ObligationRuleVersion $version, string $reason): ObligationRuleVersionEvent
    {
        $this->authorize($actor, $version);
        /** @var array{reason: string} $validated */
        $validated = Validator::make(['reason' => $reason], [
            'reason' => ['required', 'string', 'max:500'],
        ])->validate();

        return DB::transaction(function () use ($actor, $version, $validated): ObligationRuleVersionEvent {
            $locked = ObligationRuleVersion::query()->lockForUpdate()->findOrFail($version->id);
            if ($locked->status !== RuleVersionStatus::Draft) {
                throw ValidationException::withMessages(['status' => 'Only a draft rule version can enter review.']);
            }

            try {
                $this->calculatorRegistry->get($locked->calculator_key)
                    ->validateParameters($locked->parameters);
            } catch (InvalidArgumentException $exception) {
                throw ValidationException::withMessages(['calculatorKey' => $exception->getMessage()]);
            }

            $locked->update(['status' => RuleVersionStatus::UnderReview]);
            $event = $this->event($locked, $actor, RuleVersionStatus::Draft, RuleVersionStatus::UnderReview, $validated['reason']);
            $this->audit($locked, $actor, 'obligation_rule.review_submitted', RuleVersionStatus::Draft, RuleVersionStatus::UnderReview, $validated['reason']);

            return $event;
        }, 3);
    }

    private function authorize(User $actor, ObligationRuleVersion $version): void
    {
        $firmId = $this->firmContext->firm()->id;
        if (! $this->featureFlags->enabled(Feature::ComplianceOperations, $firmId)) {
            throw new AuthorizationException('Compliance operations are not enabled for this firm.');
        }
        if ($version->firm_id !== $firmId) {
            throw new AuthorizationException('The rule version does not belong to the active firm.');
        }
        Gate::forUser($actor)->authorize('create', Obligation::class);
    }

    private function event(
        ObligationRuleVersion $version,
        User $actor,
        RuleVersionStatus $from,
        RuleVersionStatus $to,
        string $reason,
    ): ObligationRuleVersionEvent {
        return ObligationRuleVersionEvent::query()->create([
            'obligation_rule_version_id' => $version->id,
            'from_status' => $from,
            'to_status' => $to,
            'acted_by' => $actor->id,
            'reason' => trim($reason),
            'occurred_at' => now('UTC'),
        ]);
    }

    private function audit(
        ObligationRuleVersion $version,
        User $actor,
        string $action,
        RuleVersionStatus $from,
        RuleVersionStatus $to,
        string $reason,
    ): void {
        $this->recordAudit->handle(
            action: $action,
            actor: $actor,
            auditable: $version,
            before: ['status' => $from->value],
            after: ['status' => $to->value],
            reason: trim($reason),
        );
    }
}
