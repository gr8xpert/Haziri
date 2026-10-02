# Haziri — multi-tenant attendance SaaS

Laravel 13 · PHP 8.4 · Filament 5 · stancl/tenancy 3 (DB per company) · MariaDB/MySQL · Pest.
Full specification: `ATTENDANCE_SPEC.md`. Decisions: `docs/adr/`. History: `docs/CHANGELOG.md`. Settings: `docs/settings.md`.

## Run locally (Docker)

```bash
cp .env.example .env
docker compose up -d app db web
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate:fresh --seed     # plans, super-admin, demo company
npm install && npm run build                                  # /me PWA + kiosk assets
docker compose --profile cron up -d cron                      # scheduler + queue (like Plesk cron)
```

| URL | Who | Login |
|---|---|---|
| http://admin.hazri.localhost:8000 | super-admin | admin@hazri.localhost / password |
| http://demo.hazri.localhost:8000/admin | company panel | owner@demo.test / password |
| http://demo.hazri.localhost:8000/me | employee PWA | any user linked to an employee |
| http://demo.hazri.localhost:8000/kiosk/{token} | reception tablet | Devices → Kiosks |

`vendor/` lives in a Docker volume (fast); it is not visible on the host.

## Tests

```bash
docker compose exec app vendor/bin/pest          # engine unit tests + tenant feature tests
cd agent && python -m unittest discover -s tests # bridge agent
```

## Layout

- `app/Services/Attendance/Engine` — the pure status engine (spec §7); `AttendanceRecomputer` is the only writer of `attendance_days`.
- `app/Actions` — business actions (punch ingestion, leave, corrections, periods, payroll, tenants).
- `app/Filament/{Central,Tenant}` — the two admin panels. `app/Reports` — R1–R17.
- `agent/` — Python bridge agent for ZKTeco machines without cloud push. `deploy/` — Plesk deploy, cron, backup.

## Useful commands

`hazri:tenant:create {slug} {company} --plan= --admin-email=` · `tenants:run hazri:attendance:recompute-recent` ·
`tenants:run hazri:permissions:sync` · `hazri:ai:grant-readonly` · `hazri:billing --invoice --enforce`
