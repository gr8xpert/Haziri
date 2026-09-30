# ADR 0001 — Tenancy setup and local stack

Status: accepted (2026-10-01)

## Versions
Laravel 13, PHP **8.4** (spatie/laravel-activitylog 5 and Pest 5 require it, so the Plesk subscription must run PHP 8.4),
stancl/tenancy **3.10** (latest stable; v4 isn't stable yet), Filament 5 (Livewire 4), spatie/laravel-permission 8, MariaDB 10.11.

## Tenancy
- `App\Models\Central\Tenant`: ULID `id` (`App\Support\Tenancy\UlidGenerator`) plus a unique `slug`. The DB name is
  `hazri_t_{slug}`, stored as stancl's internal `tenancy_db_name`, so renaming the slug never moves the DB.
- Identification: `InitializeTenancyByDomainOrSubdomain`. The `domains` row holds just `slug` for subdomains, or the full
  host for a custom domain (phase 4). Unknown hosts → 404 (`$onFail` in `TenancyServiceProvider`).
- Central domains: root, `admin.`, `adms.`, `api.` (from `HAZRI_*_DOMAIN`). The super-admin Filament panel is bound to `admin.*`
  with its own `super_admin` guard (`super_admins` table). The tenant panel lives at `{slug}.*/admin`.
- The queue (`jobs`, `failed_jobs`, `job_batches`) always uses the `central` connection; stancl's queue bootstrapper
  restores tenant context inside the worker.
- **No `CacheTenancyBootstrapper`**: it needs a taggable store and Plesk has no Redis. The cache is the shared file store.
  Consequences:
  - The spatie permission cache key is switched per tenant on `TenancyBootstrapped` (see `TenancyServiceProvider`).
  - Any app cache key or unique-job id must include `tenant('id')` (e.g. `RecomputeAttendanceDay::uniqueId()`).
- `TenantSettings` is a scoped singleton that is also forgotten on every tenancy switch (`tenants:run` loops over tenants in one process).
- Sessions: file driver, cookie per host (`SESSION_DOMAIN=null`), so each tenant subdomain has its own session.

## Dedicated mode
`HAZRI_MODE`/`HAZRI_DEDICATED_TENANT` are in config and `.env.example`; the root-domain → tenant mapping is **not wired yet** (next milestone).

## Local stack
`docker compose up -d` → nginx :8000, PHP-FPM 8.4, MariaDB 10.11 (host port 3307). `*.hazri.localhost` resolves to
127.0.0.1 in browsers without editing hosts. `docker compose --profile cron up -d cron` mimics the Plesk cron (scheduler + queue).

Known Docker Desktop issue on this machine: if containers hang in "Created", quit Docker Desktop from the tray,
run `wsl --shutdown`, then reopen it.
