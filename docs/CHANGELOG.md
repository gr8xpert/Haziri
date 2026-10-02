# Changelog

## 2026-10-02 — Legacy TMS parity (client screenshots)

- **Pay cycle** (`pay_cycle_start_day`, e.g. 21st → 20th) used by month lock, payroll, monthly sheet, statement,
  payroll input, analytics and report defaults. **Leave year** (`leave_year_start_month`, e.g. October) used by
  balances, accruals, carry-forward (the open-year job now runs on the 1st of the start month) and the leave screens.
- **Exemptions** (Time rules → Exemptions): excuse lates, or the whole day (`EXEMPTED`, paid), for the company, a branch
  or a department on a date. New status code `EX`.
- **Late / half-day leave adjustment**: optionally charge the late roll-up and half days to a leave type instead of
  salary; written as leave adjustments on month lock, reversed on unlock.
- Leave types: **grade / category entitlements** (repeater on the leave type) and **allow negative balance**
  (overdraft carries into the next leave year). Employees: **earns leave** switch.
- Masters: **grades**, **contractors**, **leaving reasons** (seeded: resigned, terminated, lay-off, retired, contract
  ended). Employee master: father/husband name, address, city, payroll ID, phones, office ext., emergency contact,
  grade, contractor, leaving reason; Excel import takes the new columns (grades created on the fly).
- **Manual attendance over a date range** and **remove manual punches over a range** (Punches → Bulk manual).
- **Monthly roster generator** (Roster → Generate monthly roster: whole pay month on one shift for all / a branch / a
  department / everyone on a shift). Bulk "Assign shift" takes an end date. **Holiday ranges** (Eid 10–13 Apr in one go).
- Reports: new **Attendance summary**, **Yesterday & today time**, **Employee information**, **Monthly leave
  summary**; every report gains designation, shift, category, contractor and employee-status filters; daily muster
  gains an attendance-status filter; leave register a leave-type filter; the statement shows the scheduled shift.
- Dev: Laravel Boost installed; `pint.json` pins the existing code style. Tests: 210 PHP passing.

## 2026-10-01 — Milestone 10d: billing, custom domains, white-label, photo gallery, deploy kit

- Billing (central): monthly invoices (active employees × plan price + fixed fee, due in 10 days), payment references
  submitted by the owner (bank / JazzCash / Easypaisa) and confirmed by super-admin, suspension after due + 15 grace
  days, automatic reactivation when paid, plan employee-limit enforcement on new employees.
- Super-admin: invoices screen, **unknown devices** screen with one-click "attach to company" (spec §6.1; was missing).
- Custom domains (CNAME to the company subdomain) and white-label (logo, primary colour, email sender) — plan feature
  `white_label`. Punch-photo gallery ("all punches for PIN 47 this week").
- `deploy/`: `deploy.sh`, `cron.txt`, `backup.sh`, `plesk-notes.md`; project `README.md`.
- Security (review finding): SQL guard now checks every item of comma-joined FROM lists, including after derived
  tables; Ask Hazri **requires** the read-only DB user (SELECT on the four views only) — verified by a DB-level test.
- Tests: 182 PHP + 5 Python passing.

## 2026-10-01 — Milestone 10c: public API + webhooks (§13.3)

- Feature flags `public_api` and `white_label` added.
- Personal access tokens (hashed, abilities `read` / `punches:write`, revocable, 60 req/min per token) —
  System → API tokens. Endpoints on the tenant host: `GET /api/v1/employees`, `GET /api/v1/attendance?from&to&employee`,
  `GET /api/v1/leave/balances?year`, `POST /api/v1/punches` (source `api`), `GET /api/v1/periods/{Y-m}/payroll-input`.
- Webhooks (System → Webhooks): events `punch.created`, `attendance_day.updated`, `leave.decided`, `period.locked`,
  `payroll.generated`; headers `X-Hazri-Event/Delivery/Timestamp/Signature` (`sha256=HMAC(secret, "{ts}.{body}")`);
  server-generated secret; SSRF guard on save and on every attempt; no redirects; 5 tries with backoff; delivery log; test ping.

## 2026-10-01 — Milestone 8: employee PWA, kiosk, mobile punch (§17 step 11)

- `/me` installable PWA (Blade + Alpine + Tailwind via Vite, RTL for Urdu): login by email/password or phone OTP
  (WhatsApp/SMS, rate-limited), today + punch button, month calendar, leave balances/requests, corrections,
  announcements with acknowledgement, daily-rotating QR badge (signed, tenant-bound).
- Mobile punch: GPS + accuracy + selfie; haversine geofence against the branch (field staff exempt, accuracy slack
  ≤ 100 m); outside → `pending` punch with `geofence_fail`, HR approves in "Punch approvals"; 1 punch / 60 s.
- Kiosk `/kiosk/{token}`: PIN keypad (+ optional hashed 4-digit kiosk PIN), QR scan (BarcodeDetector), selfie,
  auto/in/out, branch lock, offline IndexedDB queue replayed with the original time + client id (idempotent),
  spoken greeting. Kiosk admin: create (auto device), link, rotate token.
- Selfies stored privately, served only via signed 30-minute URLs. Service worker caches the app shell.
- Phase-3 schema: announcements (+acks), visitors, salary structures, advances, payroll runs, payslips.
- Tests: 121 passing.

## 2026-10-01 — Milestone 7: notifications (§17 step 10)

- `NotificationChannel` contract; drivers: mail, in-app (Filament database notifications + bell), log;
  WhatsApp / SMS resolve to a per-tenant provider (log until one is configured — M9 adds real drivers).
- Templates: defaults in `config/notification_templates.php` (English + Urdu), tenant overrides in
  `notification_templates` (System → Notification texts). `{{ placeholders }}` filled from event data.
- `Notifier` + queued `SendNotification` (3 tries, backoff, quiet hours for WhatsApp/SMS), every attempt in `notification_logs`.
- Events: leave requested / decided (incl. next approver), correction requested / decided, device offline, month locked;
  scheduled every 15 min: not-in-yet alert to managers (11:00), missing punch-out (shift end + 2 h), daily summary
  (18:00), unmapped PINs, probation (7 days) / contract (30 days) ending, birthdays — each deduped per day.
- Company settings page for all §7.4 rule parameters, approval chains and notification timing; "Recompute this month".
- Tests: 111 passing.

## 2026-10-01 — Milestone 6: leave, corrections, month lock (§17 step 9)

- Approval chains (`ApprovalChain`): setting-driven manager → HR, manager skipped when absent, HR approval completes the chain.
- Leave: types (quota, accrual, carry-forward, half-day, short leave, sandwich, attachment rule, eligibility),
  balances (yearly upfront prorated for joiners, monthly accrual, carry-forward cap), requests (self / on behalf),
  validation (balance, overlap, max consecutive, locked month), sandwich rule at approval, cancel with balance reversal,
  leave calendar ("who's off"), balance adjustments. Scheduled accrual + year opening.
- Corrections & OD/WFH: add/remove punch, change status, mark OD/WFH; auto-applied on final approval;
  missing-punch suggestion = median usual out time (spec §12.4, statistical).
- Months: open → review → locked (per-employee snapshot, rows frozen) → unlock (owner/admin + reason, audited).
- Reports R6 missing punch, R7 overtime, R8 leave balances, R9 leave register, R11 department summary,
  R12 payroll input (uses the locked snapshot), R14 manual entry audit, R15 device sync.
- `hazri:permissions:sync` (deploy step). Admin may unlock months (spec §5.7).
- Tests: 106 passing.

## 2026-10-01 — Milestones 3–5: ingestion, ADMS receiver, bridge agent (§17 steps 6–8)

- USB import page: ZKTeco `*_attlog.dat` (tolerant parser) and Hazri CSV/XLSX template; preview with duplicates,
  unmapped PINs (inline mapping) and date range; import history (`usb_imports`).
- ADMS/iClock push via `tanemrahman/zkteco-adms`, tenant resolved per device serial (`ResolveAdmsTenant`);
  punches → `IngestPunchAction`; USERINFO → unmapped PINs with device names; unknown serials recorded centrally and refused.
- Device management: per-type setup guide, comm key, remote commands (sync time, push all employees, delete user,
  reboot) through the package queue (ADMS) or `device_commands` (agent); agent keys (issue / revoke).
- Agent API `/api/v1/agent/{punches,users,commands,commands/{id}/result,heartbeat}` with hashed bearer keys and rate limit.
- `hazri:devices:check` every 5 min (offline after 15 min, `DeviceWentOffline` once per outage) + daily time sync.
- `/agent`: Python bridge agent (pyzk, SQLite watermark, batching, commands, heartbeat), PyInstaller builds, NSSM +
  systemd service files, unit tests with a fake device.
- ADR 0003. Tests: 96 PHP + 5 Python passing.

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
