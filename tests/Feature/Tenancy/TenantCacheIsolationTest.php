<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Models\Firm;
use App\Tenancy\FirmContext;
use App\Tenancy\TenantCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use LogicException;
use Tests\TestCase;

class TenantCacheIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_cache_access_fails_without_an_active_firm_context(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('An active firm context is required.');

        app(TenantCache::class)->get('dashboard.stats');
    }

    public function test_same_logical_key_is_isolated_between_firms(): void
    {
        $firmA = Firm::factory()->create();
        $firmB = Firm::factory()->create();
        $context = app(FirmContext::class);
        $cache = app(TenantCache::class);

        $keyA = $context->runForFirm($firmA, function () use ($cache): string {
            $this->assertTrue($cache->put('dashboard.stats', ['due' => 2], 300));

            return $cache->key('dashboard.stats');
        });

        $keyB = $context->runForFirm($firmB, function () use ($cache): string {
            $this->assertNull($cache->get('dashboard.stats'));
            $this->assertTrue($cache->put('dashboard.stats', ['due' => 7], 300));

            return $cache->key('dashboard.stats');
        });

        $this->assertNotSame($keyA, $keyB);
        $this->assertStringContainsString("firm:{$firmA->id}", $keyA);
        $this->assertStringContainsString("firm:{$firmB->id}", $keyB);
        $this->assertStringContainsString('tenant:testing:', $keyA);

        $context->runForFirm(
            $firmA,
            fn () => $this->assertSame(['due' => 2], $cache->get('dashboard.stats')),
        );
        $context->runForFirm(
            $firmB,
            fn () => $this->assertSame(['due' => 7], $cache->get('dashboard.stats')),
        );
    }

    public function test_remember_and_forget_only_affect_the_active_firm(): void
    {
        $firmA = Firm::factory()->create();
        $firmB = Firm::factory()->create();
        $context = app(FirmContext::class);
        $cache = app(TenantCache::class);

        $context->runForFirm(
            $firmA,
            fn () => $cache->remember('members.count', 300, static fn (): int => 4),
        );
        $context->runForFirm(
            $firmB,
            fn () => $cache->remember('members.count', 300, static fn (): int => 9),
        );
        $context->runForFirm($firmA, fn () => $cache->forget('members.count'));

        $context->runForFirm(
            $firmA,
            fn () => $this->assertFalse($cache->has('members.count')),
        );
        $context->runForFirm(
            $firmB,
            fn () => $this->assertSame(9, $cache->get('members.count')),
        );
    }

    public function test_unsafe_cache_keys_are_rejected(): void
    {
        $firm = Firm::factory()->create();

        $this->expectException(InvalidArgumentException::class);

        app(FirmContext::class)->runForFirm(
            $firm,
            fn () => app(TenantCache::class)->get('../shared'),
        );
    }
}
