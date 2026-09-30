# Changelog

## 2026-10-01 — Milestone 1: scaffold, tenant schema, status engine (§17 steps 1–3)

- Laravel 13 / PHP 8.4 app with Docker Compose dev stack (nginx, PHP-FPM, MariaDB 10.11).
- stancl/tenancy 3.10: central DB + `hazri_t_{slug}` per tenant, subdomain/custom-domain identification. See ADR 0001.
- Filament 5 panels: `central` (admin.* domain, super-admin guard) and `tenant` (`{slug}.*/admin`).
- `php artisan hazri:tenant:create {slug} {company} --plan= --admin-email=`: creates DB, migrates, seeds roles and
  permissions (config/permissions.php), the default shift, CL/SL/AL leave types, settings and Pakistan holidays; prints the owner login.
- Central tables: plans, tenants, domains, tenant_settings, super_admins, device_registry, unknown_devices, agent_keys.
- Tenant schema for spec §5.1–5.7 (+ `attendance_markers` for OD/WFH, `device_commands`).
- Status engine (pure) + period calculator + shift overrides + sandwich rule. Rules: ADR 0002.
- `AttendanceRecomputer` (the only writer of `attendance_days`), `RecomputeAttendanceDay` (debounced, unique per
  tenant+employee+day), `RecomputeAttendanceRange` (chunked), nightly `hazri:attendance:recompute-recent` via `tenants:run`.
- Tests: 62 passing (engine unit tests + tenant feature tests). Pending: unmapped→mapped PIN (arrives with ingestion).
- Settings documented in docs/settings.md.
