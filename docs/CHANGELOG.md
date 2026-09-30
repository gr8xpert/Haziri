# Changelog

## 2026-10-01 — Milestone 2: admin panel, manual entry, reports (§17 steps 4–5) + M1 follow-ups

- Tenant panel: branches, departments, designations, employees (Excel import with preview + template,
  bulk assign shift / move branch / mark left), shifts, timing overrides (Ramadan/Jummah), holidays
  (+ "Load Pakistan holidays", confirm provisional), punch log, users & roles, audit log, unmapped PINs.
- Manual entry: single (punch log → Manual entry) and bulk daily sheet; receptionist limited to today/yesterday.
- Punch pipeline: `IngestPunchAction` (dedupe hash, double-tap window, PIN mapping, unmapped PINs, recompute),
  `MapUnmappedPinAction`, `CreateManualPunches`.
- `AttendanceImpactServiceProvider`: holiday / assignment / override / leave / marker / punch edits / employee
  date changes queue recomputes automatically (clamped to the last 62 days, never the future).
- Reports R1 daily muster, R2 monthly sheet, R3 late, R4 early-out, R5 absent (streaks), R10 employee statement —
  on screen + Excel + PDF (`app/Reports`, generic `ReportPage`, `ReportExporter`).
- Dashboard: today's counters, in-office-now list, unmapped PIN / offline device alerts.
- Central panel: companies (create via provisioning, suspend/activate, feature flags, audited "log in as owner"),
  plans. Central activity log table.
- Dedicated mode wired (`IdentifyTenant` middleware; root domain is not central in dedicated mode). Suspended tenants get 403.
- Dev: `vendor/` moved to a Docker named volume (composer 10+ min → ~30 s).
- Tests: 86 passing.

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
