# VeloraPlus — Phase 3 Module Catalog

## Status

**CLOSED / VERIFIED — central Module/Feature/Bundle catalog foundation is implemented and verified.**

Phase 3 establishes the centrally managed commercial catalog. Entitlements and Billing remain separate phases.

## Scope implemented

### Modules

Each Module has:

- stable machine key;
- name and description;
- lifecycle status;
- core flag;
- sort order;
- metadata.

Supported statuses:

- draft
- active
- coming_soon
- deprecated
- retired

Core Modules are infrastructure capabilities and are not customer-purchasable.

### Features

Each Feature belongs to exactly one Module and has:

- stable machine key;
- name and description;
- lifecycle status;
- billing mode;
- required/optional behavior;
- individual purchase flag;
- sort order;
- metadata.

Supported billing modes:

- flat
- per_unit
- per_seat
- usage

Usage is represented now as a catalog billing mode only; usage metering/billing remains future work.

### Dependencies

Catalog dependencies are central configuration data.

A dependency may point from a Module or Feature to another Module or Feature.

The application validates:

- supported target types;
- duplicate edges;
- self-dependencies;
- circular dependencies.

Circular dependency checks run before persistence.

### Bundles

Bundles have:

- stable machine key;
- lifecycle status;
- discount in basis points (10% = 1000);
- independent component membership for Modules and Features;
- sort order and metadata.

Bundle membership is idempotent through the pivot primary keys.

### Pricing catalog

Pricing is separate from Billing.

Catalog prices support:

- Module, Feature, or Bundle priceability;
- monthly/yearly billing cycles;
- ISO-like currency codes;
- optional country-specific overrides;
- integer minor-unit amounts;
- draft/active/retired price status;
- effective windows.

The resolver prefers an active country-specific price and falls back to the global price for the requested currency.

Active prices cannot overlap for the same priceable item, billing cycle, currency, and country scope.

Core Modules cannot receive customer catalog prices.

## Application services

- `CatalogManager` — module/feature/bundle lifecycle and composition.
- `CatalogDependencyManager` — dependency graph and circular-dependency validation.
- `CatalogPriceManager` — catalog price validation and overlap protection.
- `CatalogPricingResolver` — resolves the current applicable catalog price.

## Central schema

The Phase 3 migration adds:

- `modules`
- `features`
- `catalog_dependencies`
- `bundles`
- `bundle_modules`
- `bundle_features`
- `catalog_prices`

No tenant database tables are introduced in this phase.

## Verification completed

Local verification on the current MySQL environment:

- Catalog migration completed successfully.
- `vendor/bin/pint --dirty --format agent`: passed.
- `php artisan test --compact`: **38 tests passed, 135 assertions**.
- `git diff --check`: clean.
- `git status`: clean and synchronized with `origin/main`.

GitHub Actions:

- Workflow for commit `98bbb45a8d37f1ffbc9b00ec86c2047fde7ada84`: **successful**.

## Test coverage

The catalog feature suite covers:

- module/feature creation;
- key and status validation;
- dependency cycles and duplicates;
- bundle composition and discount bounds;
- integer minor-unit pricing;
- country-specific price precedence;
- core-module pricing protection;
- pricing-window overlap protection;
- lifecycle status changes.


## SEO / Public Catalog integration

Phase 3 creates catalog data that may later appear on public Platform pages.

The SEO layer may expose public, indexable catalog information such as Module/Feature descriptions and published pricing only when the corresponding Platform page is intentionally public.

Rules:

- internal catalog administration pages remain non-indexable;
- draft, coming_soon, deprecated, and retired entries are not automatically public SEO pages;
- SEO content must use the public catalog representation rather than leaking internal metadata;
- prices shown publicly must be generated from the approved catalog pricing model and current public commercial state;
- catalog changes that create a new public URL must update the SEO/public-web policy before release.

## Important boundary

Phase 3 defines catalog data and catalog pricing only.

It does not activate tenant entitlements, create subscriptions, process payments, or grant business-module access.

Those responsibilities remain in later phases.

## Next after Phase 3

Phase 4 is Entitlements:

~~~
Catalog
  ↓
Subscription/Billing source
  ↓
Entitlement Resolver / Projection
  ↓
Tenant Feature Availability
~~~
