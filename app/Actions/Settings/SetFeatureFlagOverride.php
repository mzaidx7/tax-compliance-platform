<?php

declare(strict_types=1);

namespace App\Actions\Settings;

use App\Actions\Audit\RecordAudit;
use App\Enums\Feature;
use App\Models\FeatureFlagOverride;
use App\Models\User;
use App\Support\FeatureFlags;
use App\Tenancy\FirmContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Record a firm's deliberate decision to enable or disable one feature.
 *
 * A flag change only shifts feature availability. It never bypasses a policy:
 * every guarded action still enforces its own permission and firm scope.
 */
final readonly class SetFeatureFlagOverride
{
    public function __construct(
        private FirmContext $firmContext,
        private FeatureFlags $featureFlags,
        private RecordAudit $recordAudit,
    ) {}

    /**
     * @throws AuthorizationException
     */
    public function handle(
        User $actor,
        Feature $feature,
        bool $enabled,
        string $reason,
    ): FeatureFlagOverride {
        $firmId = $this->firmContext->firm()->id;

        Gate::forUser($actor)->authorize('update', FeatureFlagOverride::class);

        /** @var array{feature: string, reason: string} $validated */
        $validated = Validator::make(
            ['feature' => $feature->value, 'reason' => $reason],
            [
                'feature' => ['required', Rule::enum(Feature::class)],
                'reason' => ['required', 'string', 'max:500'],
            ],
        )->validate();

        return DB::transaction(function () use ($actor, $feature, $enabled, $validated, $firmId): FeatureFlagOverride {
            $existing = FeatureFlagOverride::query()
                ->where('feature', $feature)
                ->lockForUpdate()
                ->first();
            $previousEnabled = $existing instanceof FeatureFlagOverride ? $existing->enabled : null;
            $createdBy = $existing instanceof FeatureFlagOverride ? $existing->created_by : $actor->id;

            $override = FeatureFlagOverride::query()->updateOrCreate(
                ['feature' => $feature],
                [
                    'enabled' => $enabled,
                    'updated_by' => $actor->id,
                    'created_by' => $createdBy,
                ],
            );

            $this->recordAudit->handle(
                action: 'feature_flag.overridden',
                actor: $actor,
                auditable: $override,
                before: ['feature' => $feature->value, 'enabled' => $previousEnabled],
                after: ['feature' => $feature->value, 'enabled' => $enabled],
                reason: trim($validated['reason']),
            );

            $this->featureFlags->forgetFirm($firmId);

            return $override->refresh();
        }, 3);
    }
}
