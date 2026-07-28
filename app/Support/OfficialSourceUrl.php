<?php

declare(strict_types=1);

namespace App\Support;

final class OfficialSourceUrl
{
    public function allowed(string $url): bool
    {
        $scheme = parse_url($url, PHP_URL_SCHEME);
        $host = parse_url($url, PHP_URL_HOST);

        if ($scheme !== 'https' || ! is_string($host)) {
            return false;
        }

        $host = strtolower(rtrim($host, '.'));
        $allowedHosts = config('platform.rules.official_source_hosts', []);

        if (! is_array($allowedHosts)) {
            return false;
        }

        foreach ($allowedHosts as $allowedHost) {
            if (! is_string($allowedHost)) {
                continue;
            }

            $allowedHost = strtolower(rtrim($allowedHost, '.'));

            if ($host === $allowedHost || str_ends_with($host, '.'.$allowedHost)) {
                return true;
            }
        }

        return false;
    }
}
