# TBT Compliance Platform Memory

## Handoff Metadata

- Last updated: 2026-07-28
- Updated by: Codex GPT-5.6
- Platform repository status: independent repository on `main`, remote `mzaidx7/tax-compliance-platform`
- Current milestone: Compliance Operations V1 repository release candidate completed

## Current Objective
Create a UAE-focused compliance operations and e-invoicing readiness platform under a TBT subdomain while keeping its application, data, deployment and release lifecycle separate from the public TBT website.

The immediate objective is to configure the real production host, pass `platform:release-check`, deploy the verified commit, and complete post-deployment browser and operational smoke checks. E-invoicing remains preserved behind its coming-soon boundary.

## Canonical Files

- `AGENTS.md`: durable safety and execution rules
- `LARAVEL_BOOST_GUIDELINES.md`: installed framework-specific implementation guidance
- `Think_Beyond_Tax_Compliance_Platform_Master_Plan.md`: canonical product and implementation plan
- `PRODUCT.md`: platform product truth for interface work
- `DESIGN.md`: platform operational design system
- `OPERATIONS.md`: queue, scheduler, health and feature-flag runbook
- `TENANCY.md`: tenant database, cache and private-file contract
- `MEMORY.md`: current-state handoff
- `CLAUDE.md`: Claude Code entrypoint

## Non-Negotiable Constraints

- Keep platform work inside `TBT Compliance Platform/`.
- Do not modify or depend on the public website runtime unless separately requested.
- Do not store real customer data, credentials, private documents, imports, exports or backups in Git.
- Use synthetic, anonymised or specifically approved data during development.
- Maintain strict firm-level tenant isolation.
- Do not claim FTA, Ministry of Finance, EmaraTax or ASP approval or affiliation.
- Do not automate EmaraTax login or filing.
- Do not store sensitive identity-document scans in the MVP.
- Keep compliance rules versioned, source-linked and human-verified.
- Never use em dashes in authored project content.
- Apply ECC workflow discipline while Laravel and PHP conventions govern implementation.

## Confirmed Product Decisions

- The platform will be built.
- It will sit under the TBT domain, with `app.thinkbeyondtax.com` as the initial application target.
- It is a separate product boundary from the marketing website.
- One core codebase will support hosted SaaS and dedicated deployments.
- The initial architecture is a modular Laravel monolith using Livewire and MySQL.
- The initial SaaS tenancy model is a shared database with mandatory `firm_id` scoping.
- Dedicated deployments retain tenant controls in a separate environment and database.
- Global users access firms through firm-scoped memberships.
- Work, filing, payment and risk states remain separate.
- Compliance calculations use named PHP calculators and immutable rule versions.
- Imports use staging, preview, approval, idempotency and row reconciliation.
- Release 1 is the internal Compliance Operations MVP. E-invoicing readiness is a separately tracked future release and must not delay publishing Release 1.
- The calendar is secondary to operational queues.
- The MVP stores document metadata only.
- Email is the only required external notification channel for the MVP.
- The platform will not provide unofficial EmaraTax automation or e-invoice transmission.
- Access is invitation-only. Public self-registration and passkey authentication are disabled.
- Email verification, password reset, password confirmation and two-factor authentication remain enabled.

## Architecture Summary

- Modular monolith modules: Identity, Tenancy, Clients, Compliance, Workflows, Documents, Imports, Readiness, Notifications, Reporting, Audit, Commercial and Platform Administration.
- Thin Livewire presentation components.
- Business actions and services own rules.
- MySQL migrations own schema history.
- Database-backed queues and one scheduler cron entry are the initial portable baseline.
- Statutory dates use UAE-local date values.
- Event timestamps use UTC and display in the firm timezone.
- Published rules, workflows and checklists are immutable and versioned.
- Significant actions create append-only audit history.
- Production, staging, demo and local development remain isolated.

## Current Implementation State

- The Laravel application is scaffolded directly in this directory.
- Runtime baseline: PHP 8.3.32, Laravel 13.22.0, Livewire 4.1, Flux 2.13.1 and Tailwind CSS 4.
- Development baseline: Composer 2.10.2, PHPUnit 12, Larastan 3, Laravel Pint and Laravel Boost 2.4.13.
- Local development uses SQLite. `.env.example` targets MySQL for deployed environments.
- The application timezone defaults to `Asia/Dubai`.
- Initial framework migrations for users, cache, jobs and two-factor authentication exist and have run locally.
- `firms` and `firm_users` use ULID primary keys, foreign keys, tenant-aware uniqueness and lifecycle status fields.
- Firm roles and firm and membership lifecycle values are explicit PHP backed enums.
- Firm membership is stored separately from global user identity.
- `FirmContext` is request-scoped and can only activate an active firm through an active membership or trusted system work.
- Dashboard requests require authenticated, verified and resolved firm context.
- One active membership is selected automatically. Multi-firm users can select an active firm through a server-validated switch route.
- Tenant-owned Eloquent queries fail closed without firm context and automatically scope membership queries to the active firm.
- Tenant-owned saves require firm context and reject movement to another firm.
- Tenant-owned cache values use environment and active-firm namespaces and fail closed without firm context.
- Tenant-owned files use generated environment and active-firm paths on a private, non-serving disk.
- CSV exports enforce bounded UTF-8 content, spreadsheet formula neutralization, tenant-private storage, checksums and audit metadata.
- Presentation exports are distinct from future lossless re-import formats.
- Operational notifications persist deterministic requests and immutable delivery attempts while retaining encrypted queues and delivery-time membership checks.
- Invitation notification payloads are encrypted while retaining their separate pre-membership routing path.
- Firm-aware queued jobs establish and restore firm context through job middleware.
- The global scheduler discovers active firms in bounded chunks and dispatches encrypted, unique firm jobs with deterministic generation keys.
- Per-firm scheduled-work heartbeats use tenant cache namespaces and fail closed when a firm is suspended before processing.
- Authentication is invitation-only. Public registration and passkey routes and interface entry points are absent.
- Email verification protects the dashboard. Password reset, password confirmation and two-factor authentication are available.
- Baseline firm permissions, the membership policy, invitation actions and append-only audit infrastructure are implemented and tested.
- Multi-firm users have a responsive firm chooser and persistent firm switcher.
- Firm administrators have a Livewire access register with scoped member and pending-invitation lists.
- Firm administrators can change another member's role, suspend or reactivate access and permanently revoke a membership with a required reason.
- Administrators cannot change their own role or access state, final-administrator safeguards are enforced and revoked memberships are terminal.
- Pending invitations can be resent with a rotated token and renewed 72-hour expiry, or revoked with a required reason.
- Invitation email notifications are queued after commit and contain a time-limited acceptance link.
- New invitees can create a verified account through the invitation, while existing users are returned through sign-in before acceptance.
- Incomplete modules are protected by named, fail-closed feature flags that support global or explicit firm rollout through environment configuration.
- Database queue dispatch waits for committed transactions and uses the dedicated `platform` queue by default.
- Scheduler and queue-worker heartbeat probes run every minute with overlap and single-server protection.
- `platform:operations-status` reports scheduler and worker freshness in human-readable or JSON form.
- The dashboard and authenticated shell now use the platform-owned dark ink, silver and restrained gold operational design.
- Client, obligation, work item, assignment, reassignment, workflow-version, checklist-gated transition, versioned evidence, reviewer return/approval decision, explicit workflow-version migration, filing, payment, tax, governed generation and deadline override schemas are implemented.
- Published party-master readiness rules can support manually recorded explainable issues. Each issue retains immutable rule snapshots and optional current-field context, while an independent authorised resolution or not-applicable decision is appended separately.
- Duplicate candidates retain explicit deterministic signal evidence, normalized comparison values and normalizer versions. Independent authorised decisions confirm or dismiss a candidate without automatic discovery, probability scoring or merge execution.
- Synthetic invoice-transaction samples retain manually supplied field values and source references separately from party readiness. Published invoice-domain rules support immutable explainable issues and independent resolution decisions without tax, total, validity or readiness calculations.
- An accessible compliance schedule exposes month, week and list views of stored effective deadlines plus a selected client's retained operational timeline. It remains firm-scoped and performs no statutory calculation.
- Dashboard and work-register filters can be retained, applied and explicitly deleted only by their owner in the active firm. Applying a filter reuses existing authorized queries, and audit metadata excludes names and values.
- The notification centre exposes only a recipient's active-firm request and delivery states, with append-only read evidence. Explicit daily manager summaries use encrypted idempotent delivery and stored operational counts without inferring compliance.
- Operational reports provide monthly schedules, tax-period lists, current expiring-document metadata and workload/completion rows. Preview and audited spreadsheet-safe export share one definition and exclude sensitive identifiers and reasons.
- The dashboard exposes distinct awaiting-client, under-review and explicitly unassigned queues plus workload by current assignee. A separate clean-database command builds a deterministic 200-client synthetic acceptance fixture with reconciled VAT and Corporate Tax labels, work, checklists and assignments.
- Filing state uses its own record, lifecycle and append-only history, gated by the named `manage_filings` permission, and never reads or writes work status.
- Payment state uses its own record, lifecycle and append-only history, gated by the named `manage_payments` permission, and never reads or writes work or filing status. `paid` is terminal and requires a reference and settlement date.
- Append-only history is enforced at two layers: Eloquent model events and database triggers on `work_item_transitions`, `filing_record_transitions`, `payment_record_transitions` and `audit_logs`.
- Newly published core workflow versions define two reviewer edges from `under_review`. Earlier immutable versions may retain an inert `under_review` to `ready_to_file` step that no interface exposes.
- Local PHP and Composer tooling is stored in ignored `.tools/` for reproducibility on this Windows environment.

## Work Completed in This Session

- Read and reviewed the original Version 1.0 product plan.
- Applied the installed `everything-claude-code` ECC skill.
- Applied the project-local Impeccable skill to the UX planning section.
- Verified current UAE e-invoicing, VAT and Corporate Tax planning facts against official Ministry of Finance and FTA sources.
- Upgraded the canonical plan to Version 2.0.
- Added project boundaries, architecture, domain model, tenancy, rule, workflow, import, readiness, UX, security, recovery, roadmap, work-packet, test and handoff specifications.
- Created platform-local `AGENTS.md`.
- Created platform-local `CLAUDE.md`.
- Created this `MEMORY.md`.
- Scaffolded the Laravel 13 Livewire starter application into the platform root.
- Installed Laravel Boost guidance and Codex configuration.
- Configured the platform name, Dubai timezone and MySQL deployment example.
- Disabled public self-registration and passkey authentication.
- Added regression tests that assert registration and passkey routes are unavailable.
- Installed frontend dependencies and generated a production Vite build.
- Added firm and firm-membership migrations, models, relationships, enums and synthetic factories.
- Added trusted membership creation and a production-blocked synthetic database seeder.
- Added authenticated firm resolution using server-side session selection.
- Added reusable fail-closed Eloquent tenant scoping and write guards.
- Added firm-aware queued-job middleware.
- Added HTTP route-binding, Livewire action and queued-job isolation fixtures and tests.
- Added named code-level permissions for the six baseline firm roles.
- Added a tenant-aware `FirmMembershipPolicy` with self-view and administrator-only member management.
- Added a trusted, server-validated firm switch route that records the selected firm in the session.
- Added firm invitation creation and acceptance actions with normalized email validation, 72-hour expiry and SHA-256 token storage.
- Added append-only firm-scoped audit records with actor, subject, correlation, request metadata and recursive sensitive-value redaction.
- Added platform-local product and design records derived from the approved master plan and TBT brand constraints.
- Added the authenticated firm chooser, persistent active-firm switcher and the first platform operational shell.
- Added the firm access register, invitation form, queued mail notification and acceptance interfaces for new and existing users, plus policy-protected membership role changes, suspension, reactivation and terminal revocation with self-lockout and final-administrator protections, plus invitation resend with token rotation, invitation revocation with a required reason and duplicate-invitation prevention.
- Extended the access register with responsive lifecycle controls, confirmations, loading states and protected modal focus handling.
- Added named feature flags with explicit firm allowlists, a dedicated database-backed platform queue with after-commit dispatch, scheduler and queue-worker heartbeat probes with overlap and single-server protection, and a machine-readable operations health command.
- Added the portable queue, scheduler, health-check and feature-flag operations runbook, plus guarded tenant cache and private-storage services, the durable tenant infrastructure contract and negative tests for missing context, cross-firm collisions, cross-firm deletion, unsafe keys, path traversal and unsafe disk configuration.
- Added the bounded spreadsheet-safe CSV writer, the audited tenant export artifact action, export cleanup on audit failure, negative tests for malformed content, actor mismatch and cross-firm row leakage, the encrypted firm-notification base, deterministic request persistence, immutable attempts, the audited delivery lifecycle, the firm access-summary template, hardened invitation notification queue payloads, bounded scheduled work, restore proof, clients, obligations, assignments, reassignment, pinned workflow and checklist versions, gated review submission and a complete Claude Code handoff.

## Verification Performed

- Confirmed the platform is isolated from the website, has no Sites hosting manifest, and the Version 2.0 master plan contains Sections 1 through 55.
- Verified `MEMORY.md` remains at the 300-line handoff limit.
- Verified the application reports Laravel 13.22.0 on PHP 8.3.32 with timezone `Asia/Dubai`.
- Laravel Pint check passed.
- Larastan passed with zero errors.
- Full workflow feature suite (`tests/Feature/Workflows`, including checklist, transition, reviewer-decision and workflow-version migration tests) passed with no failures.
- PHPUnit passed 471 tests with 1,493 assertions; GitHub Actions run `30360819994` passed the exact `32c7f1b` release head across assets, formatting, static analysis, domain tests and dependency audits.
- Database triggers were verified to reject raw query-builder updates and deletes on `work_item_transitions`, which Eloquent model events alone do not cover.
- Vite production build passed.
- All migrations, including append-only deadline overrides, and seven synthetic domain seeders passed from a fresh database.
- The local database includes immutable workflow definitions, transition steps and non-null work-item version pins.
- Composer schema and lock validation passed.
- Composer audit reported no known vulnerability advisories.
- npm audit reported zero vulnerabilities.
- Blade templates compiled successfully.
- The Impeccable detector completed with zero findings for the updated obligation register.
- Laravel configuration caching passed with all feature and operations configuration present.
- The real local scheduler dispatched a database-backed heartbeat job, a one-shot worker processed it and the operations status returned healthy.
- Tenant cache and file paths were verified to include the environment and active firm and remain isolated when logical names collide.
- CSV exports were verified against formula initiators, quoting attacks, malformed UTF-8, null bytes and configured size limits.
- Notifications were verified against duplicate dispatch, unsafe keys, failed attempts, immutable history, cross-firm relationships and context or content leakage.
- Scheduled work was verified against suspended firms, duplicate generation slots, cross-firm cache leakage and worker context leakage.
- Browser inspection remains pending because the immediate port check found no listener on 8010; no persistent server command was started.
- Code coverage was not measured because Xdebug and PCOV are not installed in the local PHP runtime.

## Regulatory Sources and Review State

Last live review: 26 July 2026.

Primary sources:

- UAE Ministry of Finance e-invoicing portal
- Ministry of Finance targeted 2026 e-invoicing amendment announcement
- FTA VAT filing guidance
- FTA Corporate Tax return guidance

No regulatory statement should be converted into a published calculator or customer-facing claim without a new official-source verification and recorded reviewer.

## Known Risks and Blockers

- Repository topology is not approved. A separate private repository rooted here is recommended.
- Exact Hostinger PHP, MySQL, cron, queue, storage, logging, backup and resource capabilities are not verified.
- The local framework baseline is pinned, but compatibility with the exact production Hostinger plan is not verified.
- GBR is not defined in the plan, and its data owner and pilot approver are not recorded.
- Legal seller and appropriate commercial licence are unresolved.
- Production and pilot data-location approval is unresolved.
- Full permission matrix is not approved.
- Baseline roles now have code-level permissions, but configurable custom roles and the final product-owner permission matrix remain open.
- Mail delivery is implemented through Laravel notifications, but production mail transport and the persistent queue-worker process are not verified.
- Local scheduler and database queue execution are verified, but the Hostinger cron entry and persistent worker supervision remain unresolved.
- Feature flags are configuration-controlled only; an audited administration interface is not implemented.
- Local desktop and 375 px browser inspection is complete for the landing page, login, dashboard, client register, CSV import dialog and e-invoicing coming-soon page. Production-host smoke testing remains pending.
- Audit and transition records are append-only through Eloquent model events and database triggers, but an audit viewer is not implemented.
- The isolation pattern is proven for membership HTTP binding, Livewire actions, queued and scheduled jobs, cache values, private files, CSV exports and notifications.
- MySQL continuous integration is not configured, so current automated database tests use SQLite in memory.
- Retention and deletion schedule is not approved.
- Rule author, verifier and update cadence are not assigned.
- Readiness scoring formula is not approved.
- Import conflict, reversal and raw-file retention rules need final product-owner approval.
- Final platform typography needs approval. Instrument Sans is the current bundled interface face.

## Open Decisions

1. Approve separate private Git repository or keep the folder in the parent repository.
2. Define GBR and identify its data owner and pilot approver.
3. Verify the exact Hostinger capability matrix.
4. Verify that the exact production environment supports the pinned PHP, Laravel and MySQL baseline.
5. Approve pilot data classes and hosting location.
6. Approve legal seller and commercial licence path.
7. Approve the role and permission matrix.
8. Assign compliance rule author and independent verifier.
9. Approve rule supersession and weekend or holiday behavior.
10. Approve import conflict, reversal and retention policies.
11. Approve the readiness score formula and duplicate thresholds.
12. Approve audit and customer-data retention.
13. Approve recovery objectives.
14. Confirm the final platform typography. Instrument Sans is currently bundled, with Inter retained as the approved future option.

## Next Three Safe Actions

1. Approve import conflict, reversal and retention policies before building import processing.
2. Verify the exact Hostinger capability matrix and production PHP, Laravel and MySQL baseline.
3. Configure and verify a real `MalwareScanner` adapter before any production document is treated as clean.

## Next Agent Handoff

Start by reading `AGENTS.md` and the master plan. Reconcile this file against Git status. Ask only for decisions that cannot be discovered safely. Keep all examples synthetic.

## Session Changelog

### 2026-07-26

- Established the platform as an independent project boundary inside the wider TBT workspace.
- Converted the original product SRS into a build-ready Version 2.0 planning baseline.
- Added durable ECC, testing, tenancy, security, UX and cross-LLM handoff rules.
- Began implementation under the product owner's explicit instruction.
- Scaffolded and verified the secure Laravel and Livewire foundation.
- Implemented and verified the firm tenancy foundation.
- Implemented and verified the permissions, firm switching, invitation and append-only audit security packet.

### 2026-07-27

- Implemented and verified the firm chooser, persistent switcher and platform operational shell.
- Implemented and verified invitation delivery, new-user onboarding, existing-user acceptance and the firm access register.
- Recorded the platform product and design context for future interface work.
- Implemented and verified audited member role changes, suspension, reactivation and terminal revocation.
- Implemented and verified invitation resend with token rotation and invitation revocation.
- Implemented and verified fail-closed global and firm-targeted feature flags.
- Implemented and verified the database queue, scheduler heartbeat, queue-worker heartbeat and operations health command.
- Implemented and verified tenant-scoped cache namespaces and private storage paths.
- Implemented and verified bounded, spreadsheet-safe and audited tenant CSV exports.
- Implemented and verified encrypted, idempotent notifications with persistent delivery evidence.
- Implemented and verified bounded, idempotent firm-aware scheduled work.
- Implemented and verified required checklist evidence on review submission and refreshed the Claude Code handoff.
- Implemented and verified reviewer return and approval decisions (`ReviewDecision`, `DecideWorkItemReview`, `review` policy ability) from `under_review`, restricted to the currently assigned reviewer with a required reason, append-only transition and audit evidence, a dedicated Livewire "Decide review" interface and refreshed `TENANCY.md` and `CLAUDE.md` handoff documentation.
- Reviewed the reviewer-decision packet and resolved three findings: unified the work-register transition button check and the transition dialog behind `WorkItem::genericTransitionTargetsFor()` so a reviewer no longer sees a dead-end "Update work" button; retired the unreachable `under_review` to `ready_to_file` reviewer edge from newly published workflow versions; and added database `BEFORE UPDATE` and `BEFORE DELETE` triggers on `work_item_transitions` and `audit_logs` because Eloquent model events never fire for query-builder mass operations.
- Implemented and verified operational notification triggers (`WorkItemHighRiskNotification`, `PaymentOverdueNotification`) dispatched only through the existing `DispatchFirmNotification` boundary when a member explicitly records high risk or an overdue payment, addressed to the current responsible manager via `WorkItem::responsibleManagerUser()`, skipped without a recipient rather than guessed, keyed on the history row so one occurrence sends once, and carrying no message contents, reason text or payment reference. Fixing this surfaced a real latent defect: `FirmNotification` held its firm identity, recipient identity and tracked request id as private properties, which do not survive queue serialization for a subclass with its own constructor, and readonly parent properties cannot be initialized from a subclass scope. They are now protected and non-readonly, still exposed only through final accessors.
- Implemented and verified controlled reopen (`ReopenWorkItem`, `work_items.parent_work_item_id`, `work_items.primary_obligation_id`) creating a linked follow-up from completed original work only, refusing to reopen a follow-up or open a second concurrent follow-up, pinning the latest published workflow and checklist, carrying the original's current owners forward after re-checking each is still active and permitted, leaving the original and all its history untouched, and recording `work_item.reopened` audit evidence. Primary uniqueness moved from `unique(firm_id, obligation_id)` to a nullable `primary_obligation_id` marker so it stays portable to MySQL, and `Obligation::workItem()` now scopes to the primary with follow-ups reached through `workItems()`.
- Implemented and verified the audit register export (`AuditRegisterFilters`, `ExportAuditRegister`) and authorized browser retrieval through a firm-scoped immutable `firm.export.created` record. Downloads stream from tenant-private storage only after validating metadata, path, checksum and byte count, require `view_audit_log`, isolate foreign firms, reject missing or altered artifacts, and append a distinct `firm.export.downloaded` audit record. The viewer and export share one filter object and exported CSV excludes before and after values.
- Implemented and verified work item risk status (`RiskLevel`, `work_items.risk_status`, `WorkItemRiskChange`, `SetWorkItemRiskStatus`) as a stored field independent of work, filing, payment and tax state, opening `unassessed`, rejecting a no-op set to the same level, requiring a reason, append-only history protected by Eloquent events and database triggers, `work_item.risk_status_changed` audit evidence, and a Livewire risk interface reusing the existing `assign_work`-gated `update` ability. No automated risk inference exists.
- Implemented and verified tax records (`TaxType`, `TaxRecordStatus`, `TaxRecord`, `TaxRecordAmendment`, `CreateTaxRecord`, `AmendTaxRecord`, `TaxRecordPolicy`, named `manage_tax_records` permission) as a fourth independent dimension with one record per obligation, retained tax type/period/currency/amounts (never inferred), a draft-then-final lifecycle where final is terminal, non-negative two-decimal amount validation, append-only amendment history protected by Eloquent events and database triggers, `tax_record.created`/`tax_record.amended` audit evidence, and a Livewire tax interface. Tax state never reads or writes work, filing or payment state and transmits nothing to any authority.
- Implemented and verified firm-level feature-flag administration (`FeatureFlagOverride`, `SetFeatureFlagOverride`, `FeatureFlagOverridePolicy`, `App\Livewire\Settings\FeatureFlags`, `Feature::label()`/`description()`) restricted to the named `manage_firm_settings` permission. `FeatureFlags` now reads a firm-scoped stored override ahead of configuration, memoised per firm and flushed on change, and reads overrides by explicit firm id bypassing the global scope so queued work still resolves. Each change requires a reason and is recorded as `feature_flag.overridden` in append-only audit history. There is no deletion path, and a flag change never bypasses a policy.
- Implemented and verified the firm operational dashboard (`App\Livewire\Dashboard\Index`, `dashboard`) with separate counts and queues for open obligations due within 30 days, open obligations past due, active work explicitly marked high risk and payment records explicitly marked overdue. Measures derive only from existing firm-scoped state, managers see the firm, assigned preparers and reviewers see only their operations, non-operational roles receive a safe empty state, and no compliance score or inferred status is created.
- Implemented and verified payment records (`PaymentStatus`, `PaymentRecord`, `PaymentRecordTransition`, `CreatePaymentRecord`, `TransitionPaymentRecord`, `PaymentRecordPolicy`, named `manage_payments` permission) with one payment per obligation, opening states limited to not required, unknown or pending, a terminal paid state requiring a retained reference and settlement date, append-only transition history protected by Eloquent events and database triggers, `payment_record.created` and `payment_record.status_transitioned` audit evidence, and a Livewire payment interface. Payment state never reads or writes work or filing status, and no transfer is ever initiated, authorised or confirmed.
- Implemented and verified filing records (`FilingStatus`, `FilingRecord`, `FilingRecordTransition`, `CreateFilingRecord`, `TransitionFilingRecord`, `FilingRecordPolicy`, named `manage_filings` permission) with one filing per obligation, opening states limited to not required or not filed, required filing reference and filed date before authority outcome states, append-only transition history protected by Eloquent events and database triggers, `filing_record.created` and `filing_record.status_transitioned` audit evidence, and a Livewire filing interface. Filing state never reads or writes work status and no payment record, EmaraTax automation or authority transmission exists.
- Implemented and verified explicit audited workflow-version migration (`MigrateWorkItemWorkflowVersion`) for one open work item to a later published version of the same workflow key, with a required reason, rejection of completed or cancelled work, rejection of a target version defining no transition from the current status, preserved transition, assignment and checklist history, append-only `work_item.workflow_version_migrated` audit evidence and a Livewire "Migrate version" interface.
- Implemented and verified the Stage 4 client, document and governed-obligation foundation: source-linked rules have controlled publication and immutable history; published manual-date rules now create persisted previews and committed generation runs with explicit client, service, applicability and period inputs, complete input, parameter and result snapshots, human-readable explanations and deterministic keys. Same-input reruns return one obligation, changed inputs create a distinct obligation, superseded previews cannot commit, and database triggers prevent run or issued snapshot rewrites. No regulated VAT or Corporate Tax formula exists.
- Implemented and verified append-only deadline overrides (`effective_due_date`, `ObligationDeadlineOverride`, `OverrideObligationDeadline`) for open obligations. Each reason-required event retains the prior and new effective date, actor and timestamp; model guards and database triggers reject history mutation; the original statutory date and governed snapshots remain unchanged; dashboard, obligation and work registers sort and measure by the effective date while continuing to show the statutory date when different; no-op, internally inconsistent, unauthorised and cross-firm changes are rejected.
- Implemented and verified explicit obligation dispositions (`ObligationDisposition`, `DisposeObligation`) for open-to-cancelled and open-to-superseded changes. Each event retains prior and new status, actor, reason and timestamp; supersession requires a separately issued, different open same-firm replacement; cancellation cannot name a replacement; model guards and database triggers reject history mutation; the original dates, generated snapshots, replacement and linked evidence remain unchanged.
- Implemented and verified changed-rule proposals (`RuleChangeProposal`, immutable preview linkage, `ProposeRuleChange`) and append-only approvals (`RuleChangeProposalDecision`, `ApproveRuleChange`). A proposal accepts only an open governed obligation and later published version of the same template, retains issued and proposed dates, reason and exact generation preview, and changes no issued record. Approval is separate, idempotently commits the preview, uses `DisposeObligation` to link and supersede the original, retains both obligations and snapshots, rejects stale or repeated decisions, and exposes the comparison and approval sequence in the generation interface.
- Implemented and verified governed calculator golden cases (`CalculatorGoldenCaseSet`, immutable `CalculatorGoldenCase`, append-only `CalculatorGoldenCaseVerification`) with official UAE source links, expected and observed snapshots, separate preparation, verification and set approval, database mutation guards and rule-governance UI. `ObligationCalculator::isRegulatory()` makes the boundary explicit; regulatory rule approval fails without an approved set whose every latest verification passes and records the selected set on the rule version. `manual_date_passthrough` remains non-regulatory and adds no statutory formula.
- Implemented and verified Stage 5 data-quality rule governance (`DataQualityRuleDefinition`, `DataQualityRuleVersion`, `DataQualityRuleEvent`) behind `e_invoicing_readiness` and the named `manage_readiness_rules` permission. Stable identities distinguish party-master from invoice-transaction rules; immutable versions retain applicability, severity, warning or blocking behavior, explanation, remediation, official or internal source, formula-version effect and change summary; separate preparation and verification precede publication; newer publication supersedes prior content without mutation; model and database guards retain lifecycle evidence; a dedicated Operate UI, route and navigation expose no imports, scoring, correction or transmission.
- Implemented and verified synthetic party-master governance (`PartyRecord`, append-only `PartyFieldVersion`, immutable `PartyCorrectionProposal`, append-only `PartyCorrectionDecision`) behind readiness permissions. A party belongs to one firm client and can explicitly be customer, supplier or both; manually supplied field values retain verification state and source provenance; a correction requires a different value and evidence; an independent manager approval appends a superseding version while rejection writes none; stale and repeated decisions fail; raw mutation is blocked; audit payloads exclude field values and source notes; dedicated UI and navigation provide no file import, merge, inferred readiness state or score.

### 2026-07-28

- Completed local release browser QA using a bounded, detached PHP preview on port 8010. Verified HTTP 200, zero page-level horizontal overflow and clean console output across the landing page, login, dashboard, client register, CSV import dialog and e-invoicing coming-soon page at desktop and 375 px.
- Browser QA exposed a production-significant Livewire boundary defect: component update requests did not rerun `ResolveFirmContext`, causing interactive firm-scoped screens to lose tenancy context. Registered the resolver as Livewire persistent middleware, reverified the CSV dialog in the browser and passed 17 focused client and tenancy tests.
- Re-ran the full regression suite after the fix: 471 tests and 1,493 assertions passed. Pint and the Vite production build passed. This package has no npm `lint` script.
- Corrected the obligations workspace at the 1280 px app-shell viewport: the manual-entry rail now stacks below the register until 2XL, preserving a usable register width; ownership received a wider column; and record cells top-align instead of floating beneath a tall action stack. Browser checks passed at desktop and mobile, the Vite build passed, and 42 obligation, generation and work-register tests passed with 138 assertions.
- Rewrote the main MVP navigation, page titles, introductions, helper text and dashboard summaries for an accountant audience. Preferred labels are now Deadlines, Calendar, Deadline rules, Create deadlines, Work tracker, Activity history and Team members. Technical terms remain only where administrators need their precise meaning. The full regression run reached 470 of 471 before one expected coming-soon copy assertion was updated; the corrected focused suite then passed 52 tests with 192 assertions.
- Reworked the deadline-list ownership column after user testing showed that every work action remained permanently expanded. Deadline actions and work actions now use accessible, collapsed disclosure controls; status, assigned team, checklist progress and workflow version remain visible. At the 1280 px app viewport the sample row fell from a near full-screen action stack to about 301 px, and browser interaction confirmed all eight work actions remain available when expanded.

### 2026-07-29

- Implemented the compliance-first master-data packet. Clients now have contact, VAT and Corporate Tax period, trade licence, passport and Emirates ID fields, with sensitive licence and identity numbers stored using Laravel encrypted casts. Added firm-scoped `client_people` records for future multiple shareholder and manager rows.
- Extended client CSV import to accept the compliance master columns, validate dates, email, mobile, paired period boundaries and unusual Corporate Tax periods, and accept `.xlsx` first-worksheet files using the local ZipArchive and XML extensions. Import remains staged, capped at 500 rows and explicit before commit.
- Added `VatFilingDeadlineCalculator` and `CorporateTaxFilingDeadlineCalculator`. VAT uses 28 days after the Tax Period end and moves weekend dates to the next weekday. Corporate Tax uses nine months after the Tax Period end and the end of that month. Both are registered in the governed calculator registry and link to official FTA or Ministry of Finance sources when schedules are created.
- Added `GenerateClientComplianceSchedule`, which creates idempotent VAT periods and obligations for the next 18 months plus the first Corporate Tax obligation after master import. Existing periods are located with date-aware queries to avoid SQLite or MySQL date-format duplicates.
- Imported document dates create immutable client document metadata and the existing document reminder workflow. Imported authorised-signatory data now creates an encrypted `client_people` record. Full client master data can be downloaded as a firm-scoped, spreadsheet-safe and audited CSV by an administrator or responsible assigned staff member.
- Reworked accountant-facing copy for client import and the dashboard, hid advanced deadline source and generation pages unless the member has `manage_firm_settings`, and changed the primary deadline navigation label to Tax Returns and Deadlines.
- Added the firm-level `admin_tools` switch. It is off by default and can be enabled in Feature administration by a firm administrator with an audit reason. Advanced deadline-source and deadline-review navigation appears only when both the switch and administrator permission are present.
- Removed hard-coded `class="dark"` from application and authentication layouts, added light-theme base tokens and contrast overrides, and retained Dark, Light and System choices through Flux appearance settings.
- Verification: migration `2026_07_29_010000_add_compliance_master_data_to_clients.php` passed. Focused calculator and client-import tests passed, including audited export. Full PHPUnit regression passed: 477 tests and 1,512 assertions. Pint, PHPStan and the Vite production build passed. `artisan view:cache` remains a local verification blocker because it did not return within 30 seconds and was terminated; the bounded Laravel preview could not bind port 8010 in this environment, so browser QA remains pending.
- Next actions: add the audited full-client workbook export, add explicit generated-work creation policy for imported obligations, run the full regression suite, build frontend assets, and perform bounded browser QA in both themes.
