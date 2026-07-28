# TBT Compliance Platform Agent Instructions

## Project Scope

This directory is the independent application boundary for the Think Beyond Tax Compliance Platform.

Keep platform source, documentation, non-secret configuration, tests and synthetic fixtures inside this directory.

Never store real customer data, production exports, uploaded documents, credentials, tokens, private keys or database backups in source control.

## Required Reading Order

Before material work:

1. Read this file.
2. Read `Think_Beyond_Tax_Compliance_Platform_Master_Plan.md`.
3. Read `MEMORY.md`.
4. Read `LARAVEL_BOOST_GUIDELINES.md` before changing Laravel application code.
5. Read relevant approved decision records and feature specifications when they exist.
6. Inspect Git status and recent history.

## Source Precedence

Use this order when instructions conflict:

1. Current product-owner instruction
2. This file
3. The approved master plan and approved architecture decision records
4. Relevant feature specifications
5. `MEMORY.md` as a current-state summary
6. Existing code and tests as implementation evidence

Do not treat `MEMORY.md` as authority to change approved scope.

## Website and Platform Separation

- Do not modify `../v2/` or `../legacy/` during platform work unless the product owner separately requests it.
- Do not import runtime code, packages, secrets, data or configuration from the website.
- Do not depend on `../v2/node_modules`.
- Do not share databases, authentication secrets, session cookies, storage or deployment pipelines with the public website.
- Keep website and platform changes in separate commits.
- Run platform commands from this directory.

Approved TBT brand assets and design decisions may be copied into platform-owned files with provenance. Impeccable and UI/UX Pro Max may be used as development tools, but they are not runtime dependencies.

## Product and Legal Boundaries

- Think Beyond Tax is currently a collective of independent UAE professionals, not an incorporated software company, licensed tax agency, FTA representative or Accredited Service Provider.
- Identify the actual licensed legal seller before paid services or external software sales.
- Do not claim FTA, Ministry of Finance, EmaraTax or ASP approval, affiliation or endorsement.
- Do not automate EmaraTax login, store EmaraTax passwords, bypass UAE Pass or scrape protected portal pages.
- Treat all regulated dates and rules as versioned, source-linked and human-verified.
- Use synthetic, anonymised or specifically approved data only.
- Never use em dashes in authored copy, documentation, code comments or commit messages.

## Architecture Baseline

- Build a modular Laravel monolith using Livewire and MySQL.
- Use a shared database with mandatory `firm_id` row scoping for SaaS.
- Keep global users and firm-scoped memberships.
- Keep work, filing, payment and risk states separate.
- Use named PHP calculators with immutable, versioned parameters for compliance rules.
- Use staged, idempotent and reconciled imports.
- Keep Livewire components thin and business rules in actions or services.
- Do not introduce microservices, event sourcing, CQRS, arbitrary rule expressions or a separate frontend application for the MVP.

Framework versions are selected only after the exact deployment environment is verified and must be pinned in lock files.

## Tenant Isolation

Every tenant-owned table must have a non-null `firm_id`.

Resolve firm context from authenticated membership, never from submitted identifiers.

Apply tenant scope to:

- Requests
- Livewire actions
- Policies
- Jobs
- Scheduled work
- Caches
- Files
- Imports and exports
- Notifications
- Reports

Every tenant-owned feature requires negative cross-tenant tests.

## Security and Data Handling

- Keep secrets in environment configuration or an approved secret manager.
- Use private storage for operational files.
- Sanitize spreadsheet exports against formula injection.
- Validate file type, size, row count, cell length and encoding.
- Do not store identity-document scans until the secure-file gate is approved.
- Do not log secrets or unnecessary personal data.
- Record significant actions in append-only audit history.
- Require a successful restore test before pilot use.

## ECC Workflow Discipline

Use the installed `everything-claude-code` ECC skill for project work.

Apply:

- Small, reviewable changes
- Feature plus tests plus documentation
- Explicit error handling
- An 80 percent or higher coverage target where practical
- Concise conventional commits in imperative mood
- Review and verification before handoff

Example commits:

- `feat(platform): add firm-scoped client records`
- `fix(compliance): prevent duplicate obligations`
- `test(tenancy): cover queued-job isolation`
- `docs(platform): record import reversal policy`

The installed skill was generated from the upstream JavaScript ECC repository. Its JavaScript-specific file naming, import and `*.test.js` guidance does not override Laravel, PHP, Livewire, Pest or PHPUnit conventions.

## Testing and Verification

Every feature or fix must include proportionate tests.

Before handoff:

1. Run focused tests.
2. Run the full relevant suite.
3. Run formatting and static analysis.
4. Run dependency and production-build checks where applicable.
5. Inspect the diff.
6. Update specifications, decisions and `MEMORY.md`.

Never report a check as passing unless it ran successfully in the current environment.

## Documentation and Memory

Update `MEMORY.md` after any material change to:

- Scope or objective
- Architecture
- Schema or migration state
- Implemented behavior
- Dependencies
- Verification results
- Deployment state
- Defects or blockers
- Next actions

Keep durable decisions in architecture decision records when that structure exists. Keep `MEMORY.md` concise and current. Never place secrets, customer data, full logs or chat transcripts in it.
