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
