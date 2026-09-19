# VeloraPlus — SEO & Public Web Architecture

## Status

**DECISION LOCKED — SEO/Public Web contract documented; implementation is a cross-cutting delivery before Public Booking is production-ready.**

This document is the canonical reference for public web rendering, SEO, crawl/index controls, canonical URLs, sitemaps, structured data, public Tenant pages, and the boundary between public pages and authenticated workspace pages.

## 1. Scope

VeloraPlus has two SEO surfaces:

1. VeloraPlus Platform Website — centrally controlled marketing and commercial pages.
2. Tenant Public Websites — company-owned public pages served through the Tenant default hostname or an active custom domain.

SEO is cross-cutting. It must not be duplicated across Controllers or Booking services.

## 2. Public request boundary

~~~
HTTP Request
    ↓
Trusted Host Normalization
    ↓
Tenant Domain Resolution
    ↓
Verified Active Domain
    ↓
Tenant Context
    ↓
Public Route
    ↓
SEO Manager + Public Data Query
    ↓
Blade SSR HTML
~~~

Authenticated workspace pages keep the existing authorization chain:

~~~
Tenant → Membership → Entitlement → Permission/Policy → Controller/Application Service
~~~

Public pages must not require a logged-in membership.

## 3. Rendering strategy

- Public pages are server-rendered with Blade.
- Core public content must exist in the initial HTTP response.
- JavaScript may enhance interactions but must not be required to discover core public content.
- Dynamic booking availability may use fetch/AJAX after initial render.
- Private tenant data must never be embedded in public HTML.
- React, Vue, Inertia, and Livewire are not introduced for SEO.

## 4. Platform SEO

Platform public pages may include:

~~~
/
/features
/pricing
/solutions/{vertical}
/about
/contact
~~~

Every indexable page must define a unique title, useful meta description, canonical URL, Open Graph metadata, semantic H1, indexable body content, and an appropriate robots policy.

## 5. Tenant public SEO

Tenant public pages use:

~~~
{tenant-slug}.velora.com
~~~

After Custom Domain implementation they may use a customer-owned verified active hostname.

Initial public page candidates:

~~~
/
/about
/services
/services/{service-slug}
/contact
/book
~~~

Additional pages are allowed only when a real public content model exists.

## 6. Canonical host

A Tenant may have several valid hostnames, but one public host is canonical.

Priority:

1. active primary custom domain when configured and healthy;
2. otherwise the active primary VeloraPlus hostname.

All indexable Tenant URLs use the canonical host. The current request host must not automatically become canonical. Canonical host selection is derived from the central tenant_domains registry; Tenant SEO settings cannot choose an arbitrary canonical hostname.

## 7. URL rules

- production URLs use HTTPS;
- hostnames are normalized to lowercase;
- canonical URLs omit the default port;
- canonical URLs do not contain tracking parameters;
- a consistent trailing-slash policy is used;
- query/filter/selection state is not the canonical form of public content;
- stable public slugs are preferred over internal IDs;
- intentional URL migrations use permanent redirects only when the new destination is stable and relevant.

## 8. Service SEO prerequisite

The Service schema now has a stable Tenant-local `slug` field. Before indexable Service pages are released, public routing and SEO rendering still need to be implemented.

Recommended URL:

~~~
/services/{service-slug}
~~~

Rules:

- slug is unique within the Tenant;
- slug is derived from the public name but may be manually controlled;
- published slug changes preserve the old URL through redirect history when that feature is implemented;
- archived/unpublished services are not indexable and are excluded from the sitemap;
- Service database ULIDs are not human-facing SEO URLs;
- current Service slugs are stable across Service name changes because the manager preserves the stored slug on rename.

## 9. Metadata model

Tenant-level SEO defaults should use the existing tenant company_settings key/value foundation, not a separate platform-only metadata store.

Reserved namespaced settings may include:

~~~
seo.site_title
seo.site_description
seo.default_og_image
seo.robots
seo.locale
~~~

Page-level SEO overrides may later be added to public content entities. For Services this means public slug plus optional SEO title, SEO description, and social image reference.

Precedence:

~~~
Page override
    ↓
Entity-generated value
    ↓
Tenant SEO default
    ↓
Platform fallback
~~~

Unknown SEO settings are ignored safely. A Tenant robots preference may refine public crawling behavior, but it can never override system rules that protect private routes, inactive/unverified domains, or tenant isolation.

## 10. Robots and noindex

Private/authenticated pages should be non-indexable, using an appropriate noindex policy.

Examples:

~~~
/login
/register
/dashboard/*
/account/*
/billing/*
/settings/*
~~~

Every public host exposes a dynamic /robots.txt.

An indexable Tenant host should publish its own sitemap URL:

~~~
User-agent: *
Allow: /
Sitemap: https://tenant.example.com/sitemap.xml
~~~

robots.txt is not an access-control mechanism and is not the sole defense against indexation of private URLs.

## 11. Sitemap architecture

Every public host exposes /sitemap.xml.

Platform sitemap: canonical public platform URLs only.

Tenant sitemap: canonical public Tenant URLs only, such as Tenant home, public information pages, and published active online-bookable Services.

Exclude dashboard, login/account pages, dynamic booking state, search/filter URLs, customer-specific pages, appointment IDs, private documents, and archived/unpublished services.

## 12. Public Booking SEO boundary

Separate SEO content from transactional booking state.

Indexable:

~~~
/services/dental-cleaning
~~~

Non-indexable transactional state:

~~~
/book/dental-cleaning?date=2026-09-20&staff=01...
~~~

Dynamic availability URLs must not be included in the sitemap. Public Booking keeps its existing rate limiting, data minimization, final availability check, and concurrency guarantees.

## 13. Structured data

Structured data is server-generated JSON-LD and must describe visible public content.

Baseline types may include:

- the most specific applicable tenant business type;
- Service for public Service pages;
- BreadcrumbList for hierarchical public pages.

Never fabricate ratings, reviews, prices, business claims, or private data.

## 14. Open Graph and images

Public pages should support og:title, og:description, og:url, og:type, og:image, and og:site_name, with social card metadata generated from the same SEO model.

Public images must have appropriate alt text, stable public URLs, efficient delivery, and reserved layout dimensions. Private tenant files must never be made public only for SEO.

## 15. Internal linking

Important public pages must be reachable through normal crawlable links.

~~~
Tenant Home → Services → Service Detail → Book
~~~

Core public pages must not be reachable only through JavaScript interactions.

## 16. Locale

Tenant locale already exists. Multilingual SEO is supported conceptually, but hreflang is emitted only when equivalent localized pages actually exist.

Canonical and hreflang relationships must be reviewed together when multilingual public content is introduced.

## 17. Errors and removals

- unknown public page/service → 404;
- intentionally migrated published URL → 301 to the exact replacement when available;
- permanently gone content with no suitable replacement may use 410 when explicitly chosen by the product lifecycle.

Private/unavailable Tenant data must not reveal whether a hidden record exists.

## 18. Caching and performance

- only non-sensitive public content is cacheable;
- cache keys include canonical Tenant/domain identity;
- public metadata/sitemap/robots caches use short, safe lifetimes;
- public content changes invalidate or version relevant cached output;
- stale cache must never expose another Tenant;
- public page performance is included in the platform p50/p95/p99 monitoring model.

Booking availability remains request-specific and is never authoritative from SEO caches.

## 19. SEO application architecture

Recommended responsibility boundaries:

~~~
app/
├── Domain/
│   └── SEO/
├── Application/
│   └── SEO/
├── Http/
│   ├── Controllers/
│   │   └── Public/
│   └── Middleware/
└── Models/
~~~

Conceptual responsibilities:

- SeoManager — resolves normalized metadata for the current public page;
- SitemapBuilder — builds canonical public URLs;
- Robots policy — generates host-aware crawl rules;
- StructuredDataBuilder — generates JSON-LD from visible public data;
- canonical URL builder — resolves the current canonical host centrally.

Exact physical placement must follow the existing modular-monolith conventions and must not introduce a conflicting app/Modules structure.

## 20. Blade contract

The public layout should receive one normalized SEO view model instead of duplicating metadata logic per template.

~~~
<x-seo :title=... :description=... :canonical=... :robots=... :open-graph=... :schema=... />
~~~

## 21. Tenant isolation/security

SEO/public code must:

- trust only the configured host resolution boundary;
- resolve exactly one active Tenant domain;
- query Tenant public data inside the current Tenant context;
- reject user-supplied tenant_id routing;
- never render private Customer, Appointment, payment, or administrative data;
- never expose secrets or provider credentials in HTML, JSON-LD, sitemap, or metadata.

## 22. Testing

Automated coverage must include:

- platform vs Tenant public route separation;
- canonical URL generation;
- custom-domain canonical selection once Custom Domains are active;
- metadata generation;
- private route noindex behavior;
- robots.txt output;
- sitemap output;
- Service slug uniqueness;
- exclusion of archived/unpublished Services;
- slug-change redirects when redirect history exists;
- structured-data Tenant isolation;
- no private Customer/Appointment data in public HTML;
- cache isolation;
- 404 behavior;
- public booking rate limiting.

Browser/production QA must inspect rendered HTML, not only JavaScript state.

## 23. Delivery slices

### SEO-1 — Foundation

Status: **COMPLETED IN THIS SLICE**
- separate Platform and Tenant public routes;
- SEO value object/view model;
- shared Blade SEO component;
- Platform metadata defaults;
- canonical URL builder;
- robots policy;
- sitemap framework;
- private-route noindex policy.

### SEO-2 — Tenant Public Surface
- Tenant public home;
- company public branding/content;
- Tenant SEO settings;
- canonical host handling;
- Tenant robots/sitemap.

### SEO-3 — Public Services
- stable Service slugs;
- Service public detail page;
- page-level SEO overrides;
- Service structured data;
- slug redirect history.

### SEO-4 — Public Booking
- Service landing pages link into Booking;
- transactional booking state remains non-indexable;
- public Booking keeps rate limiting and data minimization.

### SEO-5 — Production SEO
- Search Console/domain ownership process;
- sitemap submission;
- structured-data validation;
- crawl/index monitoring;
- Core Web Vitals review;
- production domain/custom-domain verification.

## 24. Dependencies and sequencing

SEO requires stable Tenant host resolution, Company Settings, public route separation, Booking Services for Service pages, and public media handling.

Custom-domain canonicalization depends on the later Custom Domain infrastructure delivery.

SEO does not depend on Tenant Payments and payment-provider code must not enter the SEO layer.

SEO-1 through SEO-3 are the foundation required before Public Booking is released as an indexable public surface.

## 25. Definition of done

SEO/Public Web is production-ready only when:

1. Platform pages have correct metadata and canonical URLs.
2. Tenant pages resolve only through a trusted active Tenant domain.
3. Canonical URLs use the selected canonical Tenant host.
4. Private routes are non-indexable.
5. Each public host serves correct robots.txt and sitemap.xml.
6. Published Service pages use stable slugs.
7. Archived/unpublished services are excluded.
8. Structured data matches visible content.
9. No private Tenant/customer data is rendered.
10. Cache keys/content remain Tenant-safe.
11. Public pages are server-rendered and crawlable without client-side rendering.
12. Transactional booking states remain outside the primary indexable surface.
13. Custom-domain canonicalization works after Custom Domain infrastructure is complete.
14. Automated tests and production HTML/SEO checks pass.
15. Search monitoring and sitemap procedures are documented.

## 26. Future additions

After SEO-1 through SEO-5, the public-web roadmap may add:

- industry landing-page templates;
- blog/news/knowledge content;
- localized/multilingual public sites;
- hreflang support;
- richer media/image transformations;
- tenant redirect manager;
- large-tenant sitemap indexes;
- structured-data expansion for supported industries;
- consent-aware analytics;
- Company Admin SEO audit dashboard.

Any future public URL must enter the sitemap/robots/canonical/content policy before release.

## 27. Change control

Any requirement that creates a public, crawlable, shareable, or indexable URL must update this document and the affected route, data, test, tenancy, and roadmap documentation before implementation.