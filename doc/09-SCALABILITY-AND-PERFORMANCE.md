# VeloraPlus — Scalability & Performance Architecture

## 1. Global-scale objective

VeloraPlus is intended to become a global SaaS platform.

Performance and scalability are therefore architecture requirements, not late optimization work.

The engineering target is:

- fast user-facing response times;
- predictable performance as tenant count grows;
- horizontal application scaling;
- safe operation under large concurrent traffic;
- efficient use of database connections;
- asynchronous processing for work that does not need to block a request;
- low-latency delivery for users in different regions;
- graceful degradation when an external dependency is slow or unavailable.

Do not optimize only for the first local MVP. Every major subsystem should have a clear scaling path.

## 2. Scaling model

The preferred long-term deployment shape is:

~~~
Clients
  ↓
CDN / Edge
  ↓
Load Balancer
  ↓
Stateless Laravel App Nodes
  ├── App Node
  ├── App Node
  ├── App Node
  └── ...
  ↓
Redis
  ├── Cache
  ├── Queue
  ├── Locks
  └── Sessions
  ↓
Database Layer
  ├── Central Platform DB
  └── Tenant DBs
        ├── Tenant DB A
        ├── Tenant DB B
        └── ...
~~~

The architecture must not require one permanent application server.

## 3. Stateless application servers

Application nodes should remain stateless.

Do not rely on:

- local PHP session state;
- local uploaded files;
- local process memory as the source of truth;
- node-specific application state.

Required direction:

- shared session storage;
- Redis-backed cache where appropriate;
- queue workers independent from web nodes;
- object storage/private storage for files;
- tenant-aware configuration and cache keys.

This allows multiple app nodes to serve the same tenant safely.

## 4. Database scaling

The platform uses a central/control database plus one dedicated database per company.

Benefits:

- strong tenant isolation;
- smaller working sets per tenant;
- easier tenant backup/restore;
- reduced cross-tenant contention;
- independent scaling and maintenance paths.

The implementation must also be prepared for very large tenant counts.

Future database scaling may include:

- multiple database hosts;
- tenant placement across database hosts;
- read replicas where justified;
- workload separation;
- connection pooling/proxying where justified;
- archival strategies for very large datasets.

The application must not assume that every tenant database lives on the same physical MySQL server forever.

## 5. Database performance rules

Every high-traffic query must be index-aware.

Required practices:

- index foreign-key-like lookup columns;
- index tenant-local business keys used for lookups;
- use composite indexes for common filter/sort patterns;
- avoid unbounded queries;
- paginate large result sets;
- prefer cursor pagination for very large ordered datasets where appropriate;
- avoid N+1 queries;
- select only required columns for heavy queries;
- measure slow queries;
- use transactions only around necessary write boundaries.

Do not add indexes blindly. Indexes must support real access patterns without creating unnecessary write cost.

## 6. Central database protection

The central database is a critical shared resource.

Avoid putting high-volume tenant operational traffic into central tables when it belongs in a tenant database.

Central services should primarily hold:

- platform identities;
- tenant registry;
- catalog;
- subscriptions;
- platform invoices/payments;
- provider/webhook state;
- platform-level configuration.

Tenant operational data belongs in tenant databases.

## 7. Redis strategy

Redis is a shared scalability component.

Use it for:

- cache;
- queues;
- distributed locks;
- rate limits;
- short-lived coordination;
- shared session storage where configured.

Cache keys must include tenant identity when data is tenant-sensitive.

Distributed locks are preferred for critical concurrency boundaries such as:

- booking slot allocation;
- queue transitions where needed;
- idempotency operations;
- other race-prone business actions.

Do not use Redis as the only permanent source of business truth.

## 8. Queues and asynchronous processing

Requests should return quickly.

Move non-immediate work to queues when it does not need to complete before the user receives the main response.

Examples:

- email;
- notifications;
- exports/imports;
- report generation;
- webhook processing;
- recurring billing tasks;
- usage aggregation;
- media processing;
- integration synchronization.

Queue jobs must be:

- idempotent where retried;
- tenant-aware;
- observable;
- retry-safe;
- bounded against runaway work.

Critical synchronous business validation must remain synchronous.

## 9. Booking performance

Booking is the first production module and is expected to face burst traffic.

The booking design must prevent:

- double booking;
- duplicate queue positions;
- race-condition overwrites;
- repeated webhook side effects.

Availability queries should be optimized for the exact requested:

- tenant;
- location;
- service;
- staff;
- date/time range.

Do not load an entire calendar into application memory when only a limited time window is required.

Use short-lived caching only where correctness is preserved. Final booking confirmation must re-check availability atomically.

## 10. Public booking traffic

Public booking endpoints are internet-facing and may receive traffic spikes.

Required protections include:

- rate limiting;
- bot/abuse controls where necessary;
- narrow query scopes;
- pagination;
- cacheable public metadata where safe;
- protection against expensive repeated availability queries;
- idempotency for operations that can be retried.

Public booking must not expose internal tenant data.

## 11. Frontend performance

Blade remains the rendering baseline, but pages should be kept lightweight.

Rules:

- load only required JavaScript;
- keep module scripts separated by page/module;
- defer non-critical assets;
- minimize blocking JavaScript;
- paginate large tables;
- avoid rendering thousands of DOM nodes at once;
- use lazy loading where appropriate;
- optimize images;
- serve static assets through CDN/edge delivery in production;
- keep API/fetch payloads small and purpose-built.

Do not ship every module's JavaScript to every page.

## 12. Caching policy

Use caching where it reduces expensive repeated work without creating stale critical state.

Good candidates:

- module catalog;
- feature catalog;
- pricing catalog;
- resolved entitlements;
- public service metadata;
- dashboard aggregates with controlled freshness.

Never treat a stale cache as authoritative for:

- payment confirmation;
- final entitlement activation;
- final booking availability;
- financial totals.

Critical writes invalidate or version affected cache entries.

## 13. Tenant-aware cache design

Every tenant-sensitive cache key must include a stable tenant identifier.

Preferred conceptual form:

~~~
velora:{environment}:tenant:{tenant_id}:{domain}:{key}:{version}
~~~

Central/platform cache entries must remain distinct from tenant entries.

When tenant identity or entitlement versions change, affected cache namespaces should be invalidated or versioned.

## 14. API and response performance

Even though VeloraPlus is primarily Blade-based, internal AJAX/fetch endpoints must be designed as small-purpose interfaces.

Rules:

- return only fields needed by the caller;
- avoid huge nested payloads;
- paginate collections;
- validate early;
- avoid expensive repeated serialization;
- use HTTP caching headers where appropriate;
- use compression at the edge/server layer.

## 15. Search strategy

MVP starts with MySQL-compatible search.

The Search abstraction must allow migration to a dedicated search engine later when traffic or dataset size justifies it.

Possible future architecture:

~~~
Application
  ↓
Search Abstraction
  ↓
MySQL search
        OR
Dedicated search engine
~~~

Business logic must not depend directly on one search vendor.

## 16. Global / multi-region readiness

VeloraPlus should be capable of serving users globally.

The application architecture should keep open a path for:

- regional application nodes;
- CDN/edge delivery;
- geographically closer static assets;
- regional workers where useful;
- database placement close to tenant workloads where operationally feasible.

Do not introduce multi-region write complexity in the MVP without a real requirement. The important requirement now is to avoid architecture that makes future regional deployment impossible.

## 17. Observability

Performance must be measurable.

Production observability should cover:

- request latency;
- throughput;
- error rates;
- database query latency;
- queue latency and failures;
- cache hit/miss behavior;
- Redis health;
- external provider latency;
- webhook processing;
- tenant-level abnormal load;
- CPU/memory saturation.

Define and track p50/p95/p99 latency for important flows.

## 18. Load and stress testing

Performance claims must be backed by measurement.

Before major releases, test at least:

- normal concurrent browsing;
- login/company switching;
- public booking traffic;
- appointment creation under contention;
- queue operations;
- payment/webhook bursts;
- large tenant datasets;
- large tenant count behavior.

Record:

- throughput;
- p50/p95/p99 latency;
- error rate;
- CPU/memory;
- DB utilization;
- Redis utilization;
- queue depth.

Do not choose infrastructure capacity from guesswork alone.

## 19. Capacity planning

Capacity planning must be based on measured workload.

Track:

- active tenants;
- requests per second;
- appointments/bookings per minute;
- queue depth;
- DB connections;
- DB storage growth;
- cache memory;
- file/object-storage growth;
- webhook volume.

Scaling rules should be documented before traffic reaches infrastructure limits.

## 20. Graceful degradation

When non-critical dependencies fail or slow down:

- keep core booking/business operations available where safe;
- queue non-critical notifications;
- retry transient provider failures;
- avoid cascading failures;
- show clear temporary status to users;
- record failures for operators.

Do not silently lose business events.

## 21. Reliability rules

Scalability is not only throughput.

Critical operations must be:

- transactional where required;
- idempotent where retried;
- concurrency-safe;
- observable;
- recoverable.

Backups and recovery procedures are part of the scaling/reliability design.

## 22. Performance acceptance rule

A feature is not considered production-ready because it works functionally.

For high-traffic paths, acceptance includes:

~~~
Functional correctness
+
Tenant isolation
+
Authorization
+
Query/index review
+
Concurrency review
+
Cache review
+
Queue/offloading review
+
Rate-limit review
+
Load-test evidence where appropriate
+
Observability
+
Documentation
~~~

## 23. Engineering principle

Optimize for:

**low latency + horizontal scalability + isolation + correctness + observability**

not for benchmark numbers at the expense of business correctness.

The system should scale by adding resources and distributing workload, not by rewriting the platform after traffic arrives.

## 24. Custom Domain / Edge scalability

Custom Domain traffic is part of the public edge path and must not turn tenant routing into a central bottleneck.

Requirements:

- domain lookup must be cheap and index-backed;
- verified domain mappings may be cached;
- tenant-sensitive cache keys include tenant identity;
- edge/SSL provider interactions are asynchronous where possible;
- DNS/SSL health reconciliation runs through retry-safe jobs;
- provider outages must not corrupt Tenant or billing state;
- application nodes remain stateless;
- custom-domain routing must continue to work across horizontally scaled application nodes;
- default VeloraPlus subdomains remain available as a fallback path.

A domain provider or reverse proxy may terminate TLS and forward the normalized host to the application. The application must trust forwarded host headers only from configured infrastructure.

Custom Domain performance is part of the same p50/p95/p99 and observability model used for public Booking traffic.