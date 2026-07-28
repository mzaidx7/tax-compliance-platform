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

final readonly class PublishRuleVersion
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
            if ($locked->status !== RuleVersionStatus::Approved) {
                throw ValidationException::withMessages(['status' => 'Only an approved rule version can be published.']);
            }

            try {
                $this->calculatorRegistry->get($locked->calculator_key)
                    ->validateParameters($locked->parameters);
            } catch (InvalidArgumentException $exception) {
                throw ValidationException::withMessages(['calculatorKey' => $exception->getMessage()]);
            }

            $now = now('UTC');
            $published = ObligationRuleVersion::query()
                ->where('obligation_rule_template_id', $locked->obligation_rule_template_id)
                ->where('status', RuleVersionStatus::Published)
                ->whereKeyNot($locked->id)
                ->lockForUpdate()
                ->get();

            foreach ($published as $previous) {
                $previous->update(['status' => RuleVersionStatus::Superseded]);
                ObligationRuleVersionEvent::query()->create([
                    'obligation_rule_version_id' => $previous->id,
                    'from_status' => RuleVersionStatus::Published,
                    'to_status' => RuleVersionStatus::Superseded,
                    'acted_by' => $actor->id,
                    'reason' => "Superseded by version {$locked->version}.",
                    'occurred_at' => $now,
                ]);
            }

            $locked->update(['status' => RuleVersionStatus::Published, 'published_at' => $now]);
            $event = ObligationRuleVersionEvent::query()->create([
                'obligation_rule_version_id' => $locked->id,
                'from_status' => RuleVersionStatus::Approved,
                'to_status' => RuleVersionStatus::Published,
                'acted_by' => $actor->id,
                'reason' => trim($validated['reason']),
                'occurred_at' => $now,
            ]);
            $this->recordAudit->handle(
                action: 'obligation_rule.published',
                actor: $actor,
                auditable: $locked,
                before: ['status' => RuleVersionStatus::Approved->value],
                after: [
                    'status' => RuleVersionStatus::Published->value,
                    'superseded_version_ids' => $published->pluck('id')->all(),
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
