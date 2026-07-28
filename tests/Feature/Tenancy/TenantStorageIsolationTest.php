<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Models\Firm;
use App\Tenancy\FirmContext;
use App\Tenancy\TenantStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TenantStorageIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('tenant-private');
    }

    public function test_storage_access_fails_without_an_active_firm_context(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('An active firm context is required.');

        app(TenantStorage::class)->exists('exports/register.csv');
    }

    public function test_same_logical_path_is_isolated_between_firms(): void
    {
        $firmA = Firm::factory()->create();
        $firmB = Firm::factory()->create();
        $context = app(FirmContext::class);
        $storage = app(TenantStorage::class);

        $pathA = $context->runForFirm(
            $firmA,
            fn (): string => $storage->put('exports/register.csv', 'firm-a'),
        );
        $pathB = $context->runForFirm(
            $firmB,
            fn (): string => $storage->put('exports/register.csv', 'firm-b'),
        );

        $this->assertNotSame($pathA, $pathB);
        $this->assertSame("tenants/testing/{$firmA->id}/exports/register.csv", $pathA);
        $this->assertSame("tenants/testing/{$firmB->id}/exports/register.csv", $pathB);
        Storage::disk('tenant-private')->assertExists([$pathA, $pathB]);

        $context->runForFirm(
            $firmA,
            fn () => $this->assertSame('firm-a', $storage->get('exports/register.csv')),
        );
        $context->runForFirm(
            $firmB,
            fn () => $this->assertSame('firm-b', $storage->get('exports/register.csv')),
        );
    }

    public function test_deleting_one_firms_file_does_not_delete_another_firms_file(): void
    {
        $firmA = Firm::factory()->create();
        $firmB = Firm::factory()->create();
        $context = app(FirmContext::class);
        $storage = app(TenantStorage::class);

        $pathA = $context->runForFirm(
            $firmA,
            fn (): string => $storage->put('documents/checklist.txt', 'firm-a'),
        );
        $pathB = $context->runForFirm(
            $firmB,
            fn (): string => $storage->put('documents/checklist.txt', 'firm-b'),
        );

        $context->runForFirm(
            $firmA,
            fn (): bool => $storage->delete('documents/checklist.txt'),
        );

        Storage::disk('tenant-private')->assertMissing($pathA);
        Storage::disk('tenant-private')->assertExists($pathB);
    }

    #[DataProvider('unsafePaths')]
    public function test_unsafe_storage_paths_are_rejected(string $path): void
    {
        $firm = Firm::factory()->create();

        $this->expectException(InvalidArgumentException::class);

        app(FirmContext::class)->runForFirm(
            $firm,
            fn (): string => app(TenantStorage::class)->path($path),
        );
    }

    public function test_public_or_serving_disk_configuration_is_rejected(): void
    {
        $firm = Firm::factory()->create();
        config(['platform.storage.disk' => 'public']);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Tenant storage requires a configured private, non-serving disk.');

        app(FirmContext::class)->runForFirm(
            $firm,
            fn (): string => app(TenantStorage::class)->put('exports/register.csv', 'unsafe'),
        );
    }

    /**
     * @return array<string, array{string}>
     */
    public static function unsafePaths(): array
    {
        return [
            'absolute path' => ['/exports/register.csv'],
            'parent traversal' => ['../register.csv'],
            'nested traversal' => ['exports/../register.csv'],
            'windows separator' => ['exports\\register.csv'],
            'empty segment' => ['exports//register.csv'],
            'null byte' => ["exports/register\0.csv"],
        ];
    }
}
