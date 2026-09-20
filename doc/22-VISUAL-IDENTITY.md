# VeloraPlus — Visual Identity System

## Status

LOCKED — FRONTEND IMPLEMENTATION REFERENCE

This document is the visual source of truth for the VeloraPlus Dashboard and platform UI.

It defines the product-level visual language before broad Dashboard frontend implementation begins.

Tenant-facing public websites may override brand colors through the existing tenant branding contract, but the VeloraPlus Dashboard follows this document.

---

## 1. Brand personality

VeloraPlus is a global SaaS platform for companies that need to operate modular business software from one workspace.

The visual language should communicate:

- clarity;
- reliability;
- modern SaaS quality;
- operational confidence;
- simplicity at scale;
- professional business software without unnecessary visual noise.

The interface should feel structured and premium through spacing, typography, hierarchy, and consistency rather than decorative effects.

---

## 2. Brand color system

### 2.1 Core palette

| Token | Hex | Role |
|---|---|---|
| Primary | #1D4ED8 | Main brand color, primary actions, active links, selected states |
| Primary Dark | #1E40AF | Hover and darkened primary states |
| Secondary | #0F172A | Headings, strong text, navigation emphasis, brand anchor |
| Accent | #F59E0B | Highlights, attention markers, badges, selected accents |
| Background | #FFFFFF | Main application surface and clean page backgrounds |
| Surface | #F8FAFC | Dashboard page background and secondary surfaces |
| Border | #E2E8F0 | Cards, inputs, dividers, table boundaries |
| Text | #0F172A | Primary body text |
| Muted Text | #64748B | Secondary text, metadata, helper text |
| Disabled | #94A3B8 | Disabled controls and inactive presentation |

### 2.2 Semantic states

Semantic colors are functional UI colors, not additional brand colors.

| State | Use |
|---|---|
| Success | Successful save, active/healthy state, completed operation |
| Warning | Attention required, pending action, expiring state |
| Danger | Validation failure, destructive action, failed operation |
| Info | Informational notices and neutral system guidance |

Semantic states must remain visually distinct from Primary, Secondary, and Accent brand tokens.

### 2.3 Contrast rules

- Primary text uses a dark text token on light surfaces.
- #F59E0B is an accent/highlight color and must not be used as normal body text on white backgrounds.
- Primary action text on #1D4ED8 remains white.
- Muted text is not used for critical information.
- Focus indicators remain clearly visible against light and tinted surfaces.
- Color is never the only signal for success, warning, danger, or status; pair it with text, icons, or labels.

---

## 3. Design token strategy

The frontend should consume semantic CSS/Tailwind tokens instead of scattering raw hexadecimal values throughout Blade templates.

Preferred semantic names:

    --color-primary
    --color-primary-dark
    --color-secondary
    --color-accent
    --color-background
    --color-surface
    --color-border
    --color-text
    --color-muted

Tenant branding uses the existing dynamic tokens:

    --brand-primary
    --brand-secondary
    --brand-accent
    --brand-background
    --brand-text

Tenant values are company-owned presentation settings and do not replace the VeloraPlus Dashboard brand system.

---

## 4. Typography

### Primary typeface

Instrument Sans.

Current repository configuration loads:

- 400 — Regular;
- 500 — Medium;
- 600 — Semibold.

The existing Laravel/Vite font configuration is the implementation baseline.

### Type hierarchy

| Level | Weight | Purpose |
|---|---:|---|
| Display | 600 | Major product or marketing headings |
| H1 | 600 | Page title |
| H2 | 600 | Section title |
| H3 | 600 | Card or subsection title |
| Body | 400 | Standard content |
| Body Strong | 500 | Emphasis and labels |
| Metadata | 400 | Secondary information |
| Button / Control | 500 | Interactive controls |

Avoid excessive font weights. The interface primarily uses 400, 500, and 600.

---

## 5. Layout and spacing

VeloraPlus uses a spacious, grid-based SaaS layout.

Preferred spacing scale:

    4px   — micro spacing
    8px   — tight spacing
    12px  — compact control spacing
    16px  — standard component spacing
    20px  — card/internal spacing
    24px  — section spacing
    32px  — major section spacing
    40px  — page rhythm
    48px+ — major page separation

General rules:

- inputs and controls usually use 12–16px internal spacing;
- cards usually use 20–24px padding;
- sections normally use 24–32px vertical separation;
- mobile spacing may reduce proportionally without removing hierarchy.

Avoid cramped dashboards. White space is part of the product identity.

---

## 6. Radius and shape

The interface uses restrained rounded corners.

| Token | Approx. radius | Use |
|---|---:|---|
| Small | 6px | Small controls and compact elements |
| Medium | 8px | Inputs, buttons, standard cards |
| Large | 12px | Main cards and panels |
| Pill | 9999px | Status badges, filters, compact tags |

Do not make every element pill-shaped.

---

## 7. Borders and elevation

VeloraPlus should look structured through borders and spacing first.

Default border:

    #E2E8F0

Use subtle borders for:

- cards;
- tables;
- form controls;
- separators;
- sidebar boundaries.

Use shadows sparingly:

- no shadow for ordinary flat sections;
- subtle shadow for floating menus and elevated panels;
- stronger elevation only for overlays, dialogs, dropdowns, or temporary floating elements.

---

## 8. Buttons

### Primary button

Use for the main action of a page or form.

- Primary blue background;
- white label;
- medium radius;
- medium label weight;
- hover uses Primary Dark;
- visible keyboard focus state.

### Secondary button

Use for supportive actions.

- white or surface background;
- neutral border;
- secondary/dark text;
- subtle hover surface.

### Destructive button

Use only for destructive operations.

It must be visually distinct and normally require confirmation when the operation is materially destructive.

### Button hierarchy

A screen should have one visually dominant primary action wherever practical.

---

## 9. Forms

Forms should prioritize fast business data entry.

Rules:

- every input has a visible label;
- helper text stays close to the affected control;
- validation messages appear adjacent to the invalid field;
- required fields are identifiable;
- focus state is visible;
- errors do not rely only on border color;
- password/secret inputs never echo persisted secrets;
- destructive settings require deliberate confirmation.

Input states:

- default;
- hover;
- focus;
- filled;
- validation error;
- disabled;
- readonly where needed.

---

## 10. Cards and panels

Cards group related business information.

Preferred structure:

    Card
    ├── optional eyebrow / metadata
    ├── title
    ├── description
    ├── primary content
    └── optional actions / footer

Avoid excessive nesting of cards.

Cards on the same page should share border, radius, padding, and title hierarchy.

---

## 11. Tables and operational lists

Dashboard tables are optimized for business scanning.

Rules:

- strong column hierarchy;
- compact but readable row height;
- consistent numeric alignment;
- clear status labels;
- row actions grouped consistently;
- responsive horizontal scrolling when a table cannot safely collapse;
- pagination for large datasets.

Important values such as status, amount, appointment time, or account state should remain scannable without relying on color alone.

---

## 12. Navigation

The Dashboard navigation is a structural part of the brand.

It should communicate:

- current company context;
- current module;
- current page;
- permissions and capability availability;
- separation between Company, Booking, Billing, Platform, and Settings areas.

Active navigation uses a restrained surface/primary treatment rather than an oversized indicator.

Navigation labels stay short and predictable.

---

## 13. Icons

Use one consistent outline icon system.

Rules:

- icons support meaning and do not replace critical labels;
- keep icon stroke and weight consistent;
- align icons with text baselines;
- avoid decorative icon overload;
- destructive and security-sensitive actions should pair icons with explicit text when space allows.

---

## 14. Status and feedback

Important backend state changes should provide clear feedback.

Preferred hierarchy:

1. inline field validation;
2. local success or error message;
3. page-level flash message where appropriate;
4. confirmation dialog for materially destructive actions.

Example status vocabulary:

- Active;
- Pending;
- Inactive;
- Scheduled;
- Failed;
- Completed;
- Refunded;
- Awaiting payment.

Status labels remain understandable without color.

---

## 15. Motion and interaction

Motion is subtle and functional.

Use animation for:

- dropdowns;
- dialogs;
- collapsible panels;
- loading transitions;
- small state changes.

Avoid:

- excessive page transitions;
- bouncing UI;
- decorative continuous motion;
- animation that delays normal business workflows.

Respect reduced-motion preferences.

---

## 16. Responsive behavior

The Dashboard must support:

- desktop;
- tablet;
- mobile.

The responsive strategy is content-first:

- desktop sidebar may collapse or transform on smaller screens;
- tables may scroll horizontally when necessary;
- cards may stack vertically;
- forms may move from multi-column to single-column;
- primary actions remain reachable without excessive scrolling.

Do not solve responsiveness by simply shrinking desktop components.

---

## 17. RTL / LTR

VeloraPlus supports both LTR and RTL presentation.

Rules:

- layout direction comes from the active locale;
- prefer logical CSS properties where practical;
- avoid hard-coded left/right assumptions when an equivalent logical property exists;
- directional icons are reviewed for RTL;
- numbers and financial values remain readable in both directions;
- navigation and form alignment mirror correctly without breaking hierarchy.

Arabic UI is not a separate visual theme. It uses the same design system with direction-aware layout.

---

## 18. Accessibility baseline

Required:

- semantic HTML where appropriate;
- keyboard navigation;
- visible focus states;
- labels for controls;
- descriptive button text;
- meaningful alt text for non-decorative images;
- no color-only status communication;
- sufficient contrast;
- errors associated with their inputs;
- support for reduced motion.

Accessibility is part of the component contract, not later polish.

---

## 19. Logo and brand assets

The logo system must eventually define:

- primary logo;
- horizontal lockup;
- compact/mark variant;
- light/dark usage;
- minimum clear space;
- minimum display size;
- favicon;
- approved formats.

Until final logo artwork is committed to the repository, the UI must not invent alternative logo shapes or wordmarks.

Tenant logo and favicon uploads are company assets and remain separate from the VeloraPlus platform logo.

---

## 20. Tenant branding relationship

VeloraPlus is a white-label capable SaaS platform.

There are two visual layers.

### Layer A — VeloraPlus platform identity

Used for:

- authentication shell;
- platform-level navigation and administration;
- system messaging;
- default public/platform interfaces;
- VeloraPlus operational interfaces.

Uses this locked visual system.

### Layer B — Tenant identity

Used for:

- public tenant home;
- tenant public booking experience;
- future tenant-facing transactional presentation;
- company logo and favicon;
- tenant colors;
- tenant social presentation;
- subscriber/company Dashboard shell branding.

Tenant branding is configurable through the existing branding.* company settings contract.

For a subscribed tenant's operational Dashboard, the tenant identity is the visible product identity by default. VeloraPlus must not appear in the sidebar, page header, browser title, or workspace controls. The VeloraPlus brand is reserved for a small "Powered by VeloraPlus" attribution in the Dashboard footer.

Tenant branding must not leak into platform controls in a way that hides the VeloraPlus system boundary.

---

## 21. Component design rule

Every reusable Dashboard component should define:

- structure;
- typography;
- spacing;
- color tokens;
- states;
- responsive behavior;
- accessibility behavior.

Preferred implementation order:

    Visual Identity
        ↓
    Design Tokens
        ↓
    Reusable Blade Components
        ↓
    Page Layouts
        ↓
    Dashboard Screens
        ↓
    Interaction Enhancements

Do not style each page independently.

---

## 22. Frontend implementation boundary

The visual identity controls presentation only.

It must not contain or duplicate:

- authorization logic;
- entitlement logic;
- billing rules;
- payment state transitions;
- booking state transitions;
- tenant selection logic;
- server-side validation.

Blade, Tailwind, Alpine, and Vanilla JavaScript consume backend contracts; they do not become a second business-logic layer.

---

## 23. Current repository alignment

The repository already establishes:

- Tailwind CSS 4;
- Instrument Sans;
- Vite;
- Blade;
- Alpine/Vanilla JavaScript approach;
- dynamic tenant branding tokens in the public Tenant surface.

The next broad Dashboard frontend implementation should migrate these decisions into reusable semantic design tokens and components instead of isolated page-specific styling.

---

## 24. Visual QA checklist

Before a Dashboard screen is visually complete, verify:

- consistent typography;
- correct spacing;
- correct brand and semantic color usage;
- visible focus state;
- keyboard usability;
- readable validation and error states;
- responsive layout;
- RTL/LTR behavior;
- no raw magic colors where a semantic token exists;
- no secret values rendered;
- no duplicate component styling;
- no business logic introduced into presentation.

---

## 25. Non-negotiable visual rules

1. No random colors. Use approved brand or semantic tokens.
2. No random fonts. Instrument Sans is the product UI font.
3. No page-by-page visual reinvention. Components must be shared.
4. No color-only status. Pair status color with text or icon.
5. No decorative complexity that hurts operational speed.
6. No tenant branding mixed into VeloraPlus platform controls without a documented reason.
7. No frontend business logic hidden inside styling or interaction code.
8. No broad Dashboard frontend build before this identity is treated as the visual baseline.

---

## 26. Implementation gate

This document is the visual baseline for the next Dashboard frontend phase.

Frontend work should proceed in this order:

    Backend contract verified
        ↓
    Visual Identity locked
        ↓
    Design tokens
        ↓
    Shared components
        ↓
    Dashboard shell refinement
        ↓
    Company screens
        ↓
    Booking screens
        ↓
    Billing / Marketplace
        ↓
    Settings / Usage
        ↓
    Responsive + RTL/LTR QA
        ↓
    Browser QA


---

## 27. Dashboard UI direction reference

The locked Visual Identity is complemented by `doc/23-DASHBOARD-UI-DIRECTION.md`.

The Dashboard uses external products as **pattern references only**:

- Linear — navigation, hierarchy, and calm workspace presentation;
- Stripe Dashboard — operational tables, search, filtering, and data scanning;
- Vercel — sidebar-first dashboard structure, context, and responsive behavior.

VeloraPlus does not copy their branding, screens, logos, or frontend code. These references only inform information architecture and interaction patterns.

The resulting UI must remain recognizably VeloraPlus and must continue using the palette, typography, semantic tokens, accessibility rules, and tenant-branding boundaries already defined in this document.
