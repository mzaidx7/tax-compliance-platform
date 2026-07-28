<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('platform:release-check {--json : Return machine-readable release readiness evidence}')]
#[Description('Check production configuration required for the Compliance Operations V1 release')]
final class CheckReleaseReadiness extends Command
{
    public function handle(): int
    {
        $checks = [
            $this->check('Production environment', app()->isProduction(), 'APP_ENV must be production.'),
            $this->check('Debug disabled', config('app.debug') === false, 'APP_DEBUG must be false.'),
            $this->check('HTTPS application URL', str_starts_with((string) config('app.url'), 'https://'), 'APP_URL must use HTTPS.'),
            $this->check('Application key', filled(config('app.key')), 'APP_KEY must be configured.'),
            $this->check('Database queue', config('queue.default') === 'database', 'QUEUE_CONNECTION must be database.'),
            $this->check('Persistent cache', ! in_array(config('cache.default'), ['array', 'null'], true), 'CACHE_STORE must be persistent.'),
            $this->check('Database sessions', config('session.driver') === 'database', 'SESSION_DRIVER must be database.'),
            $this->check('Encrypted sessions', config('session.encrypt') === true, 'SESSION_ENCRYPT must be true.'),
            $this->check('Secure session cookie', config('session.secure') === true, 'SESSION_SECURE_COOKIE must be true.'),
            $this->check('Operational mail transport', ! in_array(config('mail.default'), ['log', 'array'], true), 'MAIL_MAILER must deliver externally.'),
            $this->check('Client master enabled', config('platform.features.client_master.enabled') === true, 'FEATURE_CLIENT_MASTER must be true.'),
            $this->check('Compliance operations enabled', config('platform.features.compliance_operations.enabled') === true, 'FEATURE_COMPLIANCE_OPERATIONS must be true.'),
            $this->check('E-invoicing disabled', config('platform.features.e_invoicing_readiness.enabled') === false, 'FEATURE_EINVOICING_READINESS must remain false for V1.'),
        ];
        $passed = count(array_filter($checks, static fn (array $check): bool => $check['passed']));
        $ready = $passed === count($checks);

        if ($this->option('json')) {
            $this->line((string) json_encode([
                'ready' => $ready,
                'passed' => $passed,
                'total' => count($checks),
                'checks' => $checks,
            ], JSON_THROW_ON_ERROR));
        } else {
            foreach ($checks as $check) {
                $this->components->twoColumnDetail($check['name'], $check['passed'] ? '<fg=green>PASS</>' : '<fg=red>FAIL</>');
                if (! $check['passed']) {
                    $this->line("  {$check['recovery']}");
                }
            }

            $ready
                ? $this->components->info('Compliance Operations V1 configuration is release-ready.')
                : $this->components->error("Release check failed: {$passed}/".count($checks).' checks passed.');
        }

        return $ready ? self::SUCCESS : self::FAILURE;
    }

    /** @return array{name: string, passed: bool, recovery: string} */
    private function check(string $name, bool $passed, string $recovery): array
    {
        return compact('name', 'passed', 'recovery');
    }
}
