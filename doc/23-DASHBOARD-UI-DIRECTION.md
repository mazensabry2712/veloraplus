# VeloraPlus — Dashboard UI Direction & Reference System

## Status

**LOCKED — DASHBOARD UI DIRECTION**

This document supplements \`doc/22-VISUAL-IDENTITY.md\`.

\`22-VISUAL-IDENTITY.md\` remains the source of truth for colors, typography, spacing, radius, accessibility, RTL/LTR, tenant-branding boundaries, and component rules.

This document records how those rules should be expressed in the Dashboard and which external products are used as pattern references.

---

## 1. Target experience

VeloraPlus should feel like mature business SaaS:

- fast to scan;
- operational rather than decorative;
- visually branded without noise;
- consistent across Company, Booking, Billing, Marketplace, Usage, and Settings;
- comfortable for repeated daily use;
- scalable as modules and tenant data grow.

The goal is **not** to clone another product. The goal is to combine useful patterns into a distinct VeloraPlus interface.

---

## 2. External reference system

### Linear — navigation and workspace

Use Linear as a reference for:

- compact, predictable sidebar navigation;
- strong information hierarchy;
- consistent headers and controls;
- calm visual presentation;
- keeping the main work area visually dominant.

Linear's March 2026 UI refresh emphasized consistent headers/navigation/view controls and reduced sidebar visual weight.

Reference:
https://linear.app/changelog/2026-03-12-ui-refresh

### Stripe Dashboard — operational data

Use Stripe as a reference for:

- business tables and lists;
- search and filtering;
- grouped business objects;
- readable financial/operational values;
- clear status presentation;
- data-heavy workflows.

Stripe's current Dashboard documentation describes grouped search results, column-based result views, and filters/operators for more granular navigation.

Reference:
https://docs.stripe.com/dashboard/search

### Vercel — dashboard structure

Use Vercel as a reference for:

- sidebar-first information architecture;
- workspace context;
- prioritizing frequent workflows;
- responsive navigation;
- mobile dashboard behavior.

Vercel's February 2026 dashboard redesign introduced a resizable/hideable sidebar, unified navigation, improved ordering, and mobile-oriented navigation.

Reference:
https://vercel.com/changelog/dashboard-navigation-redesign-rollout

These products are references only. No dependency, copied branding, copied screen, or external design system is introduced.

---

## 3. Reference mapping

| Product | Reference area | VeloraPlus interpretation |
|---|---|---|
| Linear | Navigation | Sidebar + strong active state + clean sections |
| Linear | Hierarchy | Calm page header + content-first layout |
| Stripe | Data | Search/filter/table/list patterns |
| Stripe | Status | Clear text + badge + supporting color |
| Vercel | Context | Tenant/workspace context stays visible |
| Vercel | Responsive | Sidebar/mobile navigation adapts to viewport |

---

## 4. VeloraPlus brand rules

Locked palette:

- Primary: \`#1D4ED8\`
- Primary Dark: \`#1E40AF\`
- Secondary: \`#0F172A\`
- Accent: \`#F59E0B\`
- Background: \`#FFFFFF\`
- Surface: \`#F8FAFC\`
- Border: \`#E2E8F0\`
- Text: \`#0F172A\`
- Muted: \`#64748B\`
- Disabled: \`#94A3B8\`

Typography:

- Instrument Sans;
- 400 / 500 / 600.

No page-specific brand colors and no random fonts.

The white/black problem is solved through **hierarchy and controlled brand color**, not by adding random colors.

---

## 5. Dashboard shell

### Sidebar

The sidebar communicates:

1. tenant identity;
2. current company context;
3. user role;
4. product sections;
5. active page.

Rules:

- active item uses strong Primary Blue;
- inactive links remain quiet;
- section headings are compact metadata;
- disabled capabilities clearly show \`Soon\`;
- navigation must not overpower main content;
- mobile keeps the same information architecture.

### Header

The header provides:

- tenant/workspace context;
- current user;
- account action;
- responsive navigation access.

VeloraPlus provider branding is intentionally omitted from the tenant Dashboard shell.

Do not add global controls until the matching capability exists.

### Main content

Preferred hierarchy:

    Page Header
        ↓
    Context / Filters
        ↓
    Primary Business Content
        ↓
    Secondary Content / Actions

---

## 6. Page header

Standard page header contains:

- title;
- optional description;
- primary action;
- optional context metadata.

Use a subtle Primary Blue visual anchor or light Primary tint.

The header is a hierarchy element, not a large marketing hero.

---

## 7. Cards and panels

Cards group related business information.

Preferred structure:

    Metadata
    Title
    Description
    Main data
    Actions

Rules:

- consistent border/radius/padding;
- subtle shadow only when useful;
- light Surface/Primary tint may distinguish a header;
- avoid unnecessary nested cards;
- do not create giant decorative KPI cards without meaningful backend metrics.

---

## 8. Tables and operational screens

Operational screens follow:

    Search / Filters
        ↓
    Result Context
        ↓
    Table / List
        ↓
    Row Actions
        ↓
    Pagination

Rules:

- headers may use a subtle Primary tint;
- row hover may use a subtle Primary tint;
- numeric values stay aligned;
- status is readable without color;
- row actions use a consistent pattern;
- dense tables may scroll horizontally on mobile.

Target principle:

**Scan → Inspect → Act**

---

## 9. Filters

Core filters may include:

- date;
- status;
- location;
- service/module;
- search.

Rules:

- labels remain explicit;
- selected state is obvious;
- filters support fast operational scanning;
- do not let filter controls overpower the primary action;
- list filters should use query parameters where practical.

---

## 10. Action hierarchy

### Primary
Main workflow action.

Examples:
- Create Service;
- Create Appointment;
- Open Queue;
- Save Settings.

### Secondary
Support action or navigation.

### Ghost
Low-emphasis action where context already explains it.

### Danger
Destructive/materially risky action.

A page should normally have one dominant primary action.

---

## 11. Status system

Every important status must work without color.

Use:

- readable status text;
- badge;
- icon where useful;
- color as a supporting signal.

Typical states:

- Active;
- Pending;
- Inactive;
- Scheduled;
- Failed;
- Completed;
- Refunded;
- Awaiting payment.

---

## 12. Information density

VeloraPlus is operational software.

Target:

**moderate-to-high information density with readable spacing.**

Do:

- compact metadata;
- useful table rows;
- visible filters;
- predictable actions.

Do not:

- make every section oversized;
- hide useful data behind unnecessary clicks;
- spend excessive space on decorative elements;
- turn the dashboard into a wall of unrelated cards.

White space remains part of the brand, but it must support hierarchy.

---

## 13. Dashboard Overview

The future Dashboard home should prioritize real operational context:

    Company context
        ↓
    Key operational summary
        ↓
    Booking activity
        ↓
    Upcoming appointments / queue signals
        ↓
    Recent activity
        ↓
    Module / entitlement signals

Only show metrics supported by real backend data. Never invent numbers for visual completeness.

---

## 14. Booking workspace

Booking should feel like one coherent operational area.

### Services
Prioritize:
- service name;
- duration;
- price;
- status;
- online-booking state;
- management actions.

### Availability
Prioritize:
- Staff;
- location;
- assigned services;
- recurring hours;
- breaks;
- time off.

### Appointments
Prioritize:
- Customer;
- Service;
- Staff;
- date/time;
- location;
- status;
- payment state;
- lifecycle action.

### Queue
Prioritize:
- business date;
- location;
- service;
- queue state;
- waiting/serving counts;
- current serving Customer;
- fast entry actions.

Queue is optimized for speed of operation, not decoration.

---

## 15. Company workspace

Company screens should share the same interaction pattern:

    Page Header
        ↓
    Filters / Search
        ↓
    Primary Action
        ↓
    Table / List
        ↓
    Pagination / Actions

This applies to:

- Profile;
- Locations;
- Staff;
- Customers;
- Users;
- Roles & Permissions.

Do not invent a new visual language for each CRUD screen.

---

## 16. Billing and payments

Financial screens need higher information clarity.

Prefer:

- readable amounts;
- currency context;
- dates;
- payment/invoice status;
- provider state;
- explicit actions;
- aligned values.

Do not depend on color alone for financial state.

---

## 17. Responsive rules

Desktop:

- persistent sidebar;
- wide operational surfaces;
- multi-column controls where useful.

Tablet:

- reduced content width;
- stacked action groups when needed;
- scrollable tables when necessary.

Mobile:

- responsive navigation;
- single-column cards;
- stacked filters;
- horizontally scrollable dense tables;
- reachable primary actions.

Do not solve responsiveness by merely shrinking desktop components.

---

## 18. RTL / LTR

The same design system serves Arabic and English.

Required:

- logical spacing and positioning;
- mirrored hierarchy;
- correct directional icons;
- readable dates and numbers;
- no accidental left/right assumptions.

Arabic is not a separate visual theme.

---

## 19. Accessibility

Every reusable UI component must preserve:

- semantic HTML;
- keyboard access;
- visible focus;
- meaningful labels;
- sufficient contrast;
- clear validation;
- status communication beyond color.

Accessibility is part of the component contract.

---

## 20. Component-first rule

Broad visual work must be implemented through shared components first.

Core shared Dashboard components:

- layout;
- navigation;
- nav item;
- page header;
- card;
- button;
- badge;
- pagination;
- brand.

Before adding page-specific styling, ask whether the pattern belongs in a shared component.

---

## 21. Performance rules

The UI must remain fast as tenants, records, and modules grow.

Prefer:

- server-rendered Blade;
- pagination;
- bounded relationship loading;
- query-string filters;
- reusable components;
- minimal JavaScript;
- progressive enhancement;
- no unnecessary dependencies.

Avoid:

- loading entire datasets in the browser;
- unnecessary Dashboard-wide JavaScript;
- repeated expensive component queries;
- heavy UI frameworks for visual decoration.

---

## 22. Backend boundary

Frontend presentation must not become a second business layer.

Blade/Tailwind/Alpine/Vanilla JavaScript must not own:

- tenant selection;
- authorization;
- RBAC;
- entitlements;
- billing rules;
- payment transitions;
- appointment transitions;
- queue rules;
- server validation.

Policies and application services remain authoritative.

---

## 23. Visual QA gate

Before a screen is considered visually complete:

### Structure
- hierarchy is obvious;
- primary action is clear;
- content is scannable;
- spacing is consistent.

### Brand
- approved palette only;
- semantic tokens used;
- no random colors/fonts.

### Components
- shared components reused;
- states are consistent;
- cards/tables follow the system.

### Responsive
- desktop reviewed;
- tablet reviewed;
- mobile reviewed;
- no broken overflow.

### RTL/LTR
- Arabic reviewed;
- English reviewed;
- direction reviewed.

### Accessibility
- keyboard usable;
- focus visible;
- status not color-only;
- labels/errors clear.

---

## 24. Decision log

### D001 — Keep the locked VeloraPlus palette
**Status:** Locked

The current visual identity remains unchanged.

### D002 — Fix the white/black feel through hierarchy
**Status:** Locked

Use stronger Primary Blue active states, light Primary surfaces, clearer table hierarchy, controlled Accent use, and semantic states.

### D003 — Linear + Stripe + Vercel as references
**Status:** Locked

Each reference has a defined responsibility. None is the visual identity of VeloraPlus.

### D004 — Shared components before page-specific styling
**Status:** Locked

Visual improvements should propagate through shared components.

### D005 — Keep the existing frontend stack
**Status:** Locked

Blade + Tailwind CSS + Alpine.js + Vanilla JavaScript + Vite.

### D006 — Operational usability over decoration
**Status:** Locked

Speed, clarity, scanability, and consistency take priority over effects.

---

## 25. Next UI delivery order

    Dashboard shell refinement
        ↓
    Company workspace consistency
        ↓
    Booking workspace consistency
        ↓
    Dashboard Overview
        ↓
    Billing / Payments
        ↓
    Marketplace / Usage / Settings
        ↓
    Responsive QA
        ↓
    RTL/LTR QA
        ↓
    Browser QA
        ↓
    Accessibility pass

Each increment follows:

    Backend Contract
        ↓
    Shared Component
        ↓
    Screen
        ↓
    Feature Test
        ↓
    Full Regression
        ↓
    Documentation Update

---

## 26. Sources

Internal:
- \`doc/22-VISUAL-IDENTITY.md\`
- \`doc/20-PHASE-8-COMPANY-DASHBOARD.md\`
- \`doc/09-SCALABILITY-AND-PERFORMANCE.md\`
- \`doc/08-STACK-AND-LIBRARIES.md\`

External:
- Linear UI refresh: https://linear.app/changelog/2026-03-12-ui-refresh
- Stripe Dashboard search: https://docs.stripe.com/dashboard/search
- Vercel Dashboard redesign: https://vercel.com/changelog/dashboard-navigation-redesign-rollout

---



### Recent UI refinement

The Dashboard shell now includes a lightweight shared outline-icon set for navigation items.

Rules:
- icons are implemented as a local Blade component;
- no icon package/dependency is introduced;
- icons inherit the current text color so active/inactive/disabled states remain coherent;
- icons support navigation meaning but never replace labels;
- the same icon treatment is used across desktop and mobile navigation.


## 28. Modern interaction layer

The Dashboard foundation now includes a lightweight interaction layer without introducing a frontend framework.

### Theme
- Light and Dark modes are supported.
- The preference is stored locally under a VeloraPlus-specific storage key.
- First visit follows the operating-system color preference.
- The Tailwind `dark` variant is driven by an explicit `.dark` class on the document root.
- Semantic Dashboard colors remain token-driven so dark mode does not require page-by-page palette reinvention.

### Language
- Dashboard language can be switched between English and Arabic.
- The selected Dashboard locale is stored in the session.
- Arabic switches the document direction to RTL through the existing layout contract.
- Navigation and Dashboard Overview strings are translated through Laravel language files.
- Page headers across Company, Booking, Settings, and Usage use the same translation contract.
- Tenant-level locale remains a company configuration; the Dashboard switch is a per-session presentation preference.

### Command palette
- `Ctrl/⌘ + K` opens the workspace command palette.
- Keyboard navigation with arrow keys and Enter is supported.
- Search is client-side over a bounded list of known workspace destinations.
- Items are filtered by the same permission and entitlement checks used by navigation.
- No external command-palette dependency is introduced.

### Interaction rule
Modern interactions must improve navigation speed and visual confidence without turning the Blade Dashboard into a client-heavy application.

## 29. White-label tenant shell

The subscribed tenant experience is white-label by default:

- tenant name and uploaded tenant logo are used in the Dashboard shell;
- VeloraPlus must not appear in sidebar branding, page eyebrow, browser title, or primary workspace controls;
- the only persistent provider attribution is the small "Powered by VeloraPlus" footer;
- platform/admin surfaces may retain VeloraPlus identity where the operator is working on the SaaS platform itself.

## 30. Final rule

**VeloraPlus should look like VeloraPlus — with the operational maturity of modern SaaS dashboards, not a clone of any one product.**


## 31. Company workspace consistency delivery

The Company workspace is now normalized around the shared operational pattern:

- Customers, Locations, and Staff keep full-width paginated tables with collapsed create flows.
- Users now uses the same full-width table + collapsed add flow instead of a persistent side form.
- Roles keeps permission detail readable while moving custom-role creation into a collapsed flow.
- Shared Company labels, actions, empty states, and confirmations use the Dashboard translation contract.
- Arabic Company screens render localized labels through the same Dashboard locale switch.
- Tenant-facing Company screens do not expose provider branding in their workspace content.
- The Dashboard topbar shows tenant context once rather than repeating the tenant name.

This increment preserves the existing backend authorization, tenant isolation, pagination, and CRUD routes.
