<?php

declare(strict_types=1);

namespace App\Actions\Rules;

use App\Actions\Audit\RecordAudit;
use App\Enums\Feature;
use App\Enums\RuleVersionStatus;
use App\Models\CalculatorGoldenCaseSet;
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

final readonly class ApproveRuleVersion
{
    public function __construct(
        private FirmContext $firmContext,
        private FeatureFlags $featureFlags,
        private CalculatorRegistry $calculatorRegistry,
        private RecordAudit $recordAudit,
    ) {}

    public function handle(
        User $actor,
        ObligationRuleVersion $version,
        string $sourceLastVerifiedOn,
        string $reason,
    ): ObligationRuleVersionEvent {
        $this->authorize($actor, $version);
        /** @var array{source_last_verified_on: string, reason: string} $validated */
        $validated = Validator::make(
            ['source_last_verified_on' => $sourceLastVerifiedOn, 'reason' => $reason],
            [
                'source_last_verified_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
                'reason' => ['required', 'string', 'max:500'],
            ],
        )->validate();

        return DB::transaction(function () use ($actor, $version, $validated): ObligationRuleVersionEvent {
            $locked = ObligationRuleVersion::query()->lockForUpdate()->findOrFail($version->id);
            if ($locked->status !== RuleVersionStatus::UnderReview) {
                throw ValidationException::withMessages(['status' => 'Only a rule under review can be approved.']);
            }
            if ($locked->prepared_by === $actor->id) {
                throw ValidationException::withMessages(['verifier' => 'The verifier must be different from the preparer.']);
            }
            if (
                $locked->source_published_on !== null
                && $validated['source_last_verified_on'] < $locked->source_published_on->toDateString()
            ) {
                throw ValidationException::withMessages([
                    'sourceLastVerifiedOn' => 'The verification date cannot precede the source publication date.',
                ]);
            }

            $calculator = $this->calculatorRegistry->get($locked->calculator_key);
            $goldenCaseSet = null;
            if ($calculator->isRegulatory()) {
                $goldenCaseSet = CalculatorGoldenCaseSet::query()
                    ->where('calculator_key', $locked->calculator_key)
                    ->where('status', 'approved')
                    ->orderByDesc('version')
                    ->first();
                if ($goldenCaseSet === null) {
                    throw ValidationException::withMessages([
                        'calculatorKey' => 'A regulatory calculator requires an approved golden-case set before rule approval.',
                    ]);
                }
            }

            $now = now('UTC');
            $locked->update([
                'status' => RuleVersionStatus::Approved,
                'source_last_verified_on' => $validated['source_last_verified_on'],
                'verified_by' => $actor->id,
                'verified_at' => $now,
                'approved_at' => $now,
                'calculator_golden_case_set_id' => $goldenCaseSet?->id,
            ]);
            $event = ObligationRuleVersionEvent::query()->create([
                'obligation_rule_version_id' => $locked->id,
                'from_status' => RuleVersionStatus::UnderReview,
                'to_status' => RuleVersionStatus::Approved,
                'acted_by' => $actor->id,
                'reason' => trim($validated['reason']),
                'occurred_at' => $now,
            ]);
            $this->recordAudit->handle(
                action: 'obligation_rule.approved',
                actor: $actor,
                auditable: $locked,
                before: ['status' => RuleVersionStatus::UnderReview->value],
                after: [
                    'status' => RuleVersionStatus::Approved->value,
                    'source_last_verified_on' => $validated['source_last_verified_on'],
                    'verified_by' => $actor->id,
                    'calculator_golden_case_set_id' => $goldenCaseSet?->id,
                ],
                reason: trim($validated['reason']),
            );

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
}
