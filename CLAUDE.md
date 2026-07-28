# TBT Compliance Platform Claude Code Handoff

This directory is the complete application boundary. Do not modify `../v2/` or `../legacy/` unless the product owner separately requests website work.

## Required Start

Read these files in order before changing code:

1. `AGENTS.md`
2. `Think_Beyond_Tax_Compliance_Platform_Master_Plan.md`
3. `MEMORY.md`
4. `LARAVEL_BOOST_GUIDELINES.md`
5. `PRODUCT.md` and `DESIGN.md` for interface work
6. `TENANCY.md` for tenant-owned data or workflow work
7. `OPERATIONS.md` for queues, scheduling, deployment or recovery work

Treat the master plan as canonical scope and `MEMORY.md` as the current implementation handoff.

## Current State

- Stage 3 compliance walking skeleton is active.
- Build Packet 49 is complete.
- Work assignment, append-only reassignment, immutable workflow versions, pinned checklists, checklist completion evidence, checklist-gated review submission, reviewer return and approval decisions and explicit audited workflow-version migration are implemented.
- Every work item is pinned to a published firm-owned workflow definition and checklist version.
- Moving from `in_preparation` to `under_review` requires retained completion evidence for every required item on the pinned checklist.
- Only the currently assigned reviewer may approve or return work submitted for review, through a dedicated action separate from the generic transition tool.
- A manager may move one open work item to a later published workflow version through an explicit audited action. Publishing a later version never repins existing work.
- Append-only history is enforced by Eloquent model events and by database triggers on audit, workflow, assignment, checklist, filing, payment, tax and risk history.
- Filing records exist with their own lifecycle, gated by the named `manage_filings` permission, and never read or write work status.
- Payment records exist with their own lifecycle, gated by the named `manage_payments` permission, and never read or write work or filing status.
- Work, filing and payment state are three independent stored dimensions.
- A read-only audit register exists behind the `audit_viewer` feature flag, restricted to the named `view_audit_log` permission, with no export, edit or deletion path.
- Firm administrators can enable or disable features per firm through audited overrides read ahead of configuration; a flag change never bypasses a policy.
- Tax records exist as a fourth independent dimension with retained figures, a draft or final lifecycle and append-only amendment history, gated by the named `manage_tax_records` permission; the platform infers no statutory amount.
- Work items carry a stored, independently-changeable risk status (unassessed, low, medium, high) with append-only history; no automated risk inference exists.
- Assignment history and checklist completion evidence are protected against both Eloquent and raw database mutation. Controlled-reopen rollback refuses to remove its schema when linked follow-up work exists and requires a forward recovery migration instead.
- The audit register can be exported through `ExportAuditRegister`; its browser download resolves only an immutable firm-scoped export record, verifies the private artifact checksum and size, and records retrieval separately.
- Completed work can be corrected through `ReopenWorkItem`, which creates a linked follow-up and never changes the original. An obligation keeps one primary work item plus any number of follow-ups.
- The read-only work register groups each preserved primary work item with chronological corrective follow-ups and exposes each record's independent status, risk, workflow version and current owners.
- The firm dashboard derives separate due-soon, overdue, high-risk and overdue-payment measures from stored firm-scoped records without calculating a compliance score.
- Document evidence is immutable, firm-scoped, private and fail-closed: unconfigured scanning quarantines, infected payloads are removed, and only checksum-verified clean evidence can be downloaded.
- Client profiles retain explicit service enrollments with a responsible active firm member, tax registrations and actual non-overlapping tax periods. These records are firm-scoped, permission-gated and audited, and no period or registration is inferred.
- Client contacts retain an explicit purpose and preferred channel without copying personal details into audit payloads. Client and service status changes require a reason, create append-only history and audit evidence, and never occur from dates or other inferred state.
- Document expiry uses immutable firm-owned type versions, metadata-only client records, renewal chains and idempotent firm-local reminder generation. It stores no client document file and derives no validity or compliance conclusion.
- Obligation rule governance has stable immutable templates, draft-only content editing, source-linked versions, registered-calculator review gating, separate preparer and verifier identities, database-enforced lifecycle order and append-only events. The only registered calculator passes through a manually supplied date and explicitly performs no statutory calculation.
- Governed obligation generation requires a persisted preview, published rule, explicit client, service, applicability date and compliance-period label or actual tax period. Preview and committed runs are immutable, deterministic reruns return the same obligation, changed inputs create a new obligation, and no issued obligation is overwritten.
- Open obligations support explicit reason-required effective deadline overrides. The original statutory date and generated snapshots remain unchanged, each override is append-only, and operational queues use the effective date while displaying the original when different.
- Open obligations can be explicitly cancelled or superseded through an append-only disposition. Supersession requires a different open replacement obligation in the same firm; neither path modifies the retained obligation, dates, snapshots or linked evidence.
- Changed-rule handling is preview-first. A proposal compares an open governed obligation with a later published version of the same template, retains old and proposed dates plus the immutable generation preview, and requires a separate approval that issues a deterministic replacement before superseding the original.
- Calculator golden-case sets are versioned governance records with immutable sourced expectations, independent executable verification and approval. Calculators declare whether they are regulatory; regulatory rule approval requires and records the latest approved passing case set, while `manual_date_passthrough` remains explicitly non-regulatory.
- Stage 5 readiness rule governance is active behind `e_invoicing_readiness`. Stable rule identities explicitly separate party-master and invoice-transaction domains; versions retain applicability, severity, warning or blocking behavior, explanation, remediation, source and formula effect through independent review and immutable publication.
- Synthetic party-master identities are client-owned and can explicitly carry customer, supplier or both roles. Field values and provenance are append-only; corrections remain immutable proposals until an independent authorised approval or rejection, and approval appends a new field version without overwriting the earlier value.
- Explainable party issues are manually recorded against published party-master rules with immutable severity, behavior, explanation and remediation snapshots. Independent authorised decisions resolve or mark an issue not applicable without rewriting the retained issue.
- Duplicate candidates retain explicitly selected deterministic signals, normalized comparison values, normalizer versions and contribution explanations. Independent authorised decisions confirm or dismiss a candidate, but no probability is calculated and no merge occurs.
- Synthetic invoice-transaction samples retain manually supplied fields and provenance in a separate readiness register. Published invoice-domain rules support immutable explainable issues and independent resolution decisions without calculating tax, totals, validity or readiness.
- Two operational notification templates exist, `work_item_high_risk` and `payment_overdue`, both fired only by an explicit recorded change and addressed to the current responsible manager.
- Regulated-rule generation modules are not implemented.
- The full current verification baseline is recorded in `MEMORY.md`.

## Next Safe Packet

No further implementation packet is safe without resolving the product-owner decision gates below.

Keep it bounded:

- Approve import conflict handling, reversal behavior and raw-file retention before E1 import processing.
- Approve duplicate thresholds, merge value selection, dependent-reference redirection and recovery before merge execution.
- Approve the readiness formula, weights, denominators, unresolved-duplicate treatment and rounding before scoring or readiness reporting.
- Approve the clean-export contract after import, merge and scoring decisions establish what qualifies as clean.
- Do not add a regulated formula without sourced golden cases and explicit approval.
- Do not add VAT or Corporate Tax formulas without approved sourced golden cases.
- Keep import processing gated until conflict, reversal and retention decisions are approved.

## Local Commands

Run commands from this directory in Windows PowerShell.

```powershell
& '.\.tools\php-8.3.32\php.exe' artisan test --compact
& '.\.tools\php-8.3.32\php.exe' vendor/bin/pint --dirty --format agent
& '.\.tools\php-8.3.32\php.exe' vendor/bin/phpstan analyse --memory-limit=1G
npm.cmd run build
```

Composer is available at `.tools/composer.phar`. Use project-local caches under `.tools/` for dependency audits.

Do not launch `php artisan serve` through a persistent foreground shell. Check port 8010 with an immediate command. If no listener exists and browser verification is optional, record the blocker and continue with static and regression gates.

## Handoff Constraints

- Use only synthetic data and examples.
- Preserve strict `firm_id` scoping and negative cross-firm tests.
- Keep Livewire components thin and rules in actions or services.
- Significant changes require append-only audit evidence.
- Published workflows and checklists are immutable.
- Never overwrite assignment or completion history.
- Never use em dashes in authored project content.
- The entire platform directory is currently untracked inside the parent website repository. Do not stage or commit website files with platform work.
- Existing untracked `../v2/.preview-3010.*.log` files are outside this application and must not be changed.

## Known Blockers and Unresolved Decisions

- Browser inspection is pending because port 8010 has had no listener and prior persistent server attempts blocked shell execution.
- Xdebug and PCOV are not installed, so code coverage has not been measured.
- CI and deployment pipelines are not implemented.
- Production hosting, legal seller, retention, recovery objectives, regulated-rule ownership and pilot approvals remain product-owner decisions listed in `MEMORY.md`.
