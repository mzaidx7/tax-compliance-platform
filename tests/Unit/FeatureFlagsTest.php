<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\Feature;
use App\Support\FeatureFlags;
use Illuminate\Config\Repository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeatureFlagsTest extends TestCase
{
    use RefreshDatabase;

    public function test_features_fail_closed_when_configuration_is_missing_or_malformed(): void
    {
        $flags = new FeatureFlags(new Repository([
            'platform' => [
                'features' => [
                    Feature::ClientMaster->value => 'enabled',
                ],
            ],
        ]));

        $this->assertFalse($flags->enabled(Feature::ClientMaster));
        $this->assertFalse($flags->enabled(Feature::Imports, 'firm-01'));
    }

    public function test_global_flag_enables_the_feature_for_every_firm(): void
    {
        $flags = $this->flags([
            'enabled' => true,
            'firm_ids' => [],
        ]);

        $this->assertTrue($flags->enabled(Feature::ClientMaster));
        $this->assertTrue($flags->enabled(Feature::ClientMaster, 'firm-01'));
    }

    public function test_allowlist_enables_only_the_selected_firms(): void
    {
        $flags = $this->flags([
            'enabled' => false,
            'firm_ids' => ['firm-01'],
        ]);

        $this->assertTrue($flags->enabled(Feature::ClientMaster, 'firm-01'));
        $this->assertFalse($flags->enabled(Feature::ClientMaster, 'firm-02'));
        $this->assertFalse($flags->enabled(Feature::ClientMaster));
    }

    public function test_non_boolean_global_value_fails_closed(): void
    {
        $flags = $this->flags([
            'enabled' => 'true',
            'firm_ids' => [],
        ]);

        $this->assertFalse($flags->enabled(Feature::ClientMaster));
    }

    /**
     * @param  array{enabled: mixed, firm_ids: mixed}  $settings
     */
    private function flags(array $settings): FeatureFlags
    {
        return new FeatureFlags(new Repository([
            'platform' => [
                'features' => [
                    Feature::ClientMaster->value => $settings,
                ],
            ],
        ]));
    }
}
