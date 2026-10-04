# Changelog

## 2026-10-04 — Owner panel review round

- **Readable labels everywhere**: no more internal codes (add_punch, pending, official_duty) in badges, dropdowns and
  filters; also punch sources, anomaly types, audit events, notification channels and role names.
- **Day-first dates** in every form (30/09/2026 whatever the browser language) and table (30 Sep 2026).
- **Required fields** marked with a large bold red star; the employee form says "Fields marked * are required".
- **Manual Entry**: the "Daily attendance sheet" is renamed (it is bulk hand entry, not a report); the punch log's
  single-punch button is now "Add one punch".
- **Corrections & OD/WFH**: details in plain words ("Out punch at 18:10"; fixed: they were shown twice), Requested by,
  Approval ("Waiting for manager / HR"), and a one-line explanation under Type in the request form.
- **Months (lock) → View**: new Month summary report (also under Reports): one line per employee — present, late,
  half day, leave, OD/WFH, absent, overtime, deduction and payable days — from the lock snapshot for locked months, live
  otherwise; each name opens that employee's day-by-day Monthly attendance report. The page explains Open → Review → Locked.
- **Employee form**: main fields on top (code, machine PIN, name, department, designation, joining date, shift, type,
  status), the rest under "More details"; fields of switched-off features are hidden (payroll ID, kiosk PIN, phone punch,
  photo, contractor, branch when there is only one — the employee is put in it).
- **Employee import**: works with the client's own sheet — columns guessed from common names and confirmed by HR
  (remembered), Pakistani formats (dd/mm/yyyy, CNIC with dashes, M/F, Y/N, one Name column split), branch/department by
  code or name, "Create missing departments"; template with drop-downs, an Instructions sheet and the company's codes;
  optional "Suggest with AI" sends only the column headings.
- **Face check on PIN, QR and phone punches** (company setting, needs face recognition): the photo's face is compared with
  the enrolled one; wrong face, no face or not enrolled → the punch waits in Punch approvals (new "Why" column). Kiosk data
  is only trusted from approved tablets; on phones it deters casual buddy punching but can be fooled by a tech-savvy user.
  Punch photos shows match % and has a "Suspicious only" filter.
- **Announcements**: scheduled ones notify when they publish (fixed: they notified on save); channels per announcement
  (in-app / email / WhatsApp / SMS) with reach counts; shown on the kiosk after a punch with "I have read it"; printable
  A4 notice (English + Urdu heading).
- Security fixes: the import page could be pointed at any file on the server; spreadsheet exports wrote text starting
  with "=" as a live formula.
- Tenant migrations: `announcements.notified_at`, `announcements.channels` (run `php artisan tenants:migrate`).

## 2026-10-04 — Super-admin review round

- **White label is branding only**: white-label clients are invoiced, see Billing and are suspended for non-payment like
  everyone else. Their Billing page uses `HAZRI_PAYMENT_INSTRUCTIONS_WHITE_LABEL` (no platform name; `HZ-` numbers stay).
- **Plans**: editing a plan's feature switches updates every company on it (only the changed switches, so a
  company's own extras stay).
- **Companies**: feature switches moved from the row pop-up to the edit page; picking another plan there brings its
  switches. The employee limit is now a hand-set override only; blank follows the plan (migration clears old copies).
- **Password recovery**: "Forgot password?" on the company login (mail in the client's name), profile page to change
  your own password, super-admin "Reset owner password" (shown once, audit-logged).
- **Unknown machines**: Block / Unblock, check-ins recorded at most once a minute, unblocked rows pruned after 30 days.
- **Super-admin dashboard**: companies by status, active employees, billed this month, unpaid / overdue, payments to
  confirm, unknown machines, recently added companies.
- Fixed: the central audit log could not record entries about a company, so "Log in as owner" failed.
- **Trials end after 15 days** (`HAZRI_TRIAL_DAYS`): the company is locked the moment its trial ends ("Your free trial
  has ended") and suspended by the daily `hazri:billing --enforce` until the super-admin activates it. "Trial ends" is
  editable on the company to extend it; dashboard card "Trials ending soon" (3 days). Existing trials got 15 days from today.

## 2026-10-03 — Review round: feature switches, kiosk, anti-fraud, multi-branch, white-label, full payroll, face recognition

- **Feature switches** (super-admin): 19 features under Attendance / Payroll Lite / Payroll Full / Add-ons, per plan and per
  company; connected features turn on/off together (config/features.php, FeatureCatalog). Fixed: saving a company's
  features crashed.
- **Kiosk Auto in/out** decided from the last punch in 16 h (night shifts, mixed presses); double taps say
  "Already punched IN at 09:00" instead of a fake success.
- **Reports**: employee filter everywhere; exemption report; branch summary; tax register; EOBI / social security
  challan; PF statement; gratuity liability.
- **Anti remote-punching**: kiosk locked to approved tablets (pairing code), optional office-IP lists for kiosks and
  branches, per-employee "can punch from phone", minimum GPS accuracy, required selfie, "impossible travel" anomaly,
  `TRUSTED_PROXIES`.
- **Multi-branch**: users limited to branches (global scope in the admin panel), Branch manager role, dashboard branch
  switcher and "Branches today" comparison.
- **White-label 100%**: Brand helper; app name, AI assistant name, logo, app icon, colour, email name; super-admin can set
  and lock; `HAZRI_NEUTRAL_DOMAIN`; no billing for white-label clients; guard test for any visible "Haziri".
- **Payroll Full**: income tax (editable slabs, 2025-26 pre-filled — verify), EOBI / PESSI-SESSI, provident fund ledger,
  gratuity, bonuses / one-off items, final settlement with PDF, bank letter, payroll rules page.
- **Face recognition** on the kiosk (@vladmandic/human): enrolment page, head-turn + anti-spoof liveness on the tablet,
  server-side matching of descriptors only.
- Tests: 260 PHP passing.

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
  tables; Ask Haziri **requires** the read-only DB user (SELECT on the four views only) — verified by a DB-level test.
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

- USB import page: ZKTeco `*_attlog.dat` (tolerant parser) and Haziri CSV/XLSX template; preview with duplicates,
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
