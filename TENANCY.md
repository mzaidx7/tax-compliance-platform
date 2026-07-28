# Tenant Infrastructure Contract

## Purpose

This contract defines how application code scopes tenant-owned database records, caches and private files.

All tenant identity comes from the trusted `FirmContext`. Never construct a tenant namespace from a request parameter, form value, route value, imported field or queued payload without first resolving and validating that firm through the established context boundary.

## Cache

Use `App\Tenancy\TenantCache` for every tenant-owned cache value.

The service:

- Fails when no active firm context exists.
- Includes the application environment and active firm ULID in every key.
- Accepts only bounded safe logical keys.
- Supports scoped `get`, `has`, `put`, `remember` and `forget` operations.
- Does not expose a tenant-level flush operation.

Platform-global operational state, such as scheduler heartbeats, may use the base cache repository with an explicit platform namespace.

Firm scheduled-work heartbeats are tenant-owned. They use `TenantCache` after queue middleware establishes the trusted firm context. The global scheduler may enumerate active firm identifiers, but it must not read or write tenant records directly, accept a submitted firm identifier or retain firm context between jobs.

Never call a shared cache flush to clear one tenant. Laravel cache flush operations do not respect the configured application prefix.

## Private Files

Use `App\Tenancy\TenantStorage` for every tenant-owned file.

The service:

- Fails when no active firm context exists.
- Uses the private, non-serving disk configured by `TENANT_FILESYSTEM_DISK`.
- Includes the application environment and active firm ULID in every path.
- Rejects absolute paths, traversal segments, backslashes, empty segments and null bytes.
- Writes private visibility explicitly.
- Does not expose the raw filesystem disk or public URL generation.

Logical paths must use generated internal names. Preserve any user-supplied display filename as validated metadata later, not as the storage path.

The initial local structure is:

```text
tenants/{environment}/{firm_ulid}/{logical_path}
```

Cloud disks may replace the local adapter through configuration if they preserve private visibility and non-serving behavior.

## Client Identities

Use `App\Models\Client` and `App\Actions\Clients\CreateClient` for the canonical client identity foundation.

The boundary:

- Requires trusted active firm context and never accepts a submitted firm identifier.
- Stores a firm-local internal code, its uppercase uniqueness key, legal name, optional trade name, optional entity type and explicit lifecycle status.
- Enforces the normalized internal code as unique inside one firm while allowing the same code in another firm.
- Restricts creation and visibility to the named `manage_clients` permission.
- Starts new records as active and retains their creating user.
- Records creation in append-only audit history within the same database transaction.
- Fails closed when firm context is absent.
- Provides no deletion path while retention and deletion rules remain unapproved.

Legal and trade names are trimmed but otherwise stored as entered. Client creation does not infer entity type, tax registration, tax period, filing frequency or compliance status. Add those through their future explicit records and reviewed workflows.

## Manual Obligations

Use `App\Models\Obligation` and `App\Actions\Compliance\CreateManualObligation` for manually entered due-date records.

The boundary:

- Requires trusted active firm context and the `manage_obligations` permission.
- Permits firm administrators and managers through the current baseline role map.
- Resolves the selected client through the active firm scope and rejects another firm's client.
- Stores obligation type, optional period label, statutory due date, optional internal target date, manual origin, obligation status, source note and verification date.
- Requires the internal target date to be on or before the statutory due date.
- Rejects a verification date in the future.
- Stores the creating and verifying user.
- Records creation in append-only audit history within the same database transaction.
- Provides no deletion path while retention and deletion rules remain unapproved.

Manual obligation creation does not calculate, validate or guarantee a statutory deadline. It creates no work item, filing record, payment record, recurring rule or regulatory conclusion. Work, filing and payment states belong to their future dedicated records.

## Work Items and Assignment History

Use `App\Actions\Workflows\CreateAssignedWorkItem` to establish initial ownership for an obligation.

The boundary:

- Requires trusted active firm context and the `assign_work` permission.
- Creates at most one primary work item for each obligation through a firm-bound database constraint.
- Starts work as `not_started`, independently from obligation, filing and payment state.
- Requires an active same-firm preparer with `prepare_work`, reviewer with `review_work` and responsible manager with `assign_work`.
- Requires different preparer and reviewer memberships.
- Appends three assignment history events with effective time, assigning user and a required reason.
- Rejects updates and deletes on assignment history models.
- Records work creation and initial ownership in append-only audit history inside the same transaction.

Current ownership is derived from the latest retained event for each assignment role. Use `App\Actions\Workflows\ReassignWorkItem` to change one current owner without replacing history.

The reassignment boundary:

- Requires trusted active firm context, enabled compliance operations and the `assign_work` permission.
- Rejects completed or cancelled work items.
- Requires a different active same-firm replacement with the permission for the selected assignment role.
- Continues to require different preparer and reviewer memberships.
- Appends a new assignment event with the assigning user, required reason and effective time.
- Leaves work status, checklist snapshots and completion evidence unchanged.
- Records previous and replacement membership identifiers in append-only audit history inside the same transaction.
- Removes former owners from their operational queue because queue visibility is based only on the latest retained event for each role.

This assignment slice has no filing, payment or deletion path.

## Work Status Transitions

Use `App\Actions\Workflows\TransitionWorkItem` for the current fixed workflow.

The boundary:

- Requires trusted active firm context, an enabled compliance feature and a transition-capable permission.
- Accepts only an edge listed by the current `WorkItemStatus` state.
- Requires the active actor to be the currently assigned preparer, reviewer or responsible manager for that edge.
- Requires a concise reason for every status change.
- Locks the current work item before evaluating and applying the transition.
- Requires retained evidence for every required item on the pinned checklist before moving from `in_preparation` to `under_review`.
- Allows optional checklist items to remain open when work is submitted for review.
- Rejects an incomplete review submission before writing transition or audit history.
- Updates only work status and never changes obligation, filing or payment state.
- Appends a `work_item_transitions` event containing previous state, next state, actor, reason and UTC effective time.
- Rejects updates and deletes on transition history models.
- Records the status change in append-only audit history inside the same transaction.

Use `App\Actions\Workflows\PublishCoreWorkflowVersion` to publish the controlled transition graph used by new work.

The workflow-version boundary:

- Requires trusted active firm context, enabled compliance operations and the `assign_work` permission.
- Publishes an immutable firm-owned definition and immutable ordered transition steps.
- Assigns an increasing version within the firm and workflow key.
- Records each transition's required assignment role explicitly.
- Pins every new work item to the latest published core version through a non-null firm-bound foreign key.
- Keeps existing work pinned when a later version is published.
- Evaluates status transitions and actor ownership against the work item's pinned steps.
- Backfills existing work with a retained legacy version during the schema upgrade.
- Records publication in append-only audit history.

The controlled core workflow is a walking-skeleton baseline, not a free-form workflow designer. Explicit migration of open work, reviewer decision constraints and notification events remain future packets.

## Versioned Work Checklists

Use `App\Actions\Workflows\PublishChecklistVersion` to publish a controlled checklist and `App\Actions\Workflows\CompleteChecklistItem` to retain completion evidence.

The boundary:

- Requires trusted active firm context and enabled compliance operations.
- Restricts publication to a member with `assign_work`.
- Publishes an ordered, bounded checklist version and immutable items.
- Creates a new version for later corrections instead of modifying published content.
- Pins each newly assigned work item to the latest published `core-compliance-work` version.
- Restricts item completion to the currently assigned preparer.
- Rejects checklist items from any version other than the one pinned to that work item.
- Rejects duplicate completion and completion against completed or cancelled work.
- Requires a concise evidence note and retains actor and UTC completion time.
- Rejects updates and deletes on published versions, published items and completion evidence.
- Records publication and completion in append-only audit history.

This packet does not provide checklist editing, uncompletion, evidence files, version migration, configurable transition requirements beyond the required review-submission gate or customer-specific templates.

## Reviewer Return and Approval Decisions

Use `App\Actions\Workflows\DecideWorkItemReview` for the two reviewer decisions available while work is `under_review`.

The boundary:

- Requires trusted active firm context and enabled compliance operations.
- Restricts the decision to a member with `review_work` through a dedicated `review` policy ability.
- Rejects any decision when the locked work item is not currently `under_review`.
- Requires the active actor to be the currently assigned reviewer for that work item, independent of the generic pinned workflow role check performed as a second defense.
- Accepts only `App\Enums\ReviewDecision::Approve` or `App\Enums\ReviewDecision::Return`.
- Approval moves work to `awaiting_client_approval`. Returning moves work explicitly back to `returned_for_changes`, from which only the currently assigned preparer can resume preparation.
- Requires a concise reason for every decision.
- Confirms the pinned workflow definition still permits the resolved target status and reviewer role before writing history.
- Appends the decision as an ordinary `work_item_transitions` event and rejects updates or deletes on that history, identically to `TransitionWorkItem`.
- Records the decision in append-only audit history as `work_item.review_decided`, including the decision value, inside the same transaction.

The generic `TransitionWorkItem` action remains available for every other edge, including manager cancellation from `under_review`. The Livewire work register hides reviewer-role edges originating from `under_review` from the generic transition dialog so a reviewer decision is always made through the dedicated action.

`App\Models\WorkItem::genericTransitionTargetsFor()` is the single source of truth for that exclusion. Both the transition dialog and the work-register button visibility check call it, so the register can never offer an "Update work" button that opens an empty dialog.

Newly published core workflow versions define exactly two reviewer edges from `under_review`, to `returned_for_changes` and `awaiting_client_approval`. Approved work reaches `ready_to_file` from `awaiting_client_approval`. Workflow versions published before this change are immutable and may still contain an inert `under_review` to `ready_to_file` step. That step is unreachable through the interface and is superseded for work migrated to a later version.

This packet creates no filing or payment record.

## Workflow Version Migration

Use `App\Actions\Workflows\MigrateWorkItemWorkflowVersion` to move one open work item from its pinned workflow version to a later published version.

The boundary:

- Requires trusted active firm context, enabled compliance operations and the `assign_work` permission through the `update` policy ability.
- Locks the work item before evaluating the migration.
- Rejects completed or cancelled work.
- Accepts only a later published version of the same `definition_key`, resolved through the active firm scope.
- Rejects a target version that defines no outgoing transition from the current work status, so migration never strands work in a dead end.
- Requires a concise reason.
- Changes only `work_items.workflow_definition_id`. Work status, assignment history, transition history and the pinned checklist version are left unchanged.
- Records previous and new definition identifier and version in append-only audit history as `work_item.workflow_version_migrated` inside the same transaction.

Migration is always explicit. Publishing a later workflow version never repins existing work. This packet creates no filing or payment record and does not migrate checklist versions.

## Filing Records

Use `App\Actions\Filings\CreateFilingRecord` to open the filing record for an obligation and `App\Actions\Filings\TransitionFilingRecord` to move its state.

Filing state is a separate dimension from work status and payment status. Nothing in this boundary reads or writes work status, and no payment record exists yet.

The boundary:

- Requires trusted active firm context, enabled compliance operations and the named `manage_filings` permission through a dedicated `FilingRecordPolicy`.
- Creates at most one filing record for each obligation through a firm-bound unique constraint.
- Opens a filing only as `not_required` or `not_filed`. An authority outcome is never asserted at creation.
- Accepts only an edge listed by the current `FilingStatus` state.
- Locks the filing record before evaluating and applying a transition.
- Requires a concise reason for creation and for every state change.
- Requires a retained filing reference before `filed`, `acknowledged`, `rejected` or `corrected`.
- Requires a filed date, which may not be in the future, before `filed`.
- Appends a `filing_record_transitions` event containing previous state, next state, actor, reason and UTC effective time. The opening event has a null previous state.
- Rejects updates and deletes on filing transition history through Eloquent events and database triggers.
- Records creation as `filing_record.created` and every change as `filing_record.status_transitioned` in append-only audit history inside the same transaction.

This packet creates no payment record, performs no EmaraTax automation and transmits nothing to any authority. A filing reference is operator-entered evidence of an external submission, never proof that this platform submitted anything.

## Payment Records

Use `App\Actions\Payments\CreatePaymentRecord` to open the payment record for an obligation and `App\Actions\Payments\TransitionPaymentRecord` to move its state.

Payment state is a third independent dimension. Nothing in this boundary reads or writes work status or filing status, and no payment transition is coupled to a filing transition.

The boundary:

- Requires trusted active firm context, enabled compliance operations and the named `manage_payments` permission through a dedicated `PaymentRecordPolicy`.
- Creates at most one payment record for each obligation through a firm-bound unique constraint.
- Opens a payment only as `not_required`, `unknown` or `pending`. A settlement outcome is never asserted at creation.
- Accepts only an edge listed by the current `PaymentStatus` state. `paid` is terminal.
- Locks the payment record before evaluating and applying a transition.
- Requires a concise reason for creation and for every state change.
- Requires a retained payment reference and a settlement date, which may not be in the future, before `paid`.
- Appends a `payment_record_transitions` event containing previous state, next state, actor, reason and UTC effective time. The opening event has a null previous state.
- Rejects updates and deletes on payment transition history through Eloquent events and database triggers.
- Records creation as `payment_record.created` and every change as `payment_record.status_transitioned` in append-only audit history inside the same transaction.

This platform never initiates, authorises or confirms a real transfer. A payment reference is operator-entered evidence of a settlement that happened elsewhere. Card numbers, bank credentials and payment instruments are never stored.

## Audit Register

Use `App\Livewire\Audit\Index` for the read-only audit register.

The boundary:

- Requires trusted active firm context and the `audit_viewer` feature flag. A firm without the flag receives a 404, not an empty page.
- Restricts reading to a member with the named `view_audit_log` permission through a dedicated `AuditLogPolicy`.
- Denies `create`, `update`, `delete`, `restore` and `forceDelete` unconditionally, so no interface path can alter retained evidence.
- Relies on the `AuditLog` global firm scope, so one firm can never read another firm's records.
- Filters by action, free-text search across action, record, correlation and reason, and an inclusive date range.
- Resolves actor names only for the records on the current page, and shows a neutral label when a former member no longer exists.
- Renders retained before and after values exactly as `RecordAudit` stored them, so values redacted at write time stay redacted at read time.

This register has no edit, delete or retention path.

Use `App\Actions\Audit\ExportAuditRegister` to export the filtered register.

The export boundary:

- Requires trusted active firm context, the `audit_viewer` feature flag and the same `view_audit_log` permission as the register.
- Applies `App\Data\AuditRegisterFilters`, the single source of truth both the register and the export use, so an exported file can never contain a different set of records than the register showed.
- Streams matching records in bounded chunks through the existing spreadsheet-safe CSV writer, which enforces the configured row, column, cell-length and byte limits.
- Exports only recorded time, action, actor identifier, record type and identifier, reason and correlation identifier. Retained before and after values are not exported, so values redacted at write time can never be restored by an export.
- Records the download as its own `audit_register.exported` action carrying file name, checksum, row count, byte count and filter metadata. The search term itself is never recorded, only whether one was applied.
- Creates no edit or delete path for the records it reads.

## Tax Records

Use `App\Actions\Taxes\CreateTaxRecord` to open a draft tax record for an obligation and `App\Actions\Taxes\AmendTaxRecord` to amend its figures or finalise it.

Tax figures are a fourth stored dimension. Nothing in this boundary reads or writes work, filing or payment state, and no tax action is coupled to any work, filing or payment transition.

The boundary:

- Requires trusted active firm context, enabled compliance operations and the named `manage_tax_records` permission through a dedicated `TaxRecordPolicy`.
- Creates at most one tax record for each obligation through a firm-bound unique constraint.
- Stores the tax type, period, currency and taxable and tax amounts as retained values. The platform never infers or validates a statutory amount.
- Opens a record as `draft` and allows amendment only while `draft`. A `final` record is terminal and cannot be amended.
- Requires a concise reason and non-negative, two-decimal amounts for creation and every amendment.
- Appends a `tax_record_amendments` event with the previous and new status and amounts. The opening event has a null previous state.
- Rejects updates and deletes on tax amendment history through Eloquent events and database triggers.
- Records creation as `tax_record.created` and every amendment as `tax_record.amended` in append-only audit history inside the same transaction.

This packet performs no calculation, transmits nothing to any authority and has no deletion path. A corrected record after finalisation belongs to a future controlled path.

## Controlled Reopen and Follow-up Work

Use `App\Actions\Workflows\ReopenWorkItem` to correct completed work.

An obligation keeps exactly one primary work item and any number of linked follow-ups. Primary uniqueness is enforced through a nullable `primary_obligation_id` marker rather than a partial index, because partial indexes are not portable to MySQL. A primary stores its obligation identifier in the marker and a follow-up stores null, so a unique index on the marker permits exactly one primary while leaving follow-ups unconstrained.

The boundary:

- Requires trusted active firm context, enabled compliance operations and the `assign_work` permission through the existing `create` ability on work items.
- Resolves the original through the tenant scope before authorizing, so a cross-firm attempt fails as an authorization error rather than a missing model.
- Accepts only a `completed` original, and refuses to reopen a follow-up. Correction of a follow-up starts from the original.
- Rejects a second follow-up while an earlier one is still open.
- Leaves the original work item, its status history, checklist evidence and assignment history completely unchanged.
- Pins the follow-up to the latest published workflow and checklist versions, starting at `not_started` with `unassessed` risk and its own lifecycle.
- Carries the original's current preparer, reviewer and responsible manager forward, re-checking each is still an active member with the permission that role requires.
- Requires a concise reason and records the reopen in append-only audit history as `work_item.reopened`.

A reopen changes no filing, payment, tax or risk state, and provides no deletion path.

## Work Item Risk Status

Use `App\Actions\Workflows\SetWorkItemRiskStatus` to set the risk status of a work item.

Risk status is a stored field on the work item, per the master plan's work item field list. It is independent of work, filing, payment and tax state.

The boundary:

- Requires trusted active firm context, enabled compliance operations and the same `update` ability as reassignment and workflow-version migration, gated on `assign_work`.
- Rejects setting the level the work item already holds.
- Requires a concise reason for every change.
- Appends a `work_item_risk_changes` event containing previous level, new level, actor, reason and UTC effective time.
- Rejects updates and deletes on risk history through Eloquent events and database triggers.
- Records the change in append-only audit history as `work_item.risk_status_changed` inside the same transaction.

A work item opens `unassessed`. This packet performs no automated risk inference.

## Feature Administration

Use `App\Actions\Settings\SetFeatureFlagOverride` to record a firm's decision to enable or disable one feature, and `App\Livewire\Settings\FeatureFlags` for the administration interface.

The boundary:

- Requires trusted active firm context and restricts every change to a firm administrator through the named `manage_firm_settings` permission and a dedicated `FeatureFlagOverridePolicy`.
- Stores at most one override for each firm and feature through a firm-bound unique constraint.
- Requires an explicit reason for every enable or disable and records it as `feature_flag.overridden` in append-only audit history, with the previous and next state.
- Reads overrides through `FeatureFlags` ahead of configuration. A firm with no override for a feature falls back to configuration.
- Reads overrides by explicit firm id, bypassing the tenant global scope, so flag resolution still works in queued work that resolves its own firm without an active context.
- Has no deletion path. An override is changed, never removed, and the policy denies `delete`, `restore` and `forceDelete`.

A flag change only shifts feature availability. It never bypasses a policy: every guarded action still enforces its own permission and firm scope independently of any flag.

## History Immutability Enforcement

`work_item_transitions`, `filing_record_transitions`, `payment_record_transitions`, `tax_record_amendments`, `work_item_risk_changes` and `audit_logs` are protected at two layers:

1. Eloquent model events reject `updating` and `deleting` on individual model instances.
2. Database `BEFORE UPDATE` and `BEFORE DELETE` triggers reject the same operations for SQLite and MySQL.

The second layer is required because Eloquent model events never fire for query-builder mass operations or raw SQL. Any new append-only history table must add both layers.

## CSV Exports

Use `App\Actions\Exports\CreateCsvExport` for generated CSV artifacts.

The action:

- Requires active firm context and verifies any actor against the active membership.
- Accepts a bounded lowercase export slug and generates a unique filename.
- Streams CSV generation through bounded temporary memory.
- Enforces configured row, column, cell-length and total-byte limits.
- Rejects malformed row shapes, invalid UTF-8, null bytes, unsupported values and non-finite numbers.
- Neutralizes dangerous string cells beginning with formula characters, including full-width variants.
- Preserves typed numeric values as numbers.
- Uses standards-compatible CSV quoting with an explicit empty proprietary escape character.
- Stores the artifact through `TenantStorage`.
- Records only filename, path, checksum, size and count metadata in append-only audit history.
- Removes the artifact if its audit record cannot be created.

Formula neutralization adds a tab to dangerous string cells. This is intended for human viewing in spreadsheet applications and becomes part of the exported value. Do not treat a presentation export as a lossless re-import format.

Artifacts are written to tenant-private storage. No browser download route exists yet, so an artifact is retrieved out of band. A future download route must authorize the active firm and record each retrieval as its own audited action, as `ExportAuditRegister` already does for creation.

## Notifications

Operational notifications must extend `App\Notifications\FirmNotification` and dispatch through `App\Actions\Notifications\DispatchFirmNotification`.

The notification boundary:

- Carries explicit firm and recipient identities.
- Persists one tenant-owned request per deterministic firm, recipient, template, channel, trigger and caller idempotency key.
- Hashes caller idempotency keys instead of storing their raw business value.
- Encrypts the queued payload.
- Uses the dedicated platform queue after database commit.
- Applies the persisted scheduled time as the queue delay when delivery is in the future.
- Restores active firm context through queue middleware.
- Re-checks firm status, recipient identity and active membership immediately before delivery.
- Requires each template to declare a stable key, version, single supported channel and optional tenant-owned trigger record.
- Records recipient identity and template metadata without message contents.
- Records every delivery outcome as an append-only attempt with a safe failure code.
- Retains attempt count, final status, correlation ID and nullable provider reference.
- Suppresses duplicate queue dispatch for an existing deterministic request.
- Restores the previous firm context after delivery or failure.

Notification content must load tenant-owned values only after queue middleware establishes firm context. A caller must still enforce the workflow policy that authorizes the notification.

The caller-provided idempotency key identifies one business occurrence, such as a reminder type and obligation version. It must not contain confidential content. Reusing it for the same firm, recipient, template, channel and trigger returns the existing request without another delivery.

Provider references remain null until a mail adapter exposes a stable non-sensitive identifier. Failure evidence stores a bounded exception category, never the provider response or exception message. Notification requests cannot be deleted until the retention policy is approved.

## Retained Document Evidence

Use `App\Actions\Documents\StoreDocumentEvidence` to attach a document to open work and `documents.download` to retrieve a clean retained payload.

The boundary:

- Requires trusted active firm context, enabled compliance operations and the dedicated `evidence` work-item policy ability.
- Allows responsible managers and currently assigned preparers or reviewers. Other same-firm members and every foreign firm are denied.
- Accepts only PDF, PNG and JPEG files within the configured size limit, and requires the detected MIME type to match the extension.
- Stores the payload only on tenant-private non-serving storage under a generated logical path.
- Retains immutable document metadata including purpose, original name, detected type, checksum, byte count, uploader and upload time.
- Appends an immutable scan event. The default production adapter returns `unavailable`, so an unconfigured scanner quarantines rather than approves every upload.
- Deletes an infected payload while retaining its evidence and scan history.
- Permits download only when the latest scan event is clean and the stored checksum and byte count still match.
- Records upload, scan and successful download as distinct append-only audit events.
- Rejects model, query-builder and raw SQL mutation of document and scan history through model guards and database triggers.

Development and tests use synthetic files only. The application does not claim that malware scanning is operational until a real `MalwareScanner` adapter is configured and verified in the target environment.

## Operational Notification Triggers

Two operational templates exist, both dispatched through `DispatchFirmNotification` and never through a second path:

- `work_item_high_risk` version 1, sent when `SetWorkItemRiskStatus` records a work item at high risk.
- `payment_overdue` version 1, sent when `TransitionPaymentRecord` records a payment as overdue.

The trigger boundary:

- Fires only on an explicit recorded change. Nothing infers risk, and nothing derives an overdue payment from a date.
- Addresses the work item's current responsible manager, resolved through `WorkItem::responsibleManagerUser()`, which returns null unless that member is still active.
- Skips the notification, while still recording the state change, when there is no active responsible manager or no primary work item. The caller never guesses a recipient.
- Uses the history row identifier as the caller idempotency key, so one recorded occurrence sends exactly once and a later repeat of the same level is a distinct occurrence.
- Carries no message contents, no reason text and no payment reference in the retained request.

`FirmNotification` keeps its firm identity, recipient identity and tracked request identifier as protected, non-readonly properties. A parent's private properties do not survive queue serialization for a subclass that declares its own constructor, and PHP forbids initializing a parent's readonly property from a subclass scope. They are never reassigned and remain exposed only through final accessors.

Invitation notifications are a separate pre-membership onboarding path. They may use on-demand mail routing, but their token-bearing queued payload is encrypted and uses the platform queue.

## Required Tests

Every tenant-owned cache or file feature must prove:

1. Missing firm context fails closed.
2. Two firms using the same logical key or path receive different physical namespaces.
3. Reading or deleting in one firm cannot affect the other firm.
4. Unsafe keys or paths are rejected before access.
5. The configured file disk is private and non-serving.

Exports, imports, notifications and scheduled jobs must add their own negative cross-tenant tests on top of this infrastructure.

## Client Service And Tax Profile

Client profile records are tenant-owned facts:

- A service enrollment belongs to one firm and client, names one explicit service and may name one active membership from the same firm as responsible.
- A tax registration belongs to one firm and client, stores its supplied tax type and registration identifier, and never implies authority validation.
- A tax period belongs to one registration in the same firm, uses supplied start and end dates and rejects overlaps. Dates never create or change a period automatically.
- Creation requires trusted active firm context, the compliance-operations feature and authorization for the client.
- Every create action writes append-only audit evidence. Registration identifiers are excluded from audit payloads.
- Composite foreign keys and global firm scopes enforce the tenant boundary, with negative cross-firm tests.
- Client contacts belong to the same firm and client, and retain an explicit purpose and preferred channel. Audit metadata records only the contact record id, purpose and channel, not the name, email or phone.
- Client and service status changes are explicit reason-required actions. Their history tables reject update and deletion through both model guards and database triggers.
- An ended service enrollment is terminal. Dates never pause, resume or end an enrollment automatically.

## Client Document Expiry Metadata

Client document expiry is separate from work-item `DocumentEvidence`:

- `DocumentTypeVersion` is a published, immutable firm-owned configuration with a stable key, monotonically increasing version, explicit expiry requirement, reminder offsets and optional overdue repeat interval.
- `ClientDocument` stores metadata only. It contains no logical file path, upload field or download route.
- A renewal creates a new append-only record linked through `supersedes_client_document_id`; the earlier record is never updated.
- Reference labels, issue dates and expiry dates are omitted from audit payloads because metadata may still be personal data.
- Firm-aware scheduled work compares the supplied expiry date with the firm's local calendar date and creates idempotent reminder evidence only for configured upcoming offsets, expiry day and configured repeated overdue intervals.
- Reminder evidence is append-only. It does not send a client message or assert that a document is valid, invalid or regulator-approved.
- Published type versions, client document metadata and reminder evidence reject model and raw database update or deletion.

## Obligation Rule Governance

Rule governance records are firm-owned and do not alter existing obligations:

- A rule template is an immutable stable identity containing its obligation type, jurisdiction and authority.
- A rule version contains effective dates, applicability criteria, calculator key, validated parameter shape, official source metadata, preparer and change summary.
- Source URLs must use HTTPS on configured official UAE government hosts. Configuration currently permits the FTA, Ministry of Finance, UAE Legislation and U.AE domains and their subdomains.
- Content may be edited only in draft. Submitting for review freezes content at model and database layers.
- Review requires a registered PHP calculator and valid parameters. Approval requires a verifier different from the preparer and retains source verification and approval timestamps.
- Lifecycle order is draft, under review, approved, published, then superseded or retired. Database triggers reject skipped states, post-review content rewrites, deletion and lifecycle-history mutation.
- Publishing a later version supersedes the prior published version without changing either version's content or any existing obligation.
- `manual_date_passthrough` is the only registered calculator. It validates and returns a human-supplied date with an explanation that no statutory calculation was performed.

## Governed Obligation Generation

Generation is a preview-first tenant-owned operation:

- A preview requires a published rule version, client, service enrollment, explicit applicability date, explicit compliance-period label and optional actual tax period from the same firm.
- The selected service and optional tax period must belong to the client and cover the supplied applicability date. The system never infers a client period or service.
- The deterministic key covers firm, client, service, optional tax period, rule version, canonical input snapshot and validated parameter snapshot.
- One deterministic input has one preview run, one committed run and one generated obligation. Repeating it returns the same records; changing an input produces a distinct key and record.
- Preview and committed runs are immutable. A committed obligation retains the run, rule, service, period, input, parameter, result, explanation and due-date snapshots.
- A preview cannot commit after its rule is superseded or retired. The operator must create a new preview.
- Generated snapshot fields reject model and raw database mutation. Independent obligation workflow status may still change through its own controlled boundary.
- Generation never modifies, replaces or silently supersedes another issued obligation.

## Obligation Deadline Overrides

Deadline correction is an explicit tenant-owned operation:

- `statutory_due_date` remains the original manually entered or governed-generation date and is never rewritten by an override.
- `effective_due_date` is the operational date used for urgency filters and ordering, with a fallback to the statutory date for earlier records.
- Only an authorised manager in the active firm may override an open obligation, and every override requires a different date and a reason.
- An effective date cannot precede the retained internal target date.
- Each event retains the prior date, new date, actor, reason and timestamp in `obligation_deadline_overrides`.
- Model guards and database triggers reject update or deletion of override history.
- Dashboards and work registers show the original statutory date whenever it differs from the effective date.
- An override never changes a generated input, parameter, result or explanation snapshot and never regenerates or supersedes an obligation.

## Obligation Dispositions

Cancellation and supersession are explicit tenant-owned status changes:

- Only an authorised manager in the active firm may dispose an open obligation, with a required reason.
- Cancellation names no replacement. Supersession requires a separately issued, different open replacement obligation resolved through the active firm scope.
- The original obligation changes only from open to cancelled or superseded. Its dates, generated snapshots, work, filing, payment, tax and audit evidence remain retained.
- Each event stores prior and new status, optional replacement identifier, actor, reason and timestamp in `obligation_dispositions`.
- Model guards and database triggers reject update or deletion of disposition history.
- Repeat disposition, self-replacement, closed replacement, cross-firm and unauthorised attempts fail closed.
- Disposition creates no replacement automatically and performs no changed-rule calculation.

## Changed-Rule Proposals

Rule changes affecting issued governed obligations are preview-first:

- A proposal requires an authorised manager, an open governed obligation with complete generation snapshots and a later published version of the same rule template in the active firm.
- The proposed date must differ from the issued statutory date. The normal generation boundary validates service, period, applicability, calculator and internal target inputs and creates the immutable deterministic preview.
- `rule_change_proposals` retains the original and proposed dates, original obligation, proposed rule, preview run, actor, reason and timestamp without changing the issued obligation.
- Approval is a separate explicit action. It locks the unresolved proposal, commits the exact preview through the normal generation boundary, then supersedes the original through the normal disposition boundary.
- `rule_change_proposal_decisions` retains the approval, replacement obligation, actor, reason and timestamp. A proposal accepts only one decision.
- Proposal and decision records reject model and raw database update or deletion.
- The original obligation, rule snapshot and calculation snapshots remain unchanged and linked to the separately issued replacement.

## Calculator Golden Cases

Calculator assurance is firm-owned and versioned:

- Every calculator declares whether it is regulatory. `manual_date_passthrough` is explicitly non-regulatory and continues to perform no statutory calculation.
- A draft case set identifies one registered calculator and version. Its cases retain named inputs, validated parameters, expected statutory result, official UAE source, source verification date and preparer.
- A verifier different from the case preparer executes the registered calculator. The observed result, explanation, pass or fail outcome, verifier and timestamp are append-only.
- A case-set approver different from the set preparer may approve only when the set contains at least one case and every case's latest verification passes.
- Regulatory rule approval requires the latest approved same-firm case set for that calculator and records the set identifier on the approved rule version.
- Case, verification and approved-set evidence reject model and raw database mutation or deletion.
- Golden cases do not authorise a regulated formula. Each future formula still requires approved official-source cases and product-owner approval.

## E-Invoicing Data-Quality Rules

Readiness rule governance is firm-owned and isolated from compliance obligations:

- Access requires the `e_invoicing_readiness` feature and named `manage_readiness_rules` permission.
- A stable immutable definition names one party-master or invoice-transaction field or scenario. The two domains never share an unexplained score or issue state.
- A version retains applicability, severity, warning or blocking behavior, explanation, remediation guidance, official or internal source, formula-version effect, preparer and change summary.
- Lifecycle order is draft, under review, approved, published, then superseded or retired. Approval requires a verifier different from the preparer and a source verification date.
- Publishing a later version supersedes the prior published version without changing prior content.
- Definitions and lifecycle events are append-only. Version content and lifecycle order are guarded at model and database layers.
- This module currently evaluates no party or invoice data, imports no files, proposes no correction, merges no identity and calculates no readiness score.

## Readiness Party Master And Corrections

Synthetic party records are client-owned readiness evidence:

- A party belongs to one firm and client and may explicitly carry customer, supplier or both roles. The stable identity and supplied active flag are immutable.
- Current field values are derived from append-only `party_field_versions`. Each version retains field key, supplied value, verification state, source kind, source reference, actor, timestamp and optional superseded version.
- Initial manual entry is restricted to the import-management role even though no file is imported.
- A correction records the current version, proposed value, evidence note, proposer and timestamp without changing the current value.
- Approval or rejection requires readiness-rule authority and a decision maker different from the proposer. Approval appends a new version; rejection appends no field value.
- A stale proposal cannot replace a field that changed after proposal. Proposals accept only one decision.
- Party, field, proposal and decision evidence reject model and raw database mutation or deletion.
- Audit metadata records identifiers, field keys and outcomes but excludes field values, source references and evidence notes.
- No party readiness status, duplicate match, merge, bulk import or readiness score is inferred.

## Readiness Party Issues

Party issues are retained explainable evidence, not an inferred readiness result:

- An issue belongs to one firm-owned party and one published party-master rule version in the same firm. An optional field reference must be the party's current field version when recorded.
- The issue snapshots severity, behavior, explanation and remediation so later rule publication cannot rewrite existing evidence.
- Recording requires party update authority. Resolution or not-applicable decisions require readiness-rule authority and a different actor.
- One issue accepts one terminal decision. The issue and decision reject model and raw database update or deletion.
- Audit metadata records identifiers and outcomes but excludes evidence notes.
- No automated evaluation, bulk import, duplicate matching, merge or readiness score is performed.

## Readiness Duplicate Candidates

Duplicate review is explicit evidence and a human decision, not an automatic match:

- A candidate links two different party records belonging to the same firm and client. Pair order is canonical and one deterministic pair creates one candidate.
- Each append-only signal retains its type, both normalized comparison values, normalizer version, contribution explanation, recorder and timestamp.
- Signal recording requires update authority over both parties. A decided candidate accepts no later signal.
- Confirmation or dismissal requires readiness-rule authority, a decision maker different from the candidate recorder and at least one retained signal.
- Candidate, signal and decision records reject model and raw database update or deletion.
- Audit metadata excludes normalized values and decision reasons.
- No probability, readiness score, automatic discovery, merge or dependent-reference redirection is performed.

## Invoice Transaction Readiness Samples

Invoice readiness remains separate from party readiness and retains only supplied evidence:

- A synthetic sample belongs to one firm client and has an immutable sample identity and manual source reference.
- Each append-only sample field retains one explicitly supplied value, field key, source reference, recorder and timestamp. No field value is calculated.
- An invoice issue links only to a published invoice-transaction rule in the same firm and optionally to one field of the selected sample.
- The issue snapshots severity, behavior, explanation and remediation. Resolution or not-applicable requires readiness-rule authority and a different actor.
- Sample, field, issue and resolution records reject model and raw database update or deletion.
- Audit metadata excludes supplied values, source references, evidence notes and decision reasons.
- No VAT, total, exchange-rate, validity, compliance or readiness calculation is performed.

## Compliance Schedule And Client Timeline

The schedule is a read-only projection over retained tenant records:

- Access requires the compliance-operations feature and normal obligation-view authorization.
- Month, week and list ranges query the effective due date with statutory-date fallback, within the active firm's global scope.
- Client and obligation-state filters never bypass firm scoping or expose clients from another firm.
- The client timeline derives only from retained client status, obligation, work-transition, filing-transition and payment-transition evidence.
- Timeline reasons remain in their protected source records and are not duplicated into the projection.
- The schedule stores no date, infers no status and performs no statutory calculation.

## Saved Operational Filters

Saved filters are private user preferences inside one firm:

- Each record belongs to one firm, one global user and one constrained surface: dashboard or work register.
- Filter schemas are validated per surface. Client references resolve through the active firm's scope.
- Only the owner in the active firm may view, update or explicitly delete a saved filter.
- Applying a saved filter feeds values into the same existing tenant-scoped and permission-filtered queries. It never stores or broadens query authorization.
- Filter names and values are excluded from audit payloads; audit metadata records only the surface and filter keys.
- No shared filter, administrator transfer or cross-firm reuse exists.

## In-App Notification Evidence And Manager Summaries

The notification centre is a recipient-owned projection over existing delivery evidence:

- A signed-in user sees only notification requests addressed to their global user id inside the active firm's scope.
- Request status and immutable attempts are visible without message contents, provider references, failure reasons or correlation metadata.
- Marking a notice read appends one immutable receipt. It never mutates or deletes the notification request.
- A report-authorised member may explicitly select an active manager and generate one deterministic summary request per recipient and firm-local date.
- Summary counts derive from stored open deadlines, explicit high-risk work and explicit overdue-payment state. No compliance state is inferred.
- The summary notification payload is encrypted in the queue and stores no counts or message content in the notification request or audit log.

## Operational Reports And Exports

Operational reports are permission-gated tenant projections:

- Access requires the named `view_reports` permission in the active firm.
- Monthly schedule, tax-period, expiring-document and workload/completion previews use the same row definitions as their exports.
- Every query remains under the firm global scope and is bounded by month or current firm workload.
- Exports reuse the spreadsheet-safe tenant-private storage and immutable audited-artifact boundary.
- A report owner with `view_reports` may download their own recognised operational-report artifact after checksum and byte verification. Audit-register exports retain their stricter audit permission.
- Reports exclude contact details, document references, reasons, tax registration identifiers and audit contents.
- No statutory date, tax, risk or compliance value is calculated.
