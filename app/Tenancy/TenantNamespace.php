<?php

declare(strict_types=1);

namespace App\Tenancy;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Str;
use InvalidArgumentException;
use LogicException;

final readonly class TenantNamespace
{
    public function __construct(
        private FirmContext $firmContext,
        private Repository $config,
    ) {}

    public function cacheKey(string $key): string
    {
        if (preg_match('/\A[A-Za-z0-9][A-Za-z0-9._:-]{0,199}\z/', $key) !== 1) {
            throw new InvalidArgumentException('Tenant cache keys must use 1 to 200 safe characters.');
        }

        return "tenant:{$this->environment()}:firm:{$this->firmId()}:{$key}";
    }

    public function storagePath(string $path): string
    {
        if (strlen($path) > 240 || str_contains($path, '\\') || str_contains($path, "\0")) {
            throw new InvalidArgumentException('The tenant storage path is invalid.');
        }

        $segments = explode('/', $path);

        foreach ($segments as $segment) {
            if (preg_match('/\A[A-Za-z0-9][A-Za-z0-9._-]*\z/', $segment) !== 1) {
                throw new InvalidArgumentException('Tenant storage paths must be relative and use safe path segments.');
            }
        }

        return "tenants/{$this->environment()}/{$this->firmId()}/{$path}";
    }

    private function environment(): string
    {
        $environment = Str::slug((string) $this->config->get('app.env'));

        if ($environment === '') {
            throw new LogicException('A valid application environment is required for tenant namespaces.');
        }

        return $environment;
    }

    private function firmId(): string
    {
        return (string) $this->firmContext->firm()->getKey();
    }
}
