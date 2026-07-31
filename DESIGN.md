# TBT Compliance Platform Design System

## Direction

The platform is the TBT Control Ledger, a multi-client operations command centre inspired by the sectional clarity of the FTA portal. It uses a persistent navigation rail, strong section bars, grouped registers, card stacks and collapsible operational panels. The visual system keeps TBT ink, charcoal, gold and silver, while adding enough surface contrast for long working sessions.

The FTA portal is a structural reference only. TBT does not reproduce its branding or single-company navigation. Every major surface is redesigned for cross-client scanning, filtering and exception handling.

## Operating Scene

Accounting and tax professionals use the platform for sustained desktop work in normal office lighting. The interface supports Dark, Light and System appearance modes. Content surfaces use stepped tonal layers, clear borders and restrained depth so users can distinguish navigation, filters, summaries, registers and action queues instantly. Mobile layouts support review and essential actions without pretending dense operational work is phone-first.

## Typography

- Instrument Sans is the bundled interface and reading family.
- Monospace is limited to identifiers, dates and numeric records that benefit from fixed-width scanning.
- Headings use weight and scale for hierarchy, never gradient text or excessive tracking.
- Operational labels use sentence case.

## Colour and Surfaces

- Dark canvas: #090A0C.
- Dark navigation: #111318.
- Dark panel: #15181E with a #252A33 border.
- Light canvas: #F3F1EB.
- Light panel: #FFFFFF with a #DED9CC border.
- Primary reading: cool white in Dark and deep ink in Light.
- Secondary reading: #AEB5C2 in Dark and #5F6672 in Light.
- Gold #D4A64A marks current firm, focus, primary actions and selected navigation.
- Navy #18324C supports information and VAT state. Violet supports Corporate Tax. Gold supports documents.
- Green, amber and red are reserved for real lifecycle state.
- Major panels use one border and a soft offset shadow. Nested rows use separators without additional shadows.
- Glass is functional and selective. The app shell, sidebar, page header, filter controls and onboarding strips may use translucent ink or paper with 16 to 20px blur. Dense registers and forms remain more opaque for reading.
- Glass surfaces require a solid fallback colour, a visible edge and sufficient contrast in both appearance modes.

## Layout

- A persistent sidebar owns product navigation and active-firm context. It collapses to an icon rail on desktop and becomes a drawer on smaller screens.
- A compact command header identifies the firm, page and primary action.
- The content well uses a maximum width suited to dense tables and forms.
- Filters live in a dedicated control panel and may collapse on smaller screens.
- Portfolio summaries use varied-width operational cards, not a uniform marketing grid.
- Registers use section headers, sticky table headings where practical and labelled mobile rows.
- Empty states remain inside the surface they describe and explain the next safe action.

## Components

- Firm switcher: compact context control showing firm name and current role.
- Status badge: sentence-case lifecycle value with restrained semantic colour.
- Primary action: gold fill with dark text and visible focus treatment.
- Operational panel: 14px radius, clear header band, border and subtle shadow.
- Metric card: icon, precise count, short label and category colour. Counts remain visible as text.
- Section bar: title, supporting description, count or action and an optional disclosure control.
- Accordion: native details and summary for secondary queues, retaining full keyboard support.
- Data register: high-contrast heading row, hover trace and explicit empty state.
- Destructive actions: explicit text labels and confirmation where required.
- Forms: labels remain visible; validation names the problem and recovery.

## Motion

Use a restrained 180ms transition for hover, focus and disclosure states. The first dashboard load may use one subtle panel reveal. All data and controls remain visible without animation. Respect reduced-motion preferences.

## Accessibility

- Target WCAG AA contrast.
- Preserve visible keyboard focus for every action.
- Use semantic tables on wide screens and labelled stacked rows on small screens.
- Keep touch targets at least 44 pixels where controls are isolated.
- Never use colour as the only indication of role or status.

## Content Voice

Use concise operational language. Name the firm, action, state and recovery path. Avoid claims of regulatory approval, guaranteed compliance or automated filing.
