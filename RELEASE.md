# Compliance Operations V1 Release Runbook

## Release boundary

Compliance Operations V1 includes the client register, CSV onboarding, obligations, governed manual dates, workflows, checklists, assignments, document metadata, filing, payment and tax records, schedules, notifications, reports, safe exports and audit evidence.

It excludes automated statutory calculators, e-invoicing readiness operations, transmission, OCR, billing and accounting integrations.

## Required infrastructure

- PHP 8.3 with the extensions required by `composer check-platform-reqs`
- MySQL 8 compatible database
- HTTPS application host and valid certificate
- One isolated database, cache namespace, queue and private storage root for this application
- Outbound mail transport
- One supervised queue worker
- One scheduler invocation per minute
- Encrypted off-host database and private-file backups

The target host must be verified directly. This repository does not claim that a particular Hostinger plan supports long-running workers, cron frequency, private storage or the pinned runtime.

## Production environment

Create the production `.env` outside source control. At minimum:

```dotenv
APP_NAME="TBT Compliance Platform"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://compliance.thinkbeyondtax.com
APP_TIMEZONE=Asia/Dubai

DB_CONNECTION=mysql
QUEUE_CONNECTION=database
DB_QUEUE=platform
PLATFORM_QUEUE=platform
CACHE_STORE=database
CACHE_PREFIX=tbt-compliance-production
SESSION_DRIVER=database
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true

FEATURE_CLIENT_MASTER=true
FEATURE_COMPLIANCE_OPERATIONS=true
FEATURE_IMPORTS=false
FEATURE_EINVOICING_READINESS=false
FEATURE_AUDIT_VIEWER=true
```

Configure unique database, mail and storage credentials through the host's secret controls. Never copy development credentials or data.

## Pre-deployment gate

1. Review and merge only a green `main` branch.
2. Run `composer audit --locked` and `npm audit --audit-level=high --omit=dev`.
3. Run the complete test, static-analysis, formatting and frontend-build gates.
4. Confirm the production backup destination and restore procedure.
5. Confirm queue supervision and the scheduler cron entry.
6. Confirm the release environment values with `php artisan platform:release-check`.

## Deployment

```shell
composer install --no-dev --classmap-authoritative --no-interaction
npm ci
npm run build
php artisan down --retry=30 --secret="GENERATE-A-ONE-TIME-BYPASS"
php artisan migrate --force
php artisan optimize
php artisan queue:restart
php artisan up
```

Build assets in CI and deploy the resulting exact source state when the host supports artifact deployment. If Node is unavailable on the host, do not omit the build. Upload the verified `public/build` artifact created from the same commit.

## Post-deployment smoke checks

1. `GET /up` returns HTTP 200 over HTTPS.
2. `php artisan platform:release-check` passes every check.
3. `php artisan platform:operations-status` reports fresh scheduler and worker heartbeats after their first run.
4. An invited synthetic release-test user can sign in, select the correct firm and sign out.
5. Firm A cannot discover Firm B clients, work, reports, files or exports.
6. A synthetic client can be created manually and through CSV preview and commit.
7. A synthetic obligation can move through assignment, checklist, review, filing and payment states.
8. Schedule, dashboard, notification centre, report preview and export download load without errors.
9. `/readiness` shows coming soon and old readiness workspace paths return 404.
10. Application, worker and mail logs contain no unhandled release errors.

Delete the synthetic smoke-test records through an approved data procedure. Do not run destructive database shortcuts in production.

## Rollback

- Keep the previous application artifact and environment configuration available.
- Put the application in maintenance mode.
- Restore the previous artifact only when its schema is forward-compatible.
- Prefer a forward recovery migration after any committed schema change.
- Restore a verified backup only under the approved recovery procedure and record the incident.
- Restart workers and repeat all smoke checks before reopening access.

## Release evidence

Record the deployed commit, deployment time, operator, migration result, release-check output, operations-status output, smoke-test result, backup identifier and any accepted residual risk. Do not record credentials or customer data in release notes.
