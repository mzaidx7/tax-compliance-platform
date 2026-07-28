# TBT Compliance Platform Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users

- UAE accounting, bookkeeping and tax professionals operating through a firm workspace.
- Firm administrators who invite members, assign access and review security activity.
- Preparers, reviewers, managers, data-cleanup operators and read-only users completing compliance operations.

## Product Purpose

The platform coordinates compliance operations and e-invoicing readiness work under a TBT subdomain. Success means each authenticated user can enter the correct firm context, see only that firm's records and complete assigned operational work with clear accountability.

## Positioning

The platform combines firm-scoped operational workflows, versioned compliance rules and append-only audit history without claiming authority from the FTA, Ministry of Finance, EmaraTax or an Accredited Service Provider.

## Operating Context

Users work in authenticated firm workspaces, often switching between firms, inviting colleagues, reviewing records and preparing work for independent professional review. Desktop is the primary dense-work environment, with responsive access required for smaller screens.

## Capabilities and Constraints

- Laravel and Livewire modular monolith with MySQL as the deployment target.
- Shared-database SaaS tenancy with mandatory firm scoping.
- Invitation-only authentication with verified email and optional two-factor authentication.
- Baseline roles are firm administrator, manager, preparer, reviewer, data-cleanup operator and read-only.
- Significant actions create append-only audit records.
- Real customer data, credentials and private documents must not be used in development.
- Configurable custom roles, the final permission matrix and the licensed legal seller remain open decisions.

## Brand Commitments

- Product name: TBT Compliance Platform.
- Inherit the Think Beyond Tax identity: dark ink and charcoal, restrained gold guidance, silver typography and precise software details.
- Use concise, practical language without regulatory affiliation, approval or outcome claims.
- Never use em dashes in product copy.

## Evidence on Hand

- Canonical product and implementation plan: `Think_Beyond_Tax_Compliance_Platform_Master_Plan.md`.
- Current implementation handoff: `MEMORY.md`.
- Parent brand guidance: `../DESIGN.md`.
- Existing Laravel, Livewire and Flux application shell.
- No customer testimonials, commercial claims, production metrics or approved customer data are available and none may be fabricated.

## Product Principles

- Resolve tenant context before showing operational data.
- Make state, responsibility and recovery actions explicit.
- Prefer traceable review-ready work over opaque automation.
- Keep regulated statements sourced, versioned and human-verified.
- Make the secure path the easiest path.

## Accessibility & Inclusion

Maintain visible keyboard focus, semantic labels, adequate contrast, touch-friendly controls and responsive layouts at 375, 768, 1024 and 1440 pixel widths.
