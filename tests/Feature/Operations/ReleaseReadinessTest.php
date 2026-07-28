<?php

declare(strict_types=1);

namespace Tests\Feature\Operations;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ReleaseReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_responses_receive_the_release_security_headers(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Cross-Origin-Opener-Policy', 'same-origin');
    }

    public function test_release_check_fails_with_actionable_local_configuration(): void
    {
        $this->artisan('platform:release-check')
            ->expectsOutputToContain('APP_ENV must be production.')
            ->expectsOutputToContain('APP_URL must use HTTPS.')
            ->assertFailed();
    }

    public function test_release_check_passes_for_the_v1_production_contract(): void
    {
        app()->detectEnvironment(static fn (): string => 'production');
        config([
            'app.debug' => false,
            'app.url' => 'https://compliance.thinkbeyondtax.com',
            'app.key' => 'base64:'.base64_encode(random_bytes(32)),
            'queue.default' => 'database',
            'cache.default' => 'database',
            'session.driver' => 'database',
            'session.encrypt' => true,
            'session.secure' => true,
            'mail.default' => 'smtp',
            'platform.features.client_master.enabled' => true,
            'platform.features.compliance_operations.enabled' => true,
            'platform.features.e_invoicing_readiness.enabled' => false,
        ]);

        $this->artisan('platform:release-check')
            ->expectsOutputToContain('Compliance Operations V1 configuration is release-ready.')
            ->assertSuccessful();
    }
}
