<?php

declare(strict_types=1);

namespace App\Tenancy;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Filesystem\Factory;
use Illuminate\Contracts\Filesystem\Filesystem;
use LogicException;
use RuntimeException;

final readonly class TenantStorage
{
    public function __construct(
        private Factory $filesystems,
        private Repository $config,
        private TenantNamespace $namespace,
    ) {}

    public function path(string $path): string
    {
        return $this->namespace->storagePath($path);
    }

    public function put(string $path, string $contents): string
    {
        $scopedPath = $this->path($path);

        if (! $this->disk()->put($scopedPath, $contents, ['visibility' => 'private'])) {
            throw new RuntimeException('The tenant file could not be stored.');
        }

        return $scopedPath;
    }

    /**
     * @param  resource  $stream
     */
    public function writeStream(string $path, $stream): string
    {
        if (! is_resource($stream) || get_resource_type($stream) !== 'stream') {
            throw new LogicException('Tenant storage requires a readable stream resource.');
        }

        $scopedPath = $this->path($path);

        if (! $this->disk()->writeStream($scopedPath, $stream, ['visibility' => 'private'])) {
            throw new RuntimeException('The tenant file stream could not be stored.');
        }

        return $scopedPath;
    }

    public function get(string $path): string
    {
        $contents = $this->disk()->get($this->path($path));

        if (! is_string($contents)) {
            throw new RuntimeException('The tenant file could not be read.');
        }

        return $contents;
    }

    /**
     * @return resource
     */
    public function readStream(string $path)
    {
        $stream = $this->disk()->readStream($this->path($path));

        if (! is_resource($stream)) {
            throw new RuntimeException('The tenant file stream could not be opened.');
        }

        return $stream;
    }

    public function exists(string $path): bool
    {
        return $this->disk()->exists($this->path($path));
    }

    public function delete(string $path): bool
    {
        return $this->disk()->delete($this->path($path));
    }

    private function disk(): Filesystem
    {
        $diskName = $this->config->get('platform.storage.disk');
        $diskConfig = is_string($diskName)
            ? $this->config->get("filesystems.disks.{$diskName}")
            : null;

        if (
            ! is_string($diskName)
            || $diskName === ''
            || ! is_array($diskConfig)
            || ($diskConfig['visibility'] ?? null) !== 'private'
            || ($diskConfig['serve'] ?? false) !== false
        ) {
            throw new LogicException('Tenant storage requires a configured private, non-serving disk.');
        }

        return $this->filesystems->disk($diskName);
    }
}
