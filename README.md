# TBT Compliance Platform

Release 1 is an invitation-only, firm-scoped compliance operations platform built with Laravel, Livewire, Flux and MySQL.

It covers client records, manually verified obligations, work assignments, checklists, review decisions, filing, payment and tax records, document expiry, schedules, notifications, operational reports and append-only audit evidence.

E-invoicing readiness is intentionally separated as a future release. The Release 1 interface exposes a coming-soon page and no operational readiness routes.

## Local setup

```powershell
composer install
npm.cmd install
Copy-Item .env.example .env
& '.\.tools\php-8.3.32\php.exe' artisan key:generate
& '.\.tools\php-8.3.32\php.exe' artisan migrate --seed
npm.cmd run build
```

Only synthetic data may be used in development.

## Verification

```powershell
& '.\.tools\php-8.3.32\php.exe' vendor/bin/pint --test
& '.\.tools\php-8.3.32\php.exe' vendor/bin/phpstan analyse --memory-limit=1G
& '.\.tools\php-8.3.32\php.exe' artisan test --compact
npm.cmd run build
```

Run the production configuration gate after setting the real deployment environment:

```shell
php artisan platform:release-check
```

See [RELEASE.md](RELEASE.md) for the release checklist, [OPERATIONS.md](OPERATIONS.md) for worker and scheduler operations, and [TENANCY.md](TENANCY.md) for data-boundary rules.
