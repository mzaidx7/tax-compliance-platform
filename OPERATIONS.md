# Platform Operations Baseline

## Purpose

This runbook defines the portable queue, scheduler, health-check and feature-flag contract for the TBT Compliance Platform.

It does not confirm compatibility with a specific Hostinger plan. Verify the target host's PHP CLI, cron, long-running process, database and storage limits before deployment.

## Required Runtime Configuration

Use platform-owned values and do not share queue, cache, session or database infrastructure with the public TBT website.

```dotenv
QUEUE_CONNECTION=database
DB_QUEUE=platform
PLATFORM_QUEUE=platform
PLATFORM_HEARTBEAT_FRESH_FOR_SECONDS=300
PLATFORM_SCHEDULED_FIRM_BATCH_SIZE=100
PLATFORM_SCHEDULED_WORK_HEARTBEAT_TTL_SECONDS=86400
CACHE_STORE=database
CACHE_PREFIX=tbt-compliance
```

The database queue and cache migrations must be applied before workers or the scheduler start.

## Queue Worker

The initial portable worker command is:

```shell
php artisan queue:work database --queue=platform --sleep=3 --tries=3 --timeout=60 --max-time=3600
```

Production must supervise and restart the worker. A deployment should run:

```shell
php artisan queue:restart
```

Do not use `queue:listen` as the production baseline.

Tracked operational notifications use three attempts with 10, 30 and 60 second backoff values and a 60 second timeout. Each failed attempt is retained with a safe failure category. After Laravel exhausts the job, the notification request receives a terminal failed status and an audit event. Investigate terminal failures before retrying or creating a new business-occurrence key.

## Scheduler

Laravel requires one cron invocation every minute:

```cron
* * * * * cd /absolute/path/to/platform && php artisan schedule:run
```

The application schedule:

- Records a scheduler heartbeat every minute.
- Dispatches a queue-worker heartbeat every minute.
- Discovers active firms every five minutes in bounded database chunks.
- Dispatches one encrypted and unique firm-aware scheduled-work job per active firm and generation slot.
- Prevents overlapping heartbeat tasks.
- Uses shared cache locks so only one server dispatches each task.

The target environment must use a shared lock-capable cache store before multiple application servers are introduced.

The firm dispatcher accepts no firm identifier. It discovers active firms from the database, and each queued job carries only its trusted firm identity and deterministic generation slot. The worker establishes firm context through queue middleware and restores any previous context after processing. A firm suspended before processing fails closed.

Each per-firm heartbeat is stored in the tenant cache under its deterministic generation key. Re-running the same slot overwrites the same cache record, while Laravel's unique job lock prevents duplicate queued copies while the original is pending or running. The job also generates idempotent document-expiry reminder evidence using the firm's local calendar date. Reminder uniqueness prevents repeated five-minute schedule slots from creating duplicate evidence for the same document, kind and day. Future obligation jobs must use the same firm-aware middleware, bounded work units and deterministic generation keys.

## Health Check

Human-readable status:

```shell
php artisan platform:operations-status
```

Machine-readable status:

```shell
php artisan platform:operations-status --json
```

The command exits successfully only when both scheduler and queue-worker heartbeats are fresh. A missing or stale heartbeat returns a failure exit code.

## Feature Flags

All incomplete modules are disabled by default:

```dotenv
FEATURE_CLIENT_MASTER=false
FEATURE_COMPLIANCE_OPERATIONS=false
FEATURE_IMPORTS=false
FEATURE_EINVOICING_READINESS=false
FEATURE_AUDIT_VIEWER=false
```

A feature can be enabled globally or for a comma-separated list of firm ULIDs:

```dotenv
FEATURE_CLIENT_MASTER_FIRM_IDS=01EXAMPLEFIRMULID,01SECONDTESTFIRM
```

Feature flags control rollout only. Every protected action must still enforce authentication, firm context, policy and permission checks.

After changing environment configuration, rebuild Laravel's configuration cache:

```shell
php artisan config:cache
```

## Recovery

- Disable an incomplete feature by setting its global flag to `false` and clearing its firm allowlist.
- Rebuild configuration cache after any flag change.
- Restart queue workers after deploying job or configuration changes.
- Use `php artisan schedule:clear-cache` only when a stale overlap lock remains after an interrupted task.
- Investigate failed jobs before retrying them. Never discard failed tenant work without an auditable recovery decision.

## Local Synthetic Restore Proof

Run the portable development proof with:

```shell
php artisan platform:prove-backup-restore --synthetic-only
```

The command is disabled in production and refuses to run without the explicit synthetic-only confirmation. It creates an isolated temporary SQLite source, applies the real migrations, inserts generated fixtures for two firms, copies a database backup and a synthetic private file, restores both to separate temporary artifacts, and verifies:

- Schema version and migration count.
- Critical table record counts and a SHA-256 record manifest.
- SQLite integrity and foreign-key consistency.
- Password-hash authentication against a synthetic account.
- Firm membership and audit-log isolation.
- Queue, failed-job and cache table availability.
- Private-file SHA-256 integrity.
- Removal of every temporary source, backup and restored artifact.

The command does not read or mutate the configured application database.

This is a local engineering proof, not production recovery approval. It does not prove MySQL backup tooling, encrypted off-host retention, host-level configuration recovery, worker supervision, production private-file volumes, recovery point objectives or recovery time objectives. Repeat the runbook against the selected production-equivalent infrastructure before launch and record the approved recovery targets separately.
