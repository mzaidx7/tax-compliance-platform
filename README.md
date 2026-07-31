# TBT Compliance Platform

Release 1 is an invitation-only, firm-scoped compliance operations platform built with Laravel, Livewire, Flux and MySQL.

It covers client records, calculated VAT and Corporate Tax schedules, work assignments, checklists, review decisions, filing, payment and tax records, document expiry, client reminders, schedules, operational reports and append-only audit evidence.

E-invoicing readiness is intentionally separated from this final Compliance Operations release. Its dormant code and data structures are retained for possible later work, but the interface exposes no e-invoicing navigation, page or feature control.

## Local setup

```powershell
composer install
npm.cmd install
Copy-Item .env.example .env
& '.\.tools\php-8.3.32\php.exe' artisan key:generate
& '.\.tools\php-8.3.32\php.exe' artisan migrate --seed
npm.cmd run build
& '.\.tools\php-8.3.32\php.exe' artisan serve --host=127.0.0.1 --port=8000
```

Only synthetic data may be used in development.

Use `artisan serve` for local testing. A plain `php -S` command pointed at `public` does not route Livewire and Flux asset requests through Laravel, so menus, modals, filters and calendar controls will render without working.

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
