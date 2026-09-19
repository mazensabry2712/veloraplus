# VeloraPlus — Phase 3 Module Catalog

## Status

**IN PROGRESS — catalog foundation implemented; local verification and CI are the remaining closing gates.**

Phase 3 turns the documented Module/Feature/Bundle commercial catalog into a central platform domain. Entitlements and Billing remain separate phases.

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

### Application services

- CatalogManager — module/feature/bundle lifecycle and composition.
- CatalogDependencyManager — dependency graph and circular-dependency validation.
- CatalogPriceManager — catalog price validation and overlap protection.
- CatalogPricingResolver — resolves the current applicable catalog price.

## Central schema

The Phase 3 migration adds:

- modules
- features
- catalog_dependencies
- bundles
- bundle_modules
- bundle_features
- catalog_prices

No tenant database tables are introduced in this phase.

## Tests

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

## Verification gate

Local:

~~~powershell
vendor/bin/pint --dirty --format agent
php artisan test --compact
git diff --check
git status
~~~

Redis is not required for the Phase 3 test suite because phpunit.xml uses CACHE_STORE=array.

Phase 3 closes only after the local suite and GitHub Actions are both green.

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

Billing remains after the Entitlement architecture contract.
