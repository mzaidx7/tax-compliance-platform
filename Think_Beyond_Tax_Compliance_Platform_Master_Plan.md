# Think Beyond Tax Compliance Platform

## Master Product Plan and Software Requirements Specification

**Version:** 2.0  
**Date:** 26 July 2026  
**Status:** Build-ready planning baseline  
**Primary domain:** `thinkbeyondtax.com`  
**Working product name:** Think Beyond Tax Compliance Platform  
**Document purpose:** Complete planning and build context for Codex, developers, designers, testers and project stakeholders.

**Implementation authority:** Sections 31 onward turn the product requirements into an executable delivery plan. If a general statement in Sections 1 to 30 conflicts with a specific implementation decision in Sections 31 onward, the later implementation decision controls until the product owner records a replacement.

---

## Release Strategy Amendment: Compliance Operations First

**Approved:** 28 July 2026

The master plan remains the long-term roadmap, but it no longer defines one continuous launch scope.

### Release 1: Internal Compliance Operations MVP

Release 1 is the immediate build and publishing target. It includes:

- Secure firm tenancy, authentication, roles, permissions and audit evidence
- Client master, contacts, services, tax registrations and actual tax periods
- Manual and governed source-linked obligations
- Assignment, workflow, checklist, review, filing, payment and tax-record operations
- Document metadata and expiry tracking
- Dashboard queues, schedule, client timeline, notifications, reports and safe exports
- Bounded CSV client onboarding with validation, preview, reconciliation and error reporting
- Responsive, accessible and coherent production frontend
- CI, production configuration, queue and scheduler instructions, backup proof, deployment runbook and post-deployment smoke checks

Release 1 explicitly excludes:

- Claims that a stored or manually entered date is legally correct
- E-invoicing readiness assessment, scoring, cleanup, merge or export
- E-invoice transmission, EmaraTax automation, OCR and accounting integrations
- Billing, public self-service onboarding and dedicated-edition automation

Release 1 includes bounded VAT and Corporate Tax schedule calculators based on confirmed imported Tax Periods. They retain official-source snapshots and never replace human review, documented FTA extensions or administrator overrides. The platform does not claim that a stored, imported or calculated date is legally correct for every client circumstance.

### E-Invoicing Readiness: Separate Future Release

Existing readiness code and data structures remain preserved behind the `e_invoicing_readiness` feature flag. Until its own release gate passes:

- Navigation exposes only a non-operational "Coming soon" page.
- Readiness work is not included in Release 1 acceptance or deployment claims.
- Its backlog, decisions, tests and progress remain tracked separately from Compliance Operations.
- Development may continue incrementally after Release 1 without delaying the compliance launch.

### Release 1 Completion Gate

Release 1 is publishable only when:

1. The release-critical frontend has completed desktop, tablet and mobile review.
2. Client onboarding reconciles at least 200 synthetic records without cross-tenant leakage.
3. Compliance workflows, schedules, notifications, reports and exports pass automated acceptance tests.
4. Formatting, static analysis, dependency review, production asset build and the full automated suite pass.
5. Production environment, queue, scheduler, mail, storage, backup, restore and rollback procedures are documented and verified to the extent possible before host access.
6. No known critical or high-severity release defect remains.
7. The deployed application passes authentication, tenant-isolation and core-workflow smoke checks.

Stages 5 through 10 remain future roadmap stages. They must not expand or delay the Release 1 critical path.

## 1. Instructions for Codex and Developers

Treat this document as the current source of truth for the product unless the product owner gives a newer instruction.

When implementing the platform:

1. Preserve the scope, constraints and deployment strategy in this document.
2. Build one maintainable codebase that supports both hosted SaaS and dedicated customer deployments.
3. Prioritise a reliable operational MVP over advanced AI, direct accounting integrations or visual complexity.
4. Do not claim FTA, Ministry of Finance, EmaraTax or Accredited Service Provider approval or affiliation.
5. Do not automate EmaraTax login, store EmaraTax passwords, bypass UAE Pass or scrape protected portal pages.
6. Use synthetic, anonymised or specifically approved data during development and demonstration.
7. Treat all statutory dates as configurable rules with version history, source references and human verification.
8. Keep the application portable so it can move from Hostinger to UAE-region infrastructure without rewriting business logic.
9. Use secure defaults, audit significant actions and isolate every firm's data.
10. Record unresolved assumptions in an explicit decision log instead of silently inventing product behaviour.
11. Keep all platform code, configuration, documentation and operational artifacts inside `TBT Compliance Platform/`.
12. Read `AGENTS.md`, this master plan and `MEMORY.md` before material work.
13. Apply the installed `everything-claude-code` ECC skill to project work, including testing and conventional commit discipline.
14. Update `MEMORY.md` after every material planning, implementation, verification or deployment session.
15. Never use em dashes in authored product copy, documentation, code comments or commit messages.

---

## 2. Executive Summary

The Think Beyond Tax Compliance Platform is a UAE-focused compliance operations system for accounting, bookkeeping and tax firms that manage large numbers of clients.

The original pain point comes from managing more than 200 tax clients. Important deadlines and document expiries are distributed across EmaraTax profiles, emails, spreadsheets, individual calendars, WhatsApp messages and employee knowledge. A firm may not know that a trade licence, Emirates ID, passport or other record has expired until the FTA emails the client and the client contacts the firm.

The platform will centralise:

- VAT return periods and filing deadlines
- Corporate Tax periods and filing deadlines
- Trade licence and identity-document expiries
- Tax-record amendment responsibilities
- Client document requests
- Staff assignments and reviews
- Filing and payment status
- E-invoicing implementation readiness
- Customer and supplier master-data quality
- Management reporting and audit history

The product will be commercialised through:

1. A hosted SaaS subscription.
2. A dedicated deployment with a one-time perpetual licence and optional annual maintenance.
3. One-time e-invoicing master-data readiness and cleanup engagements.

The internal GBR workflow will serve as the initial product laboratory and pilot environment.

---

## 3. Product Vision

### 3.1 Vision Statement

Create a UAE-focused compliance control centre that allows an accounting firm to see every client, deadline, missing document, assigned employee, filing status and e-invoicing data issue before it becomes a compliance problem.

### 3.2 Positioning

The platform is not intended to replace TallyPrime, QuickBooks, Zoho Books, Xero or other accounting systems.

It sits above those systems as an operational control layer for:

- Compliance deadlines
- Practice workflows
- Document-expiry monitoring
- E-invoicing readiness
- Master-data governance
- Team accountability
- Management visibility

### 3.3 Core Marketing Message

> Stop managing hundreds of tax clients through scattered spreadsheets, emails and employee memory.

### 3.4 E-Invoicing Message

> Find and fix customer, supplier and invoice-data problems before they cause e-invoice validation failures.

---

## 4. Problems Being Solved

### 4.1 Tax Compliance Visibility

Accounting firms often lack one reliable view of:

- Which VAT returns are due this month
- Which Corporate Tax returns are approaching
- Which clients belong to each VAT filing period
- Which documents are expiring
- Which clients have not submitted records
- Which filings are being prepared, reviewed or delayed
- Which payments remain pending
- Which employee is responsible
- Which work is overdue or at risk

### 4.2 Dependency on Individuals

Important information may exist only in:

- An employee's spreadsheet
- Personal calendar reminders
- Email inboxes
- WhatsApp conversations
- Physical files
- The employee's memory

This makes staff absence, resignation and handover unnecessarily risky.

### 4.3 Reactive Document Management

The firm may only discover expired documents after:

- The FTA contacts the client
- A portal application is rejected
- A filing or amendment is delayed
- The client forwards an email

### 4.4 Poor Customer and Supplier Master Data

Customer and vendor ledgers commonly contain:

- Partial names
- Abbreviations
- Trade names instead of legal names
- Missing TRNs
- Missing addresses
- Duplicate records
- Multiple spellings of the same business
- Outdated VAT information
- Generic names such as "Cash Customer"
- Incorrect customer or supplier classifications

Manual cleanup across hundreds of clients may require several weeks of accountant time.

### 4.5 E-Invoicing Transaction Data Gaps

Even where party master data is complete, invoice exports may still lack:

- Suitable invoice identifiers
- Required seller and buyer information
- Item descriptions
- Units and quantities
- Tax-category information
- Line-level VAT amounts
- Currency and exchange-rate information
- Credit-note references
- Conditional transaction data

---

## 5. Current UAE Context

The UAE e-invoicing programme uses structured invoice data exchanged through Accredited Service Providers. PDFs, Word documents, images, scans and email attachments are not themselves e-invoices.

As of this document date:

- The pilot programme commenced on 1 July 2026.
- Businesses with annual revenue above AED 50 million must appoint an Accredited Service Provider by 30 October 2026.
- Those businesses must implement the system by 1 January 2027.
- Businesses below AED 50 million are scheduled to appoint an Accredited Service Provider by 31 March 2027 and implement by 1 July 2027, subject to future official amendments.

All dates and technical requirements must remain configurable and must be checked against current official Ministry of Finance and FTA publications before production use.

---

## 6. Target Customers

### 6.1 Primary Customers

- UAE accounting firms
- Bookkeeping service providers
- Tax consultancies
- Registered tax agencies
- Independent tax professionals
- Firms managing approximately 50 to 1,000 or more clients

### 6.2 Secondary Customers

- Business-setup consultancies
- Corporate-service providers
- Audit firms
- Company-secretarial providers
- Corporate groups managing multiple entities
- Businesses preparing for UAE e-invoicing
- Accredited Service Providers seeking readiness or data-cleanup partners

### 6.3 Initial User Environment

The initial internal pilot should reflect a real accounting firm managing more than 200 clients across VAT, Corporate Tax, bookkeeping, document amendments and related work.

---

## 7. Product Principles

1. **Operationally useful:** The product must reduce real management effort.
2. **Simple onboarding:** Existing Excel data must be importable.
3. **Human-controlled:** Automated suggestions require review where risk exists.
4. **Traceable:** Dates, corrections and status changes need audit history.
5. **Configurable:** Firms may use different workflows and internal targets.
6. **Secure:** Firm data and sensitive information must be isolated.
7. **Portable:** Hosting providers and infrastructure must be replaceable.
8. **Focused:** The MVP is a compliance control layer, not a complete ERP.
9. **UAE-specific:** Rules, fields and workflows must match UAE operational realities.
10. **Commercially flexible:** The same product must support SaaS and dedicated deployments.

---

## 8. Core Functional Modules

## 8.1 Firm Workspace

Each subscribing or licensed firm receives its own workspace containing:

- Firm profile
- Users and roles
- Clients
- Obligations
- Workflows
- Notifications
- Reports
- Branding settings where permitted
- Subscription or licence information
- Audit logs

No user from one firm may access another firm's data.

## 8.2 Client Master Record

Each client profile should support:

- Internal client code
- Legal name
- Trade name
- Entity type
- Active, inactive or disengaged status
- Primary contact name
- Email
- Telephone
- VAT TRN
- Corporate Tax registration number
- Financial year-end
- VAT filing frequency
- Actual VAT tax-period start and end dates
- Corporate Tax period
- Trade licence number
- Licensing authority
- Trade licence issue and expiry dates
- Authorised signatory
- Emirates ID expiry
- Passport expiry
- Services handled by the firm
- Accounting software used
- Assigned preparer
- Assigned reviewer
- Responsible manager
- E-invoicing implementation phase
- Selected Accredited Service Provider
- Internal notes
- Attachments where enabled
- Complete activity history

Quarterly VAT clients must not automatically be assumed to follow calendar quarters. Their actual assigned periods must be stored.

## 8.3 Compliance Dashboard

The default dashboard should show operational queues rather than only a crowded calendar.

Required cards and views:

- Overdue obligations
- Due within 7 days
- Due within 30 days
- Due within 60 days
- Due within 90 days
- VAT returns due this month
- Corporate Tax returns due this month
- Expiring documents
- Awaiting client documents
- Ready for preparation
- Under review
- Awaiting client approval
- Ready to file
- Filed but payment pending
- Unassigned work
- Workload by employee
- E-invoicing readiness by client
- Master-data issues awaiting review

## 8.4 Calendar and Work Queues

The platform must offer:

- Monthly calendar
- Weekly calendar
- List view
- Work queue
- Employee workload view
- Client-specific timeline
- Filters by obligation type, status, employee, reviewer, client and date
- Saved filters
- Search

The calendar is secondary to the dashboard and work queues because hundreds of clients can make a calendar unreadable.

## 8.5 Deadline and Obligation Engine

The rules engine should generate or support:

- VAT preparation dates
- VAT filing deadlines
- Corporate Tax preparation dates
- Corporate Tax filing deadlines
- Trade licence renewals
- Tax-record amendment tasks
- Emirates ID and passport expiry reminders
- Engagement renewal dates
- Audit deadlines
- ASP appointment deadlines
- E-invoicing implementation tasks
- Custom one-time obligations
- Custom recurring obligations

Every generated date must contain:

- Obligation type
- Source rule
- Rule version
- Calculation inputs
- System-calculated or manually entered status
- Last verified date
- Verified by
- Manual override
- Override reason
- Source or reference link where applicable

## 8.6 Workflow Management

Default workflow:

1. Not started
2. Documents requested
3. Awaiting client
4. Documents received
5. In preparation
6. Under review
7. Awaiting client approval
8. Ready to file
9. Filed
10. Payment pending
11. Completed

Firms must be able to configure workflows by service.

For implementation, the visible workflow may present these labels, but the data model must keep three concerns separate:

- Work status, such as not started, awaiting client, in preparation, under review, returned, ready to file, completed or cancelled
- Filing status, such as not required, not filed, filed, acknowledged, rejected or corrected
- Payment status, such as not required, unknown, pending, paid or overdue

This separation prevents a single status field from becoming contradictory. The interface may display a combined summary while the database preserves each independent state.

Each work item should include:

- Client
- Obligation
- Assigned preparer
- Assigned reviewer
- Responsible manager
- Internal target date
- Statutory deadline
- Priority
- Risk status
- Checklist
- Notes
- Attachments
- Filing acknowledgement
- Payment confirmation
- Status history
- Comments
- Created, updated and completed timestamps

## 8.7 Document Expiry Management

Supported document types should include:

- Trade licence
- Emirates ID
- Passport
- Power of attorney
- Authorisation document
- Tax-agent appointment
- Engagement letter
- VAT certificate
- Corporate Tax certificate
- Other configurable documents

Default reminder options:

- 90 days before expiry
- 60 days
- 30 days
- 14 days
- 7 days
- 1 day
- On expiry
- Repeated overdue escalation

The MVP may store document metadata and expiry dates without storing sensitive scans.

## 8.8 Notifications and Escalations

MVP notifications:

- In-app alerts
- Internal email notifications
- Daily manager summary
- Weekly compliance summary
- Employee task reminders
- Overdue escalation

Later notifications:

- Client email reminders
- Repeated missing-document requests
- Official WhatsApp Business messages
- Calendar synchronisation

WhatsApp functionality must use an official business messaging route, approved templates where required and recorded consent.

## 8.9 Bulk Operations

Required bulk functionality:

- Import clients from Excel or CSV
- Export filtered reports
- Bulk assign preparers
- Bulk assign reviewers
- Bulk create obligations
- Bulk change status
- Bulk send document requests
- Bulk update filing periods
- Bulk update document dates
- Save import mappings
- Validate imports before committing
- Produce import-error reports
- Reverse an import where safely possible

## 8.10 Management Reporting

Reports should include:

- Monthly filing schedule
- VAT clients by tax period
- Corporate Tax clients by year-end
- Expiring documents
- Overdue obligations
- Employee workload
- Employee completion performance
- Client response delays
- Work awaiting review
- Filing completion rates
- Upcoming workload forecast
- Client compliance history
- E-invoicing readiness
- Master-data cleanup progress
- Unresolved data exceptions

---

## 9. E-Invoicing Master Data Readiness Engine

## 9.1 Purpose

Help accounting firms and businesses assess, clean and maintain the party and transaction data required for UAE e-invoicing.

This module can operate as:

- Part of the SaaS platform
- A dedicated licensed product
- A managed one-time readiness service

## 9.2 Supported Data Sources

Initial support:

- Generic Excel customer master
- Generic Excel supplier master
- CSV files
- Tally ledger exports

Later support:

- QuickBooks customer and vendor exports
- Zoho Books exports
- Xero exports
- Historical sales invoices
- Historical purchase invoices
- VAT certificates
- Trade licences
- Customer onboarding records

## 9.3 Import and Mapping

The importer should:

- Preview data before import
- Allow source columns to be mapped to platform fields
- Save reusable mapping templates
- Detect invalid dates
- Detect missing identifiers
- Identify duplicate rows
- Reject or quarantine malformed records
- Produce a clear error report
- Preserve the original uploaded file
- Record who performed the import

## 9.4 Party Master Scan

The engine should detect:

- Incomplete legal names
- Suspicious abbreviations
- Missing TRNs
- Incorrectly formatted TRNs
- Missing addresses
- Missing country
- Missing emirate
- Missing contact information
- Duplicate ledgers
- Similar names with different spellings
- Parties created as both customer and supplier
- Inactive parties
- Generic party names
- Non-business ledgers incorrectly included as counterparties
- Records requiring manual verification

Each party receives one status:

- Ready
- Minor information missing
- Major information missing
- Possible duplicate
- Requires verification
- Not applicable

## 9.5 Readiness Dashboard

For each client, show:

- Total customers
- Total suppliers
- Ready records
- Missing TRNs
- Incomplete legal names
- Missing addresses
- Possible duplicates
- Records requiring review
- Unresolved requests
- Overall readiness percentage
- Last scan date
- Cleanup progress

## 9.6 Data Quality Rules

Rules should be:

- Configurable
- Versioned
- Categorised as mandatory, conditional, recommended or informational
- Traceable to an official requirement or internal policy
- Applicable by invoice type or transaction scenario
- Capable of producing warnings or blocking errors

## 9.7 OCR and Document-Based Suggestions

Later versions may extract possible information from:

- Historical tax invoices
- VAT certificates
- Trade licences
- Customer forms
- Supplier invoices

Possible extracted fields:

- Legal business name
- Trade name
- TRN
- Trade licence number
- Address
- Contact details

Requirements:

- Show the source document
- Show confidence level
- Never overwrite approved data silently
- Require accountant approval
- Preserve old and new values
- Record the approving user and time

## 9.8 Secure Counterparty Confirmation

Where information cannot be recovered, generate a secure confirmation request.

The recipient may provide:

- Legal name
- Trade name
- TRN
- VAT registration status
- Business address
- Country
- Emirate
- Contact person
- Email
- VAT certificate
- Trade licence

Responses must enter an internal review queue before becoming approved master data.

## 9.9 Duplicate Detection and Merge

Potential matches may use:

- Legal name
- Trade name
- TRN
- Telephone
- Email
- Address
- Historical transactions

Merge requirements:

- Display both records
- Show reasons for the suggested match
- Require human approval
- Choose a surviving master record
- Preserve source references
- Record the merge history
- Allow authorised recovery where practical

## 9.10 Invoice Data Readiness

The later transaction scanner should test sample invoice data for:

- Invoice number
- Issue date
- Invoice type
- Currency
- Seller and buyer identifiers
- Item or service description
- Quantity
- Unit
- Unit price
- Discount
- Charges
- Tax category
- VAT rate
- Line-level VAT
- Totals
- Exchange-rate information
- Credit-note references
- Other mandatory or conditional fields

Party Master Readiness and Invoice Data Readiness must remain separate scores.

## 9.11 Clean Export

After review, produce:

- Clean customer master
- Clean supplier master
- Unresolved-items report
- Duplicate-merge report
- Change history
- Readiness assessment
- Import-ready Excel or CSV file

Later, add dedicated export formats for Tally, QuickBooks, Zoho Books and Xero.

## 9.12 Ongoing Monitoring

The SaaS module should continue detecting:

- Newly created incomplete ledgers
- New duplicates
- Changed or expired information
- Missing e-invoicing fields
- Master-data deterioration
- Invoice-data exceptions

This ongoing control creates recurring value after the initial cleanup.

---

## 10. User Roles and Permissions

### 10.1 Platform Super Administrator

- Manage the overall SaaS platform
- Create and suspend firm tenants
- View infrastructure and billing information
- Access customer data only through controlled support procedures
- Manage global rule versions

### 10.2 Firm Administrator

- Configure the firm workspace
- Add employees
- Manage permissions
- Configure workflows
- Manage imports
- Access all firm reports

### 10.3 Manager

- View assigned teams and clients
- Assign work
- Review workload
- Handle escalations
- Access management reports

### 10.4 Preparer

- Access assigned clients
- Update checklists
- Prepare work
- Add notes and permitted attachments
- Submit work for review

### 10.5 Reviewer

- Review prepared work
- Approve or return work
- Add review notes
- Confirm completion

### 10.6 Data Cleanup Operator

- Import party data
- Review exceptions
- Propose corrections
- Process confirmation responses
- Prepare clean exports

### 10.7 Read-Only User

- View authorised clients and reports
- Cannot change records

Permissions must be enforced at both the interface and server level.

---

## 11. SaaS Commercial Model

The hosted SaaS version will provide:

- Secure firm workspace
- Automatic updates
- Backups
- Technical support
- Compliance-rule updates
- E-invoicing readiness scans
- Optional onboarding and migration

Potential pricing dimensions:

- Active clients
- Firm users
- Party records scanned
- Storage
- Advanced features
- Support level

Possible package sizes:

- Starter: up to 100 active clients
- Growth: up to 300 active clients
- Professional: up to 1,000 active clients
- Enterprise: custom limits and requirements

Exact prices remain a market-validation decision.

---

## 12. Dedicated One-Time Licensed Model

The same codebase may be deployed through:

- A separate managed cloud instance
- A dedicated database
- The customer's cloud account
- An approved customer-controlled server

The one-time package may include:

- Perpetual licence for the purchased version
- Deployment
- Configuration
- Data migration
- Training
- Twelve months of updates and support

Annual maintenance may cover:

- Regulatory-rule updates
- Security patches
- Technical support
- Compatibility updates
- New versions
- Hosting
- Backup monitoring

The one-time price must not include unlimited lifetime support.

The customer receives a licence to use the software, not ownership of the source code, unless a separate written agreement expressly transfers ownership.

---

## 13. One-Time E-Invoicing Readiness Service

The team may offer a managed service before the full SaaS is complete:

> UAE E-Invoicing Master Data Readiness Assessment and Cleanup

Possible deliverables:

- Customer and supplier master extraction
- Automated readiness scan
- Missing-information report
- Duplicate analysis
- Document-based information recovery
- Counterparty confirmation requests
- Accountant review
- Clean master-data export
- Readiness score
- Corrective-action plan

Pricing may depend on:

- Legal entities
- Customer and supplier count
- Accounting system
- Data quality
- Required manual review
- Document-extraction volume

This service can generate early revenue and reveal recurring data patterns needed to improve the software.

---

## 14. Domain, Brand and Application Structure

### 14.1 Confirmed Domain

The correct primary domain is:

`thinkbeyondtax.com`

Do not use `thinkbeyondtax.ae`.

### 14.2 Recommended Subdomains

- `thinkbeyondtax.com` - Public brand and marketing website
- `app.thinkbeyondtax.com` - Production SaaS application
- `demo.thinkbeyondtax.com` - Demonstration environment with synthetic data
- `staging.thinkbeyondtax.com` - Private testing environment
- `status.thinkbeyondtax.com` - Future service-status page

### 14.3 Initial Branding

Working presentation:

**Think Beyond Tax Compliance Platform**

The platform may initially use the Think Beyond Tax visual identity:

- Deep charcoal or black
- Matte gold
- Warm white
- Sora-style geometric headings where available
- Inter-style body typography where available
- Restrained, professional interface

A separate product name and domain may be introduced later after checking trademarks, competitors and domain availability.

### 14.4 Legal Seller

Hosting the platform under Think Beyond Tax does not determine the legal seller.

Before external sales:

- Identify the licensed legal person or entity selling the software
- Use that party in contracts and invoices
- Identify who receives payments
- Publish appropriate legal terms
- Avoid presenting Think Beyond Tax as an incorporated software company unless that becomes legally accurate

---

## 15. Hostinger MVP Hosting Context

### 15.1 Intended Use

The existing Hostinger plan includes web-app deployment capacity. Use it initially for:

- Internal development
- MVP deployment
- GBR pilot
- Demonstration environment
- Early controlled testing
- Synthetic or approved data

This minimises initial hosting cost.

### 15.2 Hostinger Capabilities to Confirm

Before deployment, verify the exact plan supports:

- PHP application deployment
- Required PHP version and extensions
- MySQL database
- Private GitHub connection
- Environment variables
- Scheduled jobs or cron
- Background queues
- SSL
- Web application firewall
- Daily and on-demand backups
- Storage limits
- CPU and memory limits
- Application logs
- Email sending limits
- Number of included web apps

### 15.3 Suggested Hostinger MVP Layout

- Laravel application on `app.thinkbeyondtax.com`
- Separate MySQL production database
- Private GitHub repository
- Environment variables for secrets
- Managed SSL
- Scheduled database backups
- Independent encrypted backup outside Hostinger
- Separate demo database
- Separate staging database

Production, staging and demo must never share one database.

### 15.4 Data-Location Limitation

Hostinger's currently published server locations do not include a UAE data centre.

Before storing sensitive production information outside the UAE, review:

- UAE Personal Data Protection Law requirements
- Cross-border transfer requirements
- Hostinger's data-processing agreement
- Physical server and backup locations
- Subprocessors
- Encryption
- Incident handling
- Customer contract requirements

During the MVP, prefer storing:

- Client codes
- Deadline dates
- Filing status
- Assigned employees
- Document types
- Expiry dates
- Synthetic or anonymised information

Avoid storing passport, Emirates ID and other sensitive scans until hosting and security controls are approved.

### 15.5 Portability Requirement

The application must not depend on Hostinger-specific business logic.

Use:

- Environment-based configuration
- Standard MySQL or PostgreSQL-compatible data design where practical
- Database migrations
- Portable object-storage abstraction
- Exportable backups
- Standard email interfaces
- Standard queue interfaces
- Container-ready deployment where practical

### 15.6 Future Migration

When onboarding external firms or storing sensitive information, move to suitable UAE-region infrastructure if required.

The migration must preserve:

- `app.thinkbeyondtax.com`
- Customer accounts
- Tenant workspaces
- Audit history
- Application behaviour
- Customer data

Users should not need to change the URL.

### 15.7 Dedicated Customer Domains

Dedicated customers may use:

- `firmname.app.thinkbeyondtax.com`
- A customer-controlled subdomain
- A fully custom domain

Dedicated deployment must still use the same core product version wherever possible.

---

## 16. Recommended Technical Architecture

### 16.1 Application Stack

Recommended initial stack:

- Laravel backend and application framework
- Livewire for interactive server-driven interfaces
- MySQL initially for Hostinger compatibility
- Tailwind CSS or a similarly maintainable UI system
- Calendar component for calendar views
- Laravel queues and scheduler for reminders
- Private Git repository

Reasons:

- Existing PHP and MySQL familiarity
- Suitable for rapid AI-assisted development
- Strong authentication, migrations, queues and notification patterns
- Easier single-codebase deployment than separate frontend and backend applications

### 16.2 Architecture Requirements

- Multi-tenant data model
- Firm-level tenant isolation
- Server-side authorisation
- Role-based access
- Configurable rule engine
- Event and audit logging
- Background scheduling
- Notification abstraction
- File-storage abstraction
- Import/export service
- API-ready service boundaries
- Feature flags for incomplete modules

### 16.3 Multi-Tenant Strategy

The initial SaaS may use a shared application and shared database with mandatory `firm_id` or tenant scoping.

Requirements:

- Every tenant-owned table must contain a tenant identifier
- Queries must automatically scope by tenant
- Server-side policy must prevent cross-tenant access
- Automated tests must attempt unauthorised cross-tenant access
- Super-admin support access must be logged and restricted

Dedicated deployments may use a separate database and environment.

### 16.4 Core Data Entities

Minimum entities:

- firms
- users
- roles
- permissions
- firm_users
- clients
- client_contacts
- tax_registrations
- tax_periods
- licences
- documents
- document_types
- obligations
- obligation_rules
- rule_versions
- tasks
- workflows
- workflow_steps
- task_assignments
- checklists
- checklist_items
- reminders
- notifications
- filing_records
- payment_records
- imports
- import_rows
- audit_logs
- einvoice_assessments
- party_records
- party_issues
- duplicate_candidates
- correction_suggestions
- confirmation_requests
- invoice_data_assessments
- subscriptions
- licences

### 16.5 API and Integration Position

MVP:

- Excel and CSV import/export
- Email notifications

Later:

- Tally-specific import/export
- QuickBooks integration
- Zoho Books integration
- Xero integration
- Google or Microsoft calendar
- WhatsApp Business
- ASP collaboration
- Public API and webhooks

Do not create unofficial EmaraTax automation.

---

## 17. Security, Privacy and Reliability Requirements

### 17.1 Security Controls

- Encryption in transit
- Encryption at rest where supported
- Multi-factor authentication
- Strong password policy
- Secure password hashing
- Firm-level data isolation
- Least-privilege permissions
- Audit logs
- Login monitoring
- Rate limiting
- CSRF protection
- Input validation
- File-type and size validation
- Malware scanning before sensitive document storage
- Secrets in environment variables
- Dependency vulnerability monitoring
- Regular backups
- Restore testing

### 17.2 Privacy and Data Governance

- Privacy policy
- Data-processing agreement
- Data-retention rules
- Secure deletion process
- Data export process
- Correction process
- Cross-border transfer assessment
- Subprocessor list
- Incident-response procedure
- Customer notification procedure

### 17.3 Audit Requirements

Record:

- Login and authentication events
- Client creation and deletion
- Deadline changes
- Manual overrides
- Status changes
- Assignments
- Imports
- Exports
- Document uploads and downloads
- Master-data corrections
- Duplicate merges
- Support access
- Permission changes

Audit records must be tamper-resistant and unavailable for ordinary users to edit.

### 17.4 Backup and Recovery

- Daily automated database backup
- Independent backup outside the primary host
- Defined retention period
- Periodic restore test
- Recovery instructions
- Recovery time and recovery point targets before commercial launch

---

## 18. Regulated Activity Boundaries

The product initially provides:

- Compliance scheduling
- Workflow management
- Data-readiness assessment
- Master-data validation
- Data cleanup
- Reporting
- ASP onboarding preparation

The product does not initially provide:

- Official e-invoice transmission
- Peppol access-point services
- FTA representation
- Automated tax filing
- Legal advice
- Guaranteed compliance
- Guaranteed avoidance of penalties

Only formally accredited providers may offer regulated UAE e-invoice transmission services. Do not market the platform as an Accredited Service Provider.

---

## 19. Development Roadmap

## Phase 0: Discovery and Workflow Mapping

- Document current GBR processes
- Select at least 20 representative client cases
- Collect current spreadsheet structures
- Map VAT and Corporate Tax cycles
- Map document-expiry workflows
- Map staff roles and handovers
- Define internal target dates
- Define MVP acceptance tests

## Phase 1: Compliance MVP

- Firm account
- Authentication
- Users and roles
- Client master
- Excel and CSV import
- VAT and Corporate Tax fields
- Manual obligations
- Recurring deadlines
- Dashboard
- Calendar and list views
- Task assignment
- Default workflow
- Internal alerts
- Basic reports
- Audit log

## Phase 2: E-Invoicing Readiness MVP

- Customer and supplier master import
- Column mapping
- Completeness rules
- TRN-format checks
- Duplicate detection
- Readiness score
- Exception queue
- Manual corrections
- Approval workflow
- Clean Excel or CSV export
- Assessment report

## Phase 3: Internal Pilot

- Deploy on Hostinger
- Configure `app.thinkbeyondtax.com`
- Import approved or anonymised GBR data
- Test through a real filing cycle
- Run sample master-data assessments
- Compare results with manual review
- Measure time saved
- Document defects and workflow gaps

## Phase 4: Managed E-Invoicing Readiness Service

- Offer paid readiness assessments
- Process early clients
- Refine data rules
- Create Tally templates
- Produce anonymised case studies
- Validate willingness to pay

## Phase 5: Commercial SaaS

- Harden tenant isolation
- Add subscription billing
- Add customer onboarding
- Complete privacy and contract documents
- Improve reporting
- Add customer-facing notifications
- Implement monitoring and recovery procedures

## Phase 6: Advanced Readiness Features

- OCR
- Secure counterparty forms
- Invoice-data scanning
- Tally-specific exports
- QuickBooks integration
- Zoho Books integration
- Xero integration
- Ongoing monitoring

## Phase 7: Dedicated Licensed Edition

- Automated dedicated deployment
- Licence management
- Maintenance contracts
- Customer-specific configuration
- Dedicated backup procedures
- Custom domains

## Phase 8: Wider Practice Operations

- Client portal
- Secure document vault
- Billing
- Time tracking
- Engagement management
- Broader practice analytics
- API and webhooks

---

## 20. MVP Acceptance Criteria

The compliance MVP is acceptable when:

- A firm administrator can import at least 200 client records.
- Invalid rows are reported without corrupting accepted data.
- Users cannot access another firm's data.
- VAT and Corporate Tax dates can be stored and filtered.
- Managers can see work due within configurable periods.
- Tasks can be assigned to a preparer and reviewer.
- Tasks move through the defined workflow.
- Document-expiry reminders are generated.
- Manual deadline overrides record the user, time and reason.
- A monthly filing report can be exported.
- Significant changes appear in the audit log.
- The application works on desktop and mobile-width screens.
- Backup and restoration have been tested.

The e-invoicing readiness MVP is acceptable when:

- A user can upload a customer or supplier Excel file.
- The user can map columns before import.
- The system identifies incomplete party records.
- The system identifies likely duplicates.
- Every issue has a clear explanation.
- Users can approve or reject proposed corrections.
- Original values remain traceable.
- A readiness score is calculated.
- A cleaned Excel or CSV file can be exported.
- An unresolved-items report can be exported.

---

## 21. Testing Requirements

### 21.1 Automated Tests

- Authentication
- Authorisation
- Tenant isolation
- Client CRUD
- Deadline calculations
- Manual overrides
- Import validation
- Duplicate detection
- Workflow transitions
- Notifications
- Audit logging
- Export generation

### 21.2 Security Tests

- Cross-tenant access attempts
- Privilege escalation
- Invalid file uploads
- Malicious spreadsheet content
- Rate-limit behaviour
- Session expiry
- Password reset
- Support-access logging

### 21.3 User Acceptance Tests

Use real operational scenarios:

- July VAT client list
- Corporate Tax deadline based on financial year-end
- Expiring trade licence
- Missing authorised-signatory document
- Client awaiting records
- Reviewer returns work
- Filing completed but payment pending
- Duplicate supplier ledgers
- Missing customer legal names
- Master-data cleanup and export

---

## 22. User Experience Requirements

- Clean and restrained black, gold and white identity
- Fast dashboard
- Minimal clicks for repetitive work
- Bulk actions for firms with hundreds of clients
- Clear status colours with accessible contrast
- Tables that remain usable with large data volumes
- Search and filters always visible where appropriate
- Mobile-responsive for status checking
- Desktop-first for bulk operations
- Plain language
- No unnecessary technical jargon
- Clear explanations for calculated dates and data-quality issues
- Confirmation before destructive actions

---

## 23. Sales and Go-to-Market

### 23.1 Initial Proof

- Use GBR as the operational pilot
- Measure time saved
- Record reduction in missed follow-ups
- Track master-data issues found
- Create anonymised before-and-after examples

### 23.2 Customer Acquisition

- Direct outreach to UAE accounting firms
- LinkedIn outreach to owners and managers
- Email campaigns
- Product demonstrations
- Professional referrals
- E-invoicing readiness workshops
- Partnerships with consultants
- Potential ASP partnerships
- Targeted advertising

### 23.3 Sales Flow

1. Discovery call
2. Demonstration with synthetic data
3. Limited readiness scan
4. Findings report
5. Paid cleanup or pilot
6. SaaS subscription or dedicated deployment

---

## 24. Revenue Streams

- Monthly SaaS subscriptions
- Annual SaaS subscriptions
- One-time software licences
- Dedicated deployments
- E-invoicing readiness assessments
- Master-data cleanup
- Data migration
- Training
- Customisation
- White labelling
- Annual maintenance
- Managed hosting
- Priority support
- Additional storage
- Future integrations

---

## 25. Legal and Commercial Requirements

Before external sales:

- Identify the legal seller
- Confirm the appropriate UAE commercial licence
- Create subscription terms
- Create perpetual licence terms
- Create a data-processing agreement
- Create privacy policy
- Create acceptable-use policy
- Create support and maintenance terms
- Define service levels
- Define limitation of liability
- Define data retention and deletion
- Document team ownership and revenue shares
- Assign intellectual property ownership
- Protect the product name where commercially justified

---

## 26. Important Risks and Mitigations

### Incorrect Deadline Rules

**Risk:** The application calculates an incorrect statutory date.  
**Mitigation:** Versioned rules, official source links, human verification, manual overrides and clear responsibility terms.

### Data Breach

**Risk:** Sensitive client information is exposed.  
**Mitigation:** Minimal MVP data, strong tenant isolation, encryption, MFA, audit logs, backups and later UAE-region hosting where required.

### Hostinger Limitations

**Risk:** Resource, background-job or data-location limitations affect production.  
**Mitigation:** Use Hostinger for MVP, monitor limits and maintain a portable migration path.

### Low Employee Adoption

**Risk:** Staff continue using spreadsheets and WhatsApp.  
**Mitigation:** Simple workflows, bulk operations, training and management enforcement.

### Overbuilding

**Risk:** Too many advanced modules delay release.  
**Mitigation:** Deliver the compliance MVP and party-readiness MVP before OCR, portals and integrations.

### Unofficial EmaraTax Automation

**Risk:** Fragile or unauthorised portal automation.  
**Mitigation:** No credential storage or scraping. Use manual verification and official integration routes only.

### E-Invoicing Rule Changes

**Risk:** Mandatory fields or timelines change.  
**Mitigation:** Configurable and versioned validation rules, official-source monitoring and maintenance updates.

### One-Time Licence Support Burden

**Risk:** Customers expect unlimited updates forever.  
**Mitigation:** Perpetual licence applies to the purchased version, with twelve months of included support and paid annual maintenance thereafter.

---

## 27. Decisions Already Made

- The product will be built.
- It will support both SaaS and one-time licensed deployments.
- It will include e-invoicing master-data readiness.
- The primary domain is `thinkbeyondtax.com`.
- The initial application target is `app.thinkbeyondtax.com`.
- Hostinger will be considered for the MVP because deployment capacity is already available.
- Hostinger must not become a permanent technical dependency.
- The application will use one core codebase.
- Laravel, Livewire and MySQL are the recommended initial stack.
- The initial SaaS and pilot will use a shared database with mandatory `firm_id` tenant scoping.
- Dedicated deployments will use a separate environment and database while retaining the same core codebase.
- Work, filing and payment statuses will be stored independently.
- The MVP will use database-backed queues and scheduling unless deployment validation confirms a supported managed queue.
- Compliance dates and readiness rules will be versioned, source-linked and published through a human review step.
- Platform source and data will remain inside the `TBT Compliance Platform/` project boundary.
- The calendar will not be the only or main operational view.
- Direct unofficial EmaraTax automation is outside scope.
- Sensitive document storage is deferred until security and hosting controls are ready.

---

## 28. Open Decisions

- Final product name
- Final legal seller
- Exact SaaS prices
- Exact perpetual licence price
- Annual maintenance percentage
- Exact Hostinger plan capabilities
- Production data location
- First accounting-system-specific importer after Excel
- Whether client portal is Phase 5 or later
- Whether WhatsApp is required before commercial launch
- Which ASPs may become partners

---

## 29. Final Product Objective

The initial product is successful when a UAE accounting firm managing hundreds of clients can:

- Know what is due
- Know who is responsible
- Know what is missing
- Know what is at risk
- Know whether work has been reviewed, filed and paid
- Identify incomplete customer and supplier data
- Prepare clients for UAE e-invoicing
- Reduce spreadsheet dependence
- Maintain a reliable, auditable operational record

---

## 30. Official Reference Links

- UAE Ministry of Finance e-invoicing portal:  
  <https://mof.gov.ae/en/about-us/initiatives/einvoicing/>

- Updated 2026 ASP appointment deadline for businesses above AED 50 million:  
  <https://mof.gov.ae/en/news/ministry-of-finance-announces-targeted-amendments-to-einvoicing-system-decisions/>

- E-invoicing Service Provider accreditation requirements:  
  <https://mof.gov.ae/en/services/accreditation-of-einvoicing-service-providers/>

- FTA VAT return filing guidance:  
  <https://tax.gov.ae/en/content/filing.vat.returns.and.making.payments.aspx>

- FTA Tax Records Amendment service:  
  <https://tax.gov.ae/en/services/tax.records.amendment.2023.aspx>

- UAE personal-data protection overview:  
  <https://u.ae/en/about-the-uae/digital-uae/data/data-protection-laws>

- Hostinger managed web-app hosting:  
  <https://www.hostinger.com/my/web-apps-hosting>

- Hostinger server locations:  
  <https://support.hostinger.com/en/articles/1583267-where-are-hostinger-servers-located>

---

# Part II: Implementation Blueprint

## 31. Project Boundary and Source-of-Truth Model

### 31.1 Platform Boundary

`TBT Compliance Platform/` is the application root and the durable boundary for this product.

Keep the following inside this boundary:

- Application source
- Migrations and seeders
- Tests
- Non-secret configuration
- Product and technical documentation
- Synthetic fixtures
- Approved brand assets copied into platform-owned directories
- Runbooks and deployment definitions

Keep the following outside source control:

- Real customer and production data
- Credentials, tokens, private keys and full connection strings
- Uploaded customer documents
- Raw imports and generated exports containing customer data
- Database backups
- Local logs and temporary processing files

Real operational data belongs only in platform-owned databases, private storage and ignored local working directories.

### 31.2 Separation from the Public Website

The public website and compliance platform must not share:

- Application dependencies
- Runtime source imports
- Environment files
- Databases
- authentication secrets
- Session cookies
- Storage buckets or private file paths
- Queue namespaces
- Deployment pipelines

Do not import runtime code from `../v2/`, `../legacy/` or `../v2/node_modules`.

Approved TBT identity assets and design decisions may be copied into platform-owned files. Record the source and approval date for copied assets. Frontend skills are development tools and must never become runtime dependencies.

Any future website-to-platform integration must use a documented, authenticated and versioned interface. It must define consent, data ownership and audit behavior.

### 31.3 Repository Decision

The recommended long-term setup is a separate private Git repository rooted at `TBT Compliance Platform/`, while the folder remains physically within the wider TBT workspace.

Do not initialise, move or link repositories until the product owner approves this topology. Until then:

- Treat the folder as an independent application root.
- Do not mix website and platform changes in one commit.
- Do not commit the platform folder through the public website repository by accident.
- Run platform commands from the platform folder.

### 31.4 Source Precedence

When instructions conflict, use this order:

1. Current product-owner instruction
2. Platform `AGENTS.md` safety and execution rules
3. This approved master plan and approved architecture decision records
4. Feature specifications under `docs/specs/`
5. `MEMORY.md` as the current-state handoff
6. Existing code and tests as implementation evidence

`MEMORY.md` summarises current state. It does not silently change product scope or override an approved decision.

### 31.5 Generated Documents

The Markdown plan is canonical. The Word file is a generated presentation copy and may be older. Regenerate it only when the product owner requests an updated Word edition.

---

## 32. Delivery Objective, Measures and Anti-Goals

### 32.1 First Commercially Useful Outcome

The first commercially useful release is a secure internal compliance operations system that:

- Imports at least 200 approved client records
- Separates firms and user permissions
- Stores actual VAT and Corporate Tax periods
- Generates, assigns and tracks obligations
- Shows due, overdue, blocked and review queues
- Tracks document-expiry metadata
- Records filing and payment state
- Sends reliable internal reminders
- Produces auditable reports
- Restores successfully from backup

The e-invoicing readiness MVP is the next bounded product increment. It must not delay proving the compliance operations core.

### 32.2 Pilot Outcome Measures

Measure these before and after the internal pilot:

- Time required to prepare the monthly compliance schedule
- Number of client records requiring manual reconciliation
- Number of due items discovered late
- Number of tasks without a clear owner
- Time between document request and client response
- Time from preparation completion to reviewer action
- Number of deadline overrides and reasons
- Percentage of obligations completed by internal target date
- Percentage completed by statutory deadline
- Number and severity of readiness issues found
- User adoption by role
- Backup restore time

### 32.3 Initial Scale Targets

Design and test the MVP against:

- 1,000 active clients per firm
- 25 active users per firm
- 25,000 active and historical obligations per firm
- 50,000 party records in one readiness import
- 100,000 party records retained per firm
- 10 concurrent interactive users in the initial target environment

These are engineering targets, not public performance promises. Confirm them through load testing on the selected host.

### 32.4 Explicit Anti-Goals

The MVP will not include:

- A complete accounting ledger or ERP
- Automated EmaraTax login or filing
- FTA representation
- Official e-invoice transmission
- Peppol access-point functions
- A general no-code workflow builder
- Arbitrary user-authored rule expressions
- OCR or AI-generated compliance decisions
- Client billing, payroll or time tracking
- Unrestricted custom code for dedicated customers
- Microservices, event sourcing or a separate frontend application

---

## 33. Architecture Decisions

### 33.1 Architecture Style

Build a modular Laravel monolith with Livewire and MySQL.

Reasons:

- One deployment unit is easier to operate on the initial host.
- Laravel provides authentication, policies, validation, migrations, queues, scheduler, notifications and storage abstractions.
- Livewire supports operational interfaces without a separate API and frontend deployment.
- Module boundaries preserve a later path to APIs or services without paying that complexity now.

Do not introduce microservices, CQRS, event sourcing, GraphQL or a custom rules language during the MVP.

### 33.2 Framework Versions

At build kickoff:

1. Verify the exact Hostinger PHP, MySQL, cron, queue, storage and process limits.
2. Select currently supported stable versions compatible with that environment.
3. Pin exact dependency versions in lock files.
4. Record the decision in an architecture decision record.
5. Test with MySQL in continuous integration.

Do not use SQLite as the only automated-test database because it can hide MySQL differences in constraints, JSON, collation, locking and transaction behavior.

### 33.3 Application Layers

Use these responsibilities:

- Livewire components and controllers coordinate requests and presentation.
- Form objects or request validators validate input.
- Policies enforce server-side authorisation.
- Application actions execute one use case.
- Domain services hold reusable business logic.
- Models represent persistence and relationships.
- Events describe completed domain changes.
- Listeners perform secondary work after database commit.
- Jobs perform bounded asynchronous work.

Keep Livewire components thin. Do not place deadline calculation, import reconciliation, duplicate scoring or tenant resolution directly inside presentation components.

### 33.4 Module Boundaries

Initial modules:

- Identity
- Tenancy
- Clients
- Compliance
- Workflows
- Documents
- Imports
- Readiness
- Notifications
- Reporting
- Audit
- Commercial
- Platform Administration

Modules may share the same Laravel application and database. They must communicate through clear actions, services and events, not hidden model side effects.

### 33.5 Persistence Conventions

- Use MySQL as the supported MVP database.
- Use migrations for every schema change.
- Use transactions around multi-record business operations.
- Use foreign keys and tenant-aware unique indexes.
- Prefer explicit tables over broad polymorphic relationships for core compliance data.
- Use soft deletion only where recovery and retention requirements justify it.
- Use archive status for records that must remain operationally visible.
- Never hard-delete audit records through ordinary application flows.
- Store statutory dates as UAE-local `DATE` values unless an official time is material.
- Store event timestamps as UTC and present them in the firm timezone.
- Default firm timezone to `Asia/Dubai`.

### 33.6 Infrastructure Abstractions

Use standard Laravel interfaces:

- Database-backed queue for the first host, with Redis allowed later through configuration
- One scheduler cron entry
- Laravel filesystem disks for private and public storage
- SMTP or transactional-email configuration through the mail abstraction
- Environment variables for secrets and host-specific settings
- Cache prefixes containing the environment and firm where tenant-owned data is cached

Business logic must not depend on Hostinger-specific APIs.

---

## 34. System Context and Trust Boundaries

### 34.1 Actors

- Platform administrator
- Firm administrator
- Manager
- Preparer
- Reviewer
- Data cleanup operator
- Read-only user
- Scheduled system process
- Controlled support user
- Future client portal user
- Future integration service

### 34.2 External Systems

MVP external systems:

- Email delivery provider
- Private application storage
- Backup destination
- Host monitoring

Later external systems:

- Accounting platforms
- Official WhatsApp Business provider
- Calendar providers
- Accredited Service Providers
- Payment and subscription provider

EmaraTax is a manual operational reference in the MVP, not a system integration.

### 34.3 Data Flow Rule

Every incoming or outgoing data flow must define:

- Responsible actor
- Firm context
- Purpose
- Data classification
- Validation
- Authorisation
- Audit event
- Retention
- Failure and retry behavior
- Reconciliation method

No external integration is complete until the system can prove which records were accepted, rejected, retried and reconciled.

---

## 35. Canonical Domain Model

### 35.1 Tenancy and Identity

| Entity | Purpose | Important invariants |
|---|---|---|
| `firms` | Tenant workspace | Every active workspace has timezone, status and configuration |
| `users` | Global human identity | One user may belong to more than one firm |
| `firm_users` | Firm membership | Role and access belong to membership, not global identity |
| `roles` | Firm-scoped role definition | Cannot grant permissions outside the firm |
| `permissions` | Named server capability | Checked by policy or action, not only hidden in UI |
| `support_access_grants` | Time-limited support access | Requires reason, approver, expiry and full audit |

### 35.2 Client and Service Records

| Entity | Purpose | Important invariants |
|---|---|---|
| `clients` | Canonical client identity | Unique client code within firm, lifecycle status preserved |
| `client_contacts` | Client contacts | Contact purpose and preferred channel are explicit |
| `client_service_enrollments` | Services handled for client | Contains start, end, status, assignees and workflow template |
| `tax_registrations` | VAT and Corporate Tax identifiers | Identifier type and status are explicit |
| `tax_periods` | Actual periods assigned to client | Do not infer calendar quarters without verified data |
| `client_licences` | Trade and operating licences | Separate from product software licences |
| `client_documents` | Metadata and future private files | MVP stores metadata only unless secure-file gate passes |
| `document_types` | Configurable metadata schema | Expiry behavior is configurable and versioned |

### 35.3 Compliance and Work

| Entity | Purpose | Important invariants |
|---|---|---|
| `obligation_rule_templates` | Named calculator type | Uses approved code calculator, not arbitrary expressions |
| `obligation_rule_versions` | Immutable published rule | Effective dates, source, verifier and parameters are required |
| `generation_runs` | One controlled generation operation | Reruns are idempotent and fully reconciled |
| `obligations` | What is legally or operationally due | Snapshots rule, inputs, result and explanation |
| `obligation_overrides` | Manual change history | Requires actor, reason, old value and new value |
| `work_items` | Operational process for an obligation | Separate from the obligation and its due date |
| `workflow_definitions` | Workflow template | Versioned and immutable after publication |
| `workflow_steps` | Allowed states and transitions | Transition permissions are explicit |
| `work_item_transitions` | Append-only history | Existing history is never rewritten |
| `assignment_history` | Preparer, reviewer and manager history | Reassignment never erases the former owner |
| `checklists` | Versioned checklist template | Work item snapshots the applied version |
| `filing_records` | Filing state and evidence | Filing state remains separate from work state |
| `payment_records` | Payment state and evidence | Payment state remains separate from work and filing |

One obligation normally has one primary work item. A controlled reopen or correction may create a linked follow-up work item without changing the original completed history.

### 35.4 Imports and Readiness

| Entity | Purpose | Important invariants |
|---|---|---|
| `import_batches` | Import lifecycle and reconciliation | Every input row reaches one final outcome |
| `import_files` | File metadata and checksum | Raw file retention follows approved policy |
| `mapping_templates` | Reusable source-to-target mapping | Versions preserve historical interpretation |
| `import_rows` | Staged source and normalized values | Row number and validation results remain traceable |
| `party_records` | Canonical customer or supplier | Supports both roles without duplicate identity |
| `party_identifiers` | TRN and other identifiers | Identifier type, country and verification state are explicit |
| `party_addresses` | Structured address | Provenance and verification state are retained |
| `party_field_sources` | Field-level provenance | Approved value can be traced to import or human correction |
| `data_quality_rule_versions` | Immutable readiness rule | Severity, applicability, source and explanation are required |
| `party_issues` | Detected issue | Status, reason, evidence and resolution are explicit |
| `correction_proposals` | Proposed field change | Approval never overwrites history silently |
| `duplicate_candidates` | Suggested match | Contains signals, score and human decision |
| `party_merge_events` | Approved merge history | Surviving record and recovery information are retained |
| `readiness_assessments` | Versioned assessment snapshot | Scoring formula version and inputs are stored |
| `confirmation_requests` | Future secure external request | Response enters review before canonical data changes |

### 35.5 Platform Operations

| Entity | Purpose | Important invariants |
|---|---|---|
| `notifications` | Requested notification | Channel, recipient, template and firm are explicit |
| `notification_attempts` | Delivery evidence | Retry outcome and provider reference are retained |
| `audit_logs` | Append-only significant activity | Ordinary users cannot edit or delete |
| `feature_flags` | Controlled release | Scope may be environment, firm or role |
| `subscriptions` | SaaS commercial status | Entitlements are separate from role permissions |
| `software_licences` | Dedicated-edition licence | Separate from client trade licences |

---

## 36. Multi-Tenancy and Authorisation Specification

### 36.1 Model

Use a shared application and shared database with mandatory `firm_id` row scoping for SaaS and the internal pilot.

Dedicated deployments use the same tenant-aware schema in a separate environment and database. Do not maintain a separate customer branch or remove tenant controls.

### 36.2 Tenant Resolution

- Resolve active firm from the authenticated user's valid membership.
- Never trust a submitted `firm_id` as proof of access.
- Reject a missing, suspended or unauthorised firm context.
- Use scoped route model binding.
- Require policies for every record read or mutation.
- Require the active firm in Livewire actions, jobs, exports and notifications.

### 36.3 Tenant-Owned Records

Every tenant-owned table must:

- Have a non-null `firm_id`
- Include `firm_id` in relevant unique indexes
- Set firm context through a trusted application action
- Reject cross-firm relationships
- Be covered by cross-tenant tests

Tenant scoping must include:

- Web requests
- Livewire calls
- Search and autocomplete
- Reports
- Queued jobs
- Scheduled jobs
- Cache entries
- Files and downloads
- Imports and exports
- Notifications
- Audit queries

### 36.4 Support Access

Do not provide unrestricted super-administrator data access.

Support access must:

- Be explicitly requested
- State a reason and affected firm
- Be approved where policy requires
- Expire automatically
- Display an active-support banner
- Record every view, export and mutation
- Allow immediate revocation

### 36.5 Required Isolation Tests

For each tenant-owned resource, prove that a user from Firm A cannot:

- List Firm B records
- Guess or bind a Firm B record identifier
- Update or delete a Firm B record
- Search for Firm B data
- Download Firm B files
- Trigger a job against Firm B
- Receive Firm B notifications
- Access Firm B cached results
- Include Firm B rows in an export

Tenant isolation is a foundation requirement and cannot be deferred to commercial hardening.

---

## 37. Obligation Rule Engine Specification

### 37.1 Safe Rule Model

Use named PHP calculator classes plus validated versioned parameters.

Do not execute:

- User-authored code
- Arbitrary formulas
- Dynamic SQL
- Unreviewed AI output

Each calculator must expose:

- Calculator key
- Accepted inputs
- Validated parameters
- Calculation result
- Human-readable explanation
- Test fixtures

### 37.2 Rule Lifecycle

Rule version states:

1. Draft
2. Under review
3. Approved
4. Published
5. Superseded
6. Retired

A published rule version is immutable. A correction creates a new version.

Required rule metadata:

- Obligation type
- Jurisdiction and authority
- Effective start and end
- Applicability criteria
- Calculator key
- Parameters
- Official source title and URL
- Source publication date where available
- Last verified date
- Prepared by
- Verified by
- Approval timestamp
- Change summary

### 37.3 Generation

Generation is a previewable and idempotent operation.

Each generated obligation stores:

- Firm and client
- Service enrollment
- Tax or compliance period
- Rule version
- Calculation input snapshot
- Statutory due date
- Internal target date
- Calculation explanation
- Generation run
- Deterministic generation key

The generation key must prevent scheduler reruns from creating duplicates.

### 37.4 Changed Rules

Never overwrite an issued obligation silently.

When a new rule may affect an existing obligation:

1. Calculate the proposed result.
2. Show old and new dates plus the reason.
3. Preserve the original rule snapshot.
4. Require authorised approval to supersede the issued obligation.
5. Notify affected owners where required.
6. Audit the decision.

### 37.5 Manual Overrides

An override requires:

- Permission
- Old value
- New value
- Reason
- Actor
- Timestamp
- Optional evidence

The interface must clearly distinguish calculated and overridden values.

### 37.6 Date Policy

- Use `Asia/Dubai` for local statutory-date interpretation.
- Store date-only deadlines as `DATE`.
- Do not shift weekends or public holidays unless an approved rule explicitly requires it.
- Model official extensions as new rule versions or scoped override events.
- Do not infer a client's assigned VAT periods from filing frequency alone.

### 37.7 Golden Cases

Before publishing a calculator, create manually verified cases covering:

- Normal date
- Month-end
- Leap year
- Period ending on non-working day
- Official extension
- Client-specific period
- Manual override
- Rule supersession
- Rerun idempotency

Every golden case records its official source and verification date.

---

## 38. Workflow and Task Specification

### 38.1 Separate State Dimensions

Store:

- Work status
- Filing status
- Payment status
- Risk status

Do not compress these into one database field.

### 38.2 Initial Work States

- `not_started`
- `documents_requested`
- `awaiting_client`
- `ready_for_preparation`
- `in_preparation`
- `under_review`
- `returned_for_changes`
- `awaiting_client_approval`
- `ready_to_file`
- `completed`
- `cancelled`

### 38.3 Transition Rules

Each transition defines:

- Starting state
- Ending state
- Allowed roles
- Required checklist items
- Required note or reason
- Required evidence
- Notification event
- Audit event

Invalid transitions must fail on the server with a clear explanation.

### 38.4 Versioning

Workflow and checklist definitions become immutable when published. Existing work remains pinned to the version under which it began. A manager may migrate open work to a newer version only through an explicit, audited action.

### 38.5 Assignment

Assignments include:

- Preparer
- Reviewer
- Responsible manager
- Effective time
- Assigned by
- Reassignment reason

Do not overwrite assignment history.

### 38.6 Constrained Configuration

The MVP supports approved workflow templates and controlled transitions. A free-form workflow designer is outside scope until real customer variation proves it is required.

---

## 39. Import and Export Specification

### 39.1 Import State Machine

Each import moves through:

1. Uploaded
2. Mapped
3. Validating
4. Preview ready
5. Approved
6. Committing
7. Completed
8. Failed
9. Reversed where safely permitted

### 39.2 Processing Model

- Stage rows before canonical writes.
- Parse large files in bounded chunks through queues.
- Record original row number.
- Record source values and normalized values.
- Validate before commit.
- Present a preview and error summary.
- Require approval for canonical writes.
- Use a transaction or controlled chunk reconciliation.
- Record committed canonical identifiers.

### 39.3 Row Outcomes

Every input row must end as exactly one of:

- Committed
- Skipped
- Quarantined for review
- Failed

An import is not complete until row counts reconcile.

### 39.4 Conflict Behavior

Each importer must define:

- Insert
- Update matched record
- Skip unchanged record
- Quarantine conflict
- Reject invalid record

Never guess update behavior from a similar name alone.

### 39.5 Idempotency and Reversal

- Hash uploaded files.
- Detect accidental duplicate uploads.
- Use a mapping-version and row-level idempotency key.
- Permit simple reversal only while no dependent edits exist.
- Use compensating audited changes after dependent work exists.

### 39.6 File Safety

Enforce:

- File type allowlist
- Maximum compressed and expanded size
- Maximum rows, columns and cell length
- Encoding validation
- Formula and macro handling
- Malware scanning before sensitive-file support
- Timeouts and memory limits
- Private storage
- Retention and deletion policy

Sanitise spreadsheet exports against formula injection.

### 39.7 Raw File Retention

For synthetic development files, retention may be local and short.

For approved operational files, define before use:

- Encryption
- Storage region
- Access control
- Retention duration
- Deletion process
- Backup inclusion
- Customer agreement

Do not preserve raw customer uploads indefinitely by default.

---

## 40. E-Invoicing Readiness Specification

### 40.1 Readiness Boundaries

The module assesses data readiness. It does not transmit e-invoices or guarantee compliance.

Keep separate:

- Party master readiness
- Invoice transaction readiness

Do not combine both into one unexplained score.

### 40.2 Rule Lifecycle

Data-quality rules use the same draft, review, approval, publication and supersession discipline as obligation rules.

Each rule defines:

- Field or scenario
- Applicability
- Severity
- Blocking or warning behavior
- Explanation
- Remediation guidance
- Official or internal source
- Formula-version effect

### 40.3 Normalisation

Normalisation must be deterministic and non-destructive.

Store:

- Source value
- Normalized candidate
- Applied normaliser version
- Approval state
- Approved canonical value

Do not silently change legal names, TRNs or addresses.

### 40.4 Duplicate Detection

Start with explainable deterministic signals:

- Exact normalized TRN
- Exact normalized email or telephone
- Exact normalized legal name
- Similar normalized name
- Shared address
- Shared source identifier

A duplicate candidate records each signal and its contribution. No record is merged automatically.

### 40.5 Merge

An approved merge must:

- Show both records
- Select the surviving record
- Select values field by field where necessary
- Preserve aliases and source references
- Redirect dependent references safely
- Record a merge event
- Support authorised recovery where practical

### 40.6 Scoring

Before building the readiness dashboard, approve a documented formula that defines:

- Included rules
- Severity weights
- Applicable-record denominator
- Treatment of not-applicable fields
- Treatment of unresolved duplicates
- Rounding
- Formula version

Every assessment stores the formula version and input counts so the score can be reproduced.

### 40.7 Human Approval

Corrections from imports, documents, OCR, AI or counterparties remain proposals until an authorised human approves them. Preserve old value, proposed value, evidence, confidence where relevant, approver and timestamp.

---

## 41. Product Information Architecture and UX Plan

### 41.1 Interaction Mode

The application uses an Operate interface. Users arrive to complete compliance work quickly and accurately. Scanability, consistency, clear feedback and keyboard access take priority over decorative motion.

### 41.2 Primary Navigation

- Home
- Work
- Clients
- Compliance
- Documents
- E-Invoicing Readiness
- Reports
- Notifications
- Settings

Platform administrators receive a separate administration area.

### 41.3 Home Dashboard

The dashboard answers:

- What is overdue?
- What is due next?
- What is blocked by a client?
- What is waiting for review?
- What is unassigned?
- Where is workload concentrated?
- Which documents are expiring?
- Which readiness issues need action?

Use operational queues with clear counts and drill-down. Do not make the calendar the main dashboard.

### 41.4 Core Screens

Compliance MVP:

- Sign in and account recovery
- Firm and user onboarding
- Dashboard
- Work queue
- Client directory
- Client detail with service, period, document and history tabs
- Obligation detail
- Work item detail
- Import wizard
- Calendar and timeline
- Reports
- Users, roles and workflow settings
- Audit viewer for authorised users

Readiness MVP:

- Assessment list
- Upload and mapping wizard
- Validation preview
- Issue queue
- Party record review
- Duplicate comparison
- Correction approval
- Readiness dashboard
- Export centre

### 41.5 Table Behavior

Operational tables must support:

- Server-side pagination
- Search
- Visible filters
- Saved views
- Column sorting
- Bulk selection
- Bulk actions with confirmation
- Clear empty, loading, error and permission states
- Sticky headers where useful
- Export of the currently authorised filtered dataset

Do not rely on horizontal scrolling for the primary action. Mobile views may reduce columns and open a detail sheet.

### 41.6 Visual System

Create platform-local design tokens derived from approved TBT identity:

- Ink and charcoal surfaces
- Restrained gold for focus and active guidance
- Silver or warm-white primary text
- Accessible status colors with text or icons
- Subtle hexagonal references
- Inter for interface text
- A single approved display typeface

The website currently specifies Lato while the old platform draft mentioned Sora-style headings. Default to Lato plus Inter for continuity unless the product owner approves a platform-specific change.

Do not copy website components or heavy homepage motion. Motion in the application should be limited to useful state transitions and must respect reduced-motion settings.

### 41.7 Accessibility

Target WCAG 2.2 AA:

- Full keyboard operation
- Visible focus
- Semantic labels and headings
- Accessible validation and error summaries
- Status never conveyed by color alone
- Sufficient contrast
- Touch targets suitable for mobile
- Reduced motion
- Screen-reader announcements for asynchronous completion

Test at 375, 768, 1024 and 1440 pixel widths.

### 41.8 Responsive Priority

- Desktop: full operation, imports, bulk changes and reporting
- Tablet: management review and moderate editing
- Mobile: status checking, approvals, comments and urgent actions

Complex column mapping and large-data cleanup may be desktop-only if the interface clearly explains the requirement.

---

## 42. Background Jobs, Notifications and Idempotency

### 42.1 Job Rules

Every job must:

- Carry a trusted firm context
- Be idempotent
- Have a bounded batch size
- Define retry and timeout behavior
- Record failure without exposing sensitive data
- Emit a correlation identifier
- Be safe to rerun

### 42.2 Scheduled Operations

Initial scheduled jobs:

- Obligation generation
- Reminder creation
- Notification delivery
- Overdue escalation
- Daily manager summary
- Weekly compliance summary
- Expired temporary-file cleanup
- Health and queue checks

### 42.3 Notification Delivery

Separate notification creation from delivery attempts.

Store:

- Template version
- Channel
- Recipient
- Firm
- Triggering record
- Scheduled time
- Attempt count
- Final status
- Provider reference
- Failure reason

Prevent duplicate messages with deterministic notification keys.

### 42.4 Delivery Expectations

Before pilot, define:

- Maximum acceptable reminder delay
- Retry schedule
- Dead-letter review process
- Who receives delivery failures
- How a manager confirms critical notices

Email is the only external notification channel required for MVP. WhatsApp remains a later integration.

---

## 43. Security and Privacy Control Plan

### 43.1 Data Classification

Use four classes:

- Class 0: public information
- Class 1: internal operational information
- Class 2: confidential client and compliance information
- Class 3: sensitive identity documents, credentials and high-risk personal data

The MVP may use Classes 1 and 2 only after hosting, access and retention controls are approved. Class 3 storage is blocked until the secure-file gate passes.

Document-expiry dates and identity metadata can still be personal data. Do not treat metadata as automatically low risk.

### 43.2 Foundation Controls

Required before domain features use approved data:

- Authentication and secure password hashing
- MFA for privileged roles before real operational use
- Firm-scoped authorisation
- CSRF protection
- Output escaping
- Strict validation and mass-assignment protection
- Rate limiting
- Secure session settings
- Host-only platform cookies
- Secrets outside source control
- Dependency and vulnerability scanning
- Append-only audit records

### 43.3 Threat Model

Complete a threat model before pilot covering:

- Cross-tenant access
- Insecure direct object references
- Privilege escalation
- Malicious spreadsheet files
- Spreadsheet formula injection
- Stored and reflected XSS
- SQL injection
- Session theft
- Password reset abuse
- Support access misuse
- Export leakage
- Backup exposure
- Notification data leakage
- Queue context leakage
- Unsafe file access

### 43.4 Audit Schema

Audit entries should include:

- Firm
- Actor type and identifier
- Support-access grant where relevant
- Action
- Record type and identifier
- Safe before and after change summary
- Reason
- Request correlation identifier
- Import or generation batch
- IP address where appropriate
- User agent where appropriate
- UTC timestamp

Redact secrets and sensitive values. Do not duplicate whole records into audit logs.

### 43.5 Retention and Deletion

Before real data, approve a schedule for:

- Active clients
- Disengaged clients
- Imports
- Exports
- Audit records
- Notifications
- Uploaded files
- Backups
- Deleted users
- Support-access evidence

Deletion must respect legal, contractual and audit requirements. Where deletion is not permitted, restrict and archive access.

### 43.6 Commercial Security Gate

Before external production users:

- Independent security review
- Penetration testing
- Privacy notice and data-processing agreement
- Subprocessor list
- Cross-border transfer review
- Incident response exercise
- Restore test
- Defined and tested recovery objectives
- Support-access controls

---

## 44. Reliability, Observability and Recovery

### 44.1 Health Monitoring

Monitor:

- Application availability
- Database connectivity
- Queue age and failures
- Scheduler heartbeat
- Email delivery failures
- Storage availability
- Backup completion
- Disk usage
- Error rate
- Response time

### 44.2 Logging

- Use structured logs.
- Include correlation identifiers.
- Include firm identifiers only where safe.
- Never log passwords, tokens, document contents or unnecessary PII.
- Separate security events from ordinary diagnostic logs.
- Define retention and access.

### 44.3 Backup

Back up:

- Database
- Private stored files
- Configuration required for recovery, excluding plaintext secrets
- Release and schema version metadata

Maintain an encrypted backup outside the primary host.

### 44.4 Restore Acceptance

A backup is not accepted until restored into a clean isolated environment and verified through:

- Schema version
- Record counts
- Critical checksums or sampled records
- Authentication
- Tenant isolation
- Representative client, obligation and audit history
- Private-file retrieval where enabled
- Queue and scheduler restart

### 44.5 Initial Recovery Targets

Propose and approve before pilot:

- Recovery point objective: 24 hours or less
- Recovery time objective: 8 hours or less

Tighten these before commercial launch based on customer commitments and hosting capability.

---

## 45. Delivery Roadmap and Exit Gates

### Stage 0: Project Control

Deliver:

- Platform `AGENTS.md`
- Platform `CLAUDE.md`
- Platform `MEMORY.md`
- Approved source hierarchy
- Decision and specification directory structure
- Work-packet template
- Secret and data-handling rules
- Repository-boundary decision

Exit gate:

- A new Codex or Claude Code session can identify the current phase, constraints and next task without relying on chat history.

### Stage 1: Discovery and Architecture Closure

Deliver:

- Map at least 20 representative client cases
- Define GBR and identify its data owner and pilot approver
- Current spreadsheet inventory
- Actor and permission matrix
- Data dictionary and classification
- Retention and deletion schedule
- Hostinger capability matrix
- Threat model
- Target-volume fixture design
- Approved architecture decision records
- Golden VAT and Corporate Tax cases
- Import conflict and reversal specification
- Readiness scoring specification

Required decisions:

- Supported framework versions
- Hosting and data location for each pilot data class
- Rule owner and reviewer
- Deadline update behavior
- Notification delay and escalation
- Audit retention
- Repository topology

Exit gate:

- No unresolved decision can materially change the foundation schema or security model.

### Stage 2: Secure Engineering Foundation

Build in order:

1. Laravel and Livewire scaffold
2. Environment validation and secret handling
3. MySQL migrations and synthetic seed framework
4. Authentication, invitations, reset and session controls
5. Firms, users and memberships
6. Firm-scoped roles and permissions
7. Tenant context, scoping and policies
8. Append-only audit infrastructure
9. Feature flags
10. Queue and scheduler skeleton
11. CI, formatting, static analysis, dependency scanning and tests
12. Staging deployment skeleton
13. Backup and restore proof

Exit gate:

- Tenant-isolation matrix passes for requests, Livewire, jobs, exports, files, notifications and cache.
- A clean database can migrate and seed.
- Backup restores successfully.
- Only synthetic data has been used.

### Stage 3: Compliance Walking Skeleton

Deliver one complete vertical flow:

1. Firm administrator creates a client.
2. Manager creates a manual obligation.
3. Manager assigns preparer and reviewer.
4. Preparer updates checklist and submits work.
5. Reviewer returns or approves it.
6. Manager records filing and payment states.
7. Dashboard and client timeline update.
8. Significant actions appear in audit history.
9. Firm B cannot discover any Firm A record.

Exit gate:

- The architecture supports one complete real workflow before bulk and rule complexity is added.

### Stage 4: Compliance MVP

Work packages:

#### C1. Client Master

- Client identity, contacts and lifecycle
- Service enrollments
- Tax registrations and actual periods
- Assigned team
- Client history

#### C2. Client Import

- Excel and CSV upload
- Mapping
- Validation preview
- Reconciliation
- Error report
- Idempotent commit
- Safe reversal rules

#### C3. Rule and Obligation Engine

- Rule lifecycle
- VAT and Corporate Tax calculators
- Manual obligations
- Generation preview
- Idempotent generation
- Supersession
- Overrides
- Golden tests

#### C4. Workflows and Checklists

- Versioned templates
- Allowed transitions
- Assignment history
- Review return flow
- Filing and payment records

#### C5. Document Expiry

- Metadata types
- Expiry dates
- Reminder policies
- Overdue escalation
- No sensitive scans

#### C6. Dashboard and Queues

- Due windows
- Overdue
- Awaiting client
- Under review
- Unassigned
- Workload
- Saved filters

#### C7. Calendar and Timeline

- Month and week views
- List view
- Client timeline
- Accessible filtering

#### C8. Notifications

- In-app notices
- Internal email
- Manager summaries
- Retry, failure and idempotency

#### C9. Reports and Exports

- Monthly schedule
- Tax-period lists
- Expiring documents
- Workload and completion
- Spreadsheet-safe exports

Exit gate:

- The measurable compliance MVP acceptance suite passes with the 200-client fixture.
- No critical or high-severity defect remains.

### Stage 5: E-Invoicing Readiness MVP

Work packages:

#### E1. Readiness Import

- Customer and supplier files
- Mapping and normalization
- Staging and reconciliation
- Original-file policy

#### E2. Data-Quality Rules

- Versioned rules
- Severity and applicability
- Explainable issues
- Source and remediation

#### E3. Review and Corrections

- Issue queue
- Proposed corrections
- Approval and rejection
- Field provenance

#### E4. Duplicate Review

- Explainable signals
- Candidate comparison
- Human merge
- Merge history and recovery

#### E5. Scoring and Reporting

- Approved scoring formula
- Reproducible assessment
- Separate party score
- Dashboard and progress

#### E6. Clean Export

- Clean customer and supplier data
- Unresolved-item report
- Merge report
- Change history
- Spreadsheet-safe output

Exit gate:

- A labelled golden dataset produces the expected issues, duplicates, approvals, score and export.
- Every imported row reconciles.
- No automatic legal-identity correction or merge occurs.

### Stage 6: Internal Pilot

Use data in increasing risk order:

1. Synthetic demonstration data
2. Anonymised representative cases
3. Specifically approved internal data
4. Real operational use only after privacy and data-location approval

Pilot requirements:

- At least 200 representative clients
- One complete filing cycle
- Manual comparison of generated deadlines
- Notification delivery evidence
- Readiness comparison against accountant review
- Backup restored into a clean environment
- User feedback by role
- Adoption and time-saving measures
- Defect and workflow-gap review

Exit gate:

- No unexplained cross-tenant, deadline, import or audit discrepancy.
- No unresolved critical or high-severity defect.
- Recovery and operational ownership are demonstrated.

### Stage 7: Managed Readiness Service

This stage is blocked until:

- Legal seller is identified
- Appropriate UAE commercial licence is confirmed
- Service agreement and data-processing terms are approved
- Human review responsibilities are documented
- Secure transfer and deletion procedures are tested
- Quality-control runbook is approved

### Stage 8: Commercial SaaS

Deliver:

- Customer onboarding
- Subscription and entitlement controls
- MFA
- Monitoring and incident response
- Production-grade backups and recovery
- Privacy and contract documents
- Support-access workflow
- Independent security review
- Billing integration
- Service and support runbooks

Exit gate:

- External security, legal, privacy, recovery and support gates all pass.

### Stage 9: Advanced Readiness

Add one capability at a time:

- Secure counterparty forms
- OCR
- Invoice transaction scanning
- Tally-specific formats
- QuickBooks integration
- Zoho Books integration
- Xero integration
- Ongoing monitoring

Each capability needs its own privacy, reconciliation, accuracy and rollback specification.

### Stage 10: Dedicated Edition and Wider Operations

Dedicated edition:

- Repeatable provisioning
- Configuration without customer forks
- Licence and version management
- Upgrade and rollback
- Dedicated monitoring and backup
- Custom domain
- Supported-version policy

Wider operations remain separate later epics:

- Client portal
- Secure document vault
- Billing
- Time tracking
- Engagement management
- API and webhooks

---

## 46. Work-Packet Contract for Lower-Effort Build Sessions

Each implementation session should receive one bounded work packet containing:

- Objective
- User and business outcome
- In-scope behavior
- Explicit exclusions
- Dependencies
- Files or modules expected to change
- Data model impact
- Permission requirements
- Audit events
- UI states
- Given, When, Then acceptance criteria
- Test fixtures
- Exact verification commands
- Documentation and memory updates
- Rollback or forward-recovery approach

Do not ask a lower-effort build session to implement an entire phase at once.

Preferred packet size:

- One schema slice plus its model and tests
- One action or workflow plus its policies and tests
- One screen backed by completed domain actions
- One importer stage
- One calculator and its golden cases

Every work packet ends with:

1. Run focused tests.
2. Run the full relevant test suite.
3. Run formatting and static analysis.
4. Inspect the diff.
5. Update documentation and `MEMORY.md`.
6. Record unresolved risks.

---

## 47. Definition of Ready

An epic or work packet is ready only when it has:

- Named outcome and owner
- Actor and permission requirements
- In-scope and out-of-scope behavior
- Resolved architecture decisions
- Data ownership and classification
- Dependencies
- UI or workflow states
- Given, When, Then acceptance criteria
- Synthetic fixtures or golden files
- Audit requirements
- Empty, loading, validation, failure and retry behavior
- Migration and recovery approach
- Official source and verification date for regulated logic
- No open decision that would materially change implementation

If these are missing, the session should refine the specification before coding.

---

## 48. Definition of Done

Work is done only when:

- Acceptance criteria pass.
- Server-side authorisation is implemented.
- Tenant-isolation tests pass.
- Unit, feature, integration and relevant browser tests pass.
- Business-critical branches are covered.
- Overall coverage remains at or above the approved baseline, targeting 80 percent or higher in line with ECC discipline.
- Formatting, static analysis, dependency audit and production asset build pass.
- Migrations work on a clean database and an upgraded database.
- Rollback or forward recovery is tested.
- Audit events contain required context without secrets.
- Imports and exports handle malicious or malformed spreadsheet content.
- Loading, empty, validation, failure and permission-denied states exist.
- Desktop and supported mobile-width behavior is checked.
- Accessibility requirements pass.
- Logs contain no secrets or inappropriate PII.
- Relevant specifications and decision records are updated.
- `MEMORY.md` reflects actual current state and test results.
- The feature is verified in staging behind a feature flag where appropriate.

ECC JavaScript naming and `*.test.js` conventions describe the upstream ECC repository. They do not override Laravel, PHP, Livewire, Pest or PHPUnit conventions.

---

## 49. Test Strategy

### 49.1 Unit Tests

Cover:

- Deadline calculators
- Rule applicability
- Normalisation
- Readiness scoring
- Duplicate signals
- Workflow transitions
- Permission predicates
- Idempotency keys

### 49.2 Feature Tests

Cover:

- Authentication
- Firm membership
- Policies
- Tenant CRUD isolation
- Livewire actions
- Obligation generation and override
- Work transitions
- Imports
- Notifications
- Reports and exports
- Audit events

### 49.3 Integration Tests

Cover:

- MySQL transactions and constraints
- Queue execution
- Scheduler behavior
- Mail delivery adapter
- Private storage
- Backup and restore
- External integration contracts when introduced

### 49.4 Browser Tests

Critical journeys:

- Firm administrator onboarding
- Manager assignment and dashboard
- Preparer submission
- Reviewer return and approval
- Filing and payment update
- Client import
- Readiness review
- Duplicate merge
- Report export
- Permission denial

### 49.5 Security Tests

Cover:

- Insecure direct object reference
- Cross-tenant access
- Privilege escalation
- Mass assignment
- CSRF
- XSS
- SQL injection
- Rate limits
- Session expiry
- Password reset
- Malicious spreadsheets
- Unsafe downloads
- Support access

### 49.6 Performance Tests

Test:

- 200-client pilot fixture
- 1,000-client target fixture
- 25,000 obligations
- 50,000-row readiness import
- Dashboard response under realistic filters
- Queue completion and retry
- Export memory use

Record actual environment and results. Do not turn internal targets into marketing claims without verified evidence.

### 49.7 User Acceptance Tests

Maintain scripted operational cases with:

- Starting data
- User role
- Expected actions
- Expected calculations
- Expected audit events
- Expected notifications
- Expected reports
- Sign-off owner

---

## 50. Environments, Releases and Change Control

### 50.1 Environments

Maintain separate:

- Local development
- Automated test
- Demo with synthetic data
- Staging
- Production

Each environment must have separate:

- Database
- Storage
- Mail credentials
- Queue namespace
- Cache prefix
- Secrets
- Application URL

### 50.2 Release Flow

1. Work in a short-lived branch.
2. Implement one approved packet.
3. Pass verification.
4. Review tenant, security and data impacts.
5. Deploy to staging.
6. Run smoke and migration tests.
7. Approve release.
8. Back up production.
9. Deploy tagged release.
10. Verify health, queues and critical journeys.
11. Record release and update `MEMORY.md`.

### 50.3 Migration Rules

- Never edit a migration already used by a shared environment.
- Prefer additive and backward-compatible schema changes.
- Separate destructive cleanup from the release that stops using old fields.
- Define forward recovery where database rollback could lose data.
- Test upgrades from every supported release.

### 50.4 Feature Flags

Use flags for:

- Incomplete modules
- Pilot-only behavior
- Firm-specific rollout
- Risky migrations in behavior
- New calculators or scoring formulas

Feature flags do not replace authorisation.

### 50.5 Conventional Commits

Apply ECC workflow discipline with concise imperative commits:

- `feat(platform): add firm-scoped client records`
- `fix(compliance): prevent duplicate obligation generation`
- `test(tenancy): cover queued-job isolation`
- `docs(platform): record import reversal policy`

Keep website and platform work in separate commits.

---

## 51. Cross-LLM Handoff and Memory Protocol

### 51.1 Required Startup Sequence

Every Codex, Claude Code or other LLM session should:

1. Set the working directory to `TBT Compliance Platform/`.
2. Read `AGENTS.md`.
3. Read this master plan.
4. Read `MEMORY.md`.
5. Read relevant decision records and feature specifications.
6. Inspect Git status and recent history.
7. Confirm the exact work packet.

### 51.2 Memory Update Events

Update `MEMORY.md` whenever any of these changes:

- Current objective
- Phase or milestone
- Architecture decision
- Schema or migration state
- Implemented behavior
- Dependency
- Verification result
- Deployment state
- Defect or blocker
- Next task

### 51.3 Memory Boundaries

`MEMORY.md` is a concise handoff, not a transcript.

Never include:

- Secrets
- Customer names or identifiers
- Production extracts
- Uploaded documents
- Large logs
- Hidden reasoning
- Unverified regulatory claims presented as current fact

Durable decisions belong in decision records. Release history belongs in a changelog. Archive old handoff detail when the active memory becomes difficult to scan.

---

## 52. Open Decision Gates

### Gate A: Before Scaffolding

- Approve repository topology
- Verify Hostinger capability matrix
- Approve supported framework versions
- Define GBR and pilot ownership
- Approve primary platform typeface
- Approve target test runner and static-analysis tools

### Gate B: Before Rule Coding

- Name rule author and independent verifier
- Approve official-source register
- Approve weekend and holiday policy
- Approve rule supersession behavior
- Approve golden VAT and Corporate Tax cases

### Gate C: Before Approved Internal Data

- Approve data classification
- Approve hosting and data location
- Approve retention and deletion
- Enable MFA for privileged users
- Pass tenant-isolation suite
- Pass backup restore

### Gate D: Before Paid Readiness Work

- Identify legal seller
- Confirm appropriate licence
- Approve service agreement
- Approve data-processing terms
- Approve secure transfer and deletion runbook

### Gate E: Before External SaaS

- Complete independent security review
- Complete privacy and cross-border assessment
- Test incident response
- Test recovery objectives
- Approve support process
- Approve billing and entitlement behavior

---

## 53. First Implementation Sequence

After Stage 1 decisions are closed, begin with these packets:

1. Scaffold the independent Laravel application and verification pipeline.
2. Add firms, users and memberships using synthetic seeds.
3. Add tenant resolution and one protected sample resource.
4. Prove cross-tenant isolation across web, Livewire and jobs.
5. Add append-only audit infrastructure.
6. Add the client master and one complete manual-obligation workflow.
7. Verify the walking skeleton before any bulk importer or automated deadline generator.

The first build session must not start by creating every database table or every dashboard. It should prove the security and domain pattern through one narrow vertical slice, then repeat the proven pattern.

---

## 54. Regulatory Source Verification Record

Last reviewed for this plan: 26 July 2026.

Verified planning facts:

- The official Ministry of Finance e-invoicing portal describes an eInvoice as structured invoice data exchanged electronically and states that PDFs, Word documents, images, scans and emails are not eInvoices.
- The Ministry of Finance announced that the pilot commenced on 1 July 2026.
- The Ministry of Finance announced an extension of the ASP appointment deadline to 30 October 2026 for persons subject to the e-invoicing system whose annual revenue exceeds AED 50 million, while keeping mandatory implementation by 1 January 2027.
- The published timeline for businesses below AED 50 million remains appointment by 31 March 2027 and implementation by 1 July 2027, subject to later official amendment.
- FTA guidance states that VAT filing and payment generally occur by the 28th day following the tax period, subject to the official due date and non-working-day rules.
- FTA guidance states that Corporate Tax returns and payment are generally due within nine months from the end of the tax period.

These facts are planning inputs, not hard-coded product behavior. Re-verify official sources before publishing a rule version or customer-facing claim.

---

## 55. Planning Baseline Approval

This Version 2.0 baseline is ready for discovery and architecture closure.

It does not authorise:

- Use of real customer data
- External sale
- Public compliance claims
- Sensitive document storage
- Production deployment
- EmaraTax automation
- E-invoice transmission

Each activity becomes authorised only after its stated gate is approved.

---

## 56. Compliance-first simplification revision

Product owner direction confirmed 29 July 2026:

- Release 1 is the internal Tax Compliance MVP. E-invoicing readiness remains a separate future release and stays visible only as Coming soon.
- Client master data is the primary source for VAT periods, Corporate Tax periods, trade licence dates, passport dates, Emirates ID dates and recurring compliance work.
- The import experience supports CSV and `.xlsx` first-worksheet files, with explicit validation, preview, accepted and rejected rows, and a confirmation step before commit.
- VAT imports store the actual period start and end dates plus monthly or quarterly frequency. Future periods are generated from those dates rather than calendar-quarter assumptions.
- VAT filing dates use 28 days after the Tax Period end, moving to the next weekday for weekend dates. Official FTA extensions remain administrator overrides with retained original dates.
- Corporate Tax imports store the confirmed Tax Period start and end dates. Filing dates use nine months after the Tax Period end. First-period imports outside the normal 6 to 18 month range are blocked for administrator review.
- Sensitive passport, Emirates ID and trade licence numbers are encrypted at rest. Full assigned-staff exports require recent password confirmation and create audit evidence.
- The schedule is calculated ahead for planning but the dashboard only promotes VAT work when the Tax Period is ending or the filing period is active. Documents appear when their configured reminder window begins.
- Deadline source and generation controls are administrator-only tools. Ordinary accountants work from Tax Returns and Deadlines, Work Tracker, Clients, Documents and Calendar.
- Advanced deadline tools are off by default for each firm. A firm administrator can enable them from Feature administration, with the decision recorded in Activity History.
- The application keeps the TBT dark and gold identity and adds Light, Dark and System appearance choices with accessible contrast and reduced-motion support.

This revision supersedes the earlier assumption that all VAT and Corporate Tax dates must be entered manually or maintained through visible rule-governance screens. The source-linked rule and override history remain in the data model for exceptional FTA dates and future review.
