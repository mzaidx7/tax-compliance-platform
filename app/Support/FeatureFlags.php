<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\Feature;
use App\Models\FeatureFlagOverride;
use Illuminate\Contracts\Config\Repository;

final class FeatureFlags
{
    /**
     * Per-firm override cache for the current request.
     *
     * @var array<string, array<string, bool>>
     */
    private array $overrides = [];

    public function __construct(private readonly Repository $config) {}

    public function enabled(Feature $feature, ?string $firmId = null): bool
    {
        if (is_string($firmId) && $firmId !== '') {
            $override = $this->firmOverrides($firmId)[$feature->value] ?? null;

            if ($override !== null) {
                return $override;
            }
        }

        return $this->enabledByConfig($feature, $firmId);
    }

    /**
     * Drop the cached overrides for one firm after a change so the next read is fresh.
     */
    public function forgetFirm(string $firmId): void
    {
        unset($this->overrides[$firmId]);
    }

    private function enabledByConfig(Feature $feature, ?string $firmId): bool
    {
        $settings = $this->config->get("platform.features.{$feature->value}");

        if (! is_array($settings)) {
            return false;
        }

        if (($settings['enabled'] ?? false) === true) {
            return true;
        }

        $allowedFirmIds = $settings['firm_ids'] ?? [];

        return is_string($firmId)
            && $firmId !== ''
            && is_array($allowedFirmIds)
            && in_array($firmId, $allowedFirmIds, true);
    }

    /**
     * Firm overrides are read by explicit firm id and bypass the tenant global
     * scope, so flag reads work even when no firm context is active, such as in
     * queued work that resolves its own firm.
     *
     * @return array<string, bool>
     */
    private function firmOverrides(string $firmId): array
    {
        if (! array_key_exists($firmId, $this->overrides)) {
            $map = [];

            foreach (
                FeatureFlagOverride::query()
                    ->withoutGlobalScopes()
                    ->where('firm_id', $firmId)
                    ->get(['feature', 'enabled']) as $override
            ) {
                $map[$override->feature->value] = $override->enabled;
            }

            $this->overrides[$firmId] = $map;
        }

        return $this->overrides[$firmId];
    }
}
