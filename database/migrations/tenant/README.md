# Tenant migrations

Every company database receives the migrations in this directory.

These migrations must contain tenant-owned data only. Never add cross-database foreign keys to central tables.

The central database uses `database/migrations`. Tenant databases use this directory through the tenant provisioning/migration path.