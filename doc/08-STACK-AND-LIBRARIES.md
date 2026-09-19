# VeloraPlus — Stack & Library Policy

## 1. Target stack

| Layer | Standard |
|---|---|
| Language | PHP 8.4 |
| Framework | Laravel 13 |
| Database | MySQL 8.4 |
| Cache/Queue | Redis |
| Templates | Blade |
| CSS | Tailwind CSS 4 |
| UI state | Alpine.js |
| Browser logic | Vanilla JavaScript |
| Bundler | Vite |
| Auth | Laravel Fortify |
| Roles/Permissions | Spatie Laravel Permission |
| Tenancy | Stancl Tenancy |
| Money | Integer minor units (Brick Money may be introduced when package locking is updated) |
| Testing | Pest |
| Static analysis | Larastan |
| Formatting | Laravel Pint |
| Initial payment provider adapter | Kashier (provider-neutral gateway architecture) |
| Custom domain/edge | Provider-neutral internal abstraction; production provider selected later |

## 1A. SEO/Public Web implementation policy

SEO must start with Laravel-native server-rendered behavior rather than adding a dedicated SEO package.

Required baseline capabilities are implemented through application code and Blade:

- metadata model/view model;
- Blade SEO component;
- canonical URL generation;
- robots.txt response;
- sitemap.xml response;
- JSON-LD structured data;
- public-page tests.

A third-party package may be introduced only after compatibility, maintenance, licensing, security posture, and actual capability gaps are reviewed and documented.
## 2. Backend packages

Foundation/runtime packages:

- laravel/framework
- laravel/fortify
- stancl/tenancy (current stable line verified for Laravel 13; integrate via Composer lock)
- spatie/laravel-permission
- integer minor-unit money arithmetic in the Billing domain; Brick Money may be added later when the dependency lock is updated through the normal Composer workflow.
- Redis integration through Laravel's native cache/queue APIs

Engineering/dev packages:

- Pest + Laravel plugin
- Larastan
- Laravel Pint

Shared optional packages, added when the corresponding feature is implemented:

- spatie/laravel-activitylog — tenant/platform audit history
- spatie/laravel-medialibrary — managed media/files when needed

Feature-specific packages are added only when required.

## 3. Frontend packages

Foundation:

- tailwindcss
- @tailwindcss/vite
- vite
- alpinejs

Booking:

- fullcalendar
- flatpickr
- tom-select

Dashboard:

- chart.js

UI utilities:

- sweetalert2
- lucide

CRM/ordering:

- sortablejs

## 4. Documents

Possible later additions:

- DOMPDF integration — invoices/reports/PDF documents;
- Laravel Excel — business exports/imports.

Add them only when the first feature requiring them is implemented.

## 5. HTTP client policy

Prefer browser fetch() for simple frontend AJAX.

Axios is not a default dependency.

Backend external integrations should prefer Laravel's HTTP client or a provider SDK where justified.

## 6. Frontend framework policy

Do not introduce:

- React;
- Vue;
- Inertia;
- Livewire;
- jQuery;

unless a future architecture decision explicitly changes the baseline.

## 7. Package approval checklist

Before introducing a package:

- Laravel 13 compatibility verified;
- PHP 8.4 compatibility verified;
- maintenance/release health reviewed;
- security/advisories reviewed;
- license reviewed;
- Laravel-native overlap checked;
- tests defined;
- package reason documented.

## 8. Repository baseline note

The initial repository is a Laravel 13 skeleton. Existing starter dependencies are the starting point, not a permanent list.

Implementation should update composer.json and package.json deliberately and keep lockfiles committed.


## 9. Performance/scalability baseline

The selected stack must support horizontal scaling and global delivery.

Baseline expectations:

- Redis for shared cache/queues/locks/session storage where configured;
- stateless Laravel application nodes;
- MySQL tenant databases that can be distributed across database hosts later;
- Vite-built assets suitable for CDN delivery;
- asynchronous queues for non-blocking work;
- application and database observability;
- load/stress testing for critical flows before production scale.

Packages are not added merely for performance. Prefer Laravel/platform capabilities first, then introduce infrastructure or libraries when measured workloads justify them.

Custom Domain / SSL tooling must follow the same approval policy. The core application depends on an internal provider-neutral contract; a specific edge/SSL vendor is an infrastructure decision, not a Domain/Tenancy business dependency.
