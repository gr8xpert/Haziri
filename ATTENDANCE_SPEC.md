# Haziri — Multi-Tenant Attendance SaaS (Build Specification)

> Working name: **Haziri** (Urdu for "attendance"). Rename freely; treat `Haziri` as a placeholder in code namespaces and config.
>
> Audience: this file is the source of truth for Claude Code building the app. Read it fully before writing code. When something is ambiguous, prefer the simplest option that keeps **one codebase, zero per-client code**, and ask before adding packages not listed in §2.

---

## 0. TL;DR

- Cloud attendance system for offices in **Pakistan**. Companies (tenants) sign up, get `company.hazri.pk`, add employees, and collect punches from **ZKTeco-class machines**, a **tablet kiosk**, a **mobile PWA**, **USB log upload**, or **manual entry** by a receptionist.
- A deterministic **status engine** resolves every employee-day into one status (present / late / half-day / leave / holiday / off / absent …) using configurable rules, including Pakistan-specific ones (Ramadan timings, Jummah break, "3 lates = 1 absent", sandwich leave rule).
- **Multi-tenant**: one codebase, one deploy, **one MySQL database per tenant** plus a central database. The same codebase can run as a **dedicated single-tenant instance** on a client's own VPS with only `.env` changes.
- Hosted on a **Plesk** VPS (PHP + MySQL/MariaDB, cron-driven queues). No Redis/Supervisor assumed.
- AI (Anthropic API) is used only on the **reporting/assistance layer** (natural-language reports, monthly narrative, anomaly explanations, missing-punch suggestions). Payroll math and status resolution are never AI-driven.

---

## 1. Goals and non-goals

### Goals
1. Replace "machine + ZKBioTime on a Windows PC + Excel" with a browser app the receptionist and HR use daily.
2. Work with machines that have **no cloud/ADMS** (the cheap, common ones) via a bridge agent and USB import.
3. Cover every real-world scenario an office hits: leave, holidays, WFH, official duty, missing punch-out, night shifts, Ramadan, daily-wage staff, multiple branches.
4. Produce the numbers payroll needs (deduction days, overtime hours, unpaid leave) as Excel/PDF, without becoming a full payroll system in v1.
5. Be sellable at low monthly per-employee pricing → the shared-tenant setup must be cheap to run.

### Non-goals (v1)
- Full payroll / tax computation (only payroll-*lite*, §9.3).
- Native mobile apps (PWA only).
- Storing biometric templates (fingerprint/face data). Never persisted; see §6.1.
- Per-client custom code. All variation goes through tenant settings and feature flags.

---

## 2. Tech stack (fixed unless a blocker is documented)

| Layer | Choice | Notes |
|---|---|---|
| Language / framework | PHP 8.3+, **Laravel** (latest stable) | Plesk PHP-FPM handler; doc root = `public/` |
| Database | MySQL 8 / MariaDB 10.6+ | Central DB + one DB per tenant |
| Tenancy | **`stancl/tenancy`** (latest stable) | DB-per-tenant, subdomain identification, `tenants:migrate` |
| Admin UI | **Filament** (latest stable) | Two panels: `central` (super-admin) and `tenant` (company admin/HR/manager/receptionist). If Filament + stancl integration causes friction, document it and fall back to Livewire + Blade with the same page structure |
| Employee self-service + kiosk | Blade + Alpine.js + Tailwind, installable **PWA** | Must work offline for short periods (kiosk) |
| Auth / roles | Laravel auth + `spatie/laravel-permission` | Roles in §8 |
| Audit log | `spatie/laravel-activitylog` | Every create/update/delete on attendance-affecting models |
| Queues | `database` driver, run by cron (`queue:work --stop-when-empty --max-time=55` every minute) | Redis optional if present; never required |
| Scheduler | `schedule:run` via cron every minute | |
| Excel | `openspout/openspout` (or `maatwebsite/excel`) | Every report exports to XLSX |
| PDF | `barryvdh/laravel-dompdf` | Payslips, monthly sheets. No headless Chrome dependency |
| Device protocol | `msaied/zkteco` (TCP + ADMS) — evaluate first; if its ADMS side is insufficient use `tanemrahman/zkteco-adms` for push | Never hand-roll the wire protocol |
| Bridge agent | Python 3 + `pyzk`, packaged with PyInstaller into a Windows service/EXE | Lives in `/agent` in the repo |
| Notifications | Email (Plesk SMTP), WhatsApp + SMS through a **provider interface** (`NotificationChannel` contract) with drivers added per provider | Start with email + a `LogChannel`; WhatsApp/SMS drivers are pluggable |
| AI | Anthropic Messages API from PHP (server-side only) | Model chosen from current docs; keep model ID in config |
| Tests | Pest | Status engine must have exhaustive tests (§7.6) |
| Front-end build | Vite | |

Conventions: PSR-12, strict types, Actions/Services for business logic (controllers thin), Enums for all statuses, all datetimes stored **UTC**, tenant timezone default `Asia/Karachi`, money as integer rupees, dates as `Y-m-d`.

---

## 3. Architecture

```
                 ┌──────────────────────── Plesk VPS ────────────────────────┐
                 │                                                           │
   Browser ───►  │  *.hazri.pk  ──► Laravel (tenant panel, PWA, kiosk)       │
                 │  admin.hazri.pk ► Laravel (central/super-admin panel)     │
   ZK device ──► │  adms.hazri.pk/iclock/* ► ADMS push receiver (central)    │
   Bridge agent► │  api.hazri.pk/api/v1/agent/* ► JSON ingestion (central)   │
   Kiosk tablet► │  {tenant}.hazri.pk/kiosk/{token}                          │
   Employee ───► │  {tenant}.hazri.pk/me  (PWA, mobile punch)                │
                 │                                                           │
                 │  Central DB ─┬─ tenants, domains, plans, billing          │
                 │              └─ device_registry (serial → tenant)         │
                 │  Tenant DBs  ── hazri_t_{slug} … one per company          │
                 │  Cron ── scheduler + queue worker                         │
                 └───────────────────────────────────────────────────────────┘
```

### 3.1 Request routing
- `admin.hazri.pk` → central panel (super-admin only).
- `adms.hazri.pk` and `api.hazri.pk` → **central** routes; they identify the tenant by device serial / agent API key, then run the ingestion inside `tenancy()->initialize($tenant)`.
- `{slug}.hazri.pk` → tenant context via subdomain. Custom domains (CNAME) supported later via `domains` table.
- Kiosk and employee PWA are tenant routes.

### 3.2 Dedicated-instance mode
Same repo, same release. `.env`:
```
HAZRI_MODE=dedicated          # default: shared
HAZRI_DEDICATED_TENANT=acme   # slug of the single tenant
```
In dedicated mode the central DB still exists (one tenant row), billing UI is hidden, and the root domain maps straight to that tenant. Moving a client from shared → dedicated = dump their tenant DB, restore on the new server, set `.env`. **No code differences.**

### 3.3 Feature flags
Every optional module is a per-tenant flag in `tenant_settings` (central DB) mirrored to the tenant's `settings` table: `machine_sync`, `kiosk`, `mobile_punch`, `leave`, `payroll_lite`, `visitors`, `ai_reports`, `whatsapp`. Plans in the central DB define default flags; super-admin can override per tenant.

---

## 4. Tenancy details

### 4.1 Central database (`hazri_central`)
| Table | Purpose |
|---|---|
| `tenants` | id (ulid), slug, company_name, timezone, status (trial/active/suspended), plan_id, employee_limit, data (json) |
| `domains` | tenant_id, domain (subdomain or custom), is_primary |
| `plans` | name, price_per_employee, price_fixed, feature_flags (json), limits (json) |
| `subscriptions` | tenant_id, plan_id, starts_at, ends_at, status, billing_cycle |
| `invoices`, `payments` | manual bank transfer / JazzCash / Easypaisa reference fields; no payment gateway in v1 |
| `device_registry` | serial_number, tenant_id, comm_key, type (adms/agent/kiosk), last_seen_at, last_ip |
| `agent_keys` | tenant_id, key_hash, label, last_used_at, revoked_at |
| `super_admins` | login for the central panel |
| `tenant_settings` | tenant_id, key, value (feature flags, overrides) |
| `central_audit_logs` | activitylog for central models |

### 4.2 Tenant provisioning
`php artisan hazri:tenant:create {slug} {company} --plan=starter --admin-email=…`
1. Create tenant + primary domain.
2. Create DB `hazri_t_{slug}`, run tenant migrations + seeders (default shift, default leave types, current-year Pakistan holidays, default settings, roles).
3. Create owner user, email login link.
4. Emit `TenantProvisioned` event.

Also available from the central panel. Deletion is soft (suspend) + scheduled hard delete after 30 days with a DB dump kept for 90 days.

### 4.3 Tenant migrations
All tenant schema lives in `database/migrations/tenant/`. `php artisan tenants:migrate` runs on every deploy (see §14). Migrations must be additive and backwards-compatible within a release (no column drops in the same release as the code that stops using them).

---

## 5. Tenant data model

Only key columns are listed; add `id`, timestamps, soft deletes where sensible. `employee_id` FKs everywhere below refer to `employees`.

### 5.1 Organisation
- `branches` — name, code, address, lat, lng, geofence_radius_m (default 150), timezone (nullable → tenant default), weekly_off_days (json, default `[0]` Sunday)
- `departments` — name, code, parent_id (nullable), head_employee_id
- `designations` — name, grade (nullable)
- `users` — login accounts; `employee_id` nullable (HR/receptionist may not be employees); role via spatie
- `employees` — code (unique, human), first_name, last_name, cnic (nullable, validated 13 digits), phone, email, photo_path, gender, dob, branch_id, department_id, designation_id, manager_employee_id, employment_type (`permanent|contract|probation|daily_wage|intern`), joined_on, probation_ends_on, contract_ends_on, left_on, status (`active|on_leave|left`), **device_pin** (string, unique per tenant, the PIN enrolled on machines), card_no (nullable), overtime_eligible (bool), late_deduction_exempt (bool), work_mode default (`office|field|remote`), salary fields nullable (see §9.3), custom_fields (json)

### 5.2 Devices and sources
- `devices` — name, serial_number (unique), model, branch_id, type (`adms|agent|usb|kiosk|manual|mobile`), comm_key, ip (for agent), timezone, last_seen_at, last_sync_at, unsynced_count, status (`online|offline|unknown`), settings (json)
- `kiosks` — device_id, token (unique, rotated), allowed_modes (json: pin, qr, face), require_photo (bool), branch_id
- `usb_imports` — device_id, file_path, rows_total, rows_imported, rows_duplicate, rows_unmapped, imported_by, log (json)

### 5.3 Shifts and schedules
- `shifts` — name, code, start_time, end_time, crosses_midnight (bool), grace_in_min (default 15), grace_out_min (5), late_after_min (nullable → grace_in), half_day_if_late_after_min (e.g. 120), half_day_if_worked_less_than_min (e.g. 240; the least worked time for a full day), early_out_before_min (30; leaving more than this before the end is an early out), auto_close_missing_out_after_min (nullable, e.g. 720), break_min (unpaid break length), multi_punch_mode (`first_last|paired`), overtime_after_min (nullable), max_overtime_min, weekly_off_days (json, overrides branch), is_default
- `shift_assignments` — employee_id, shift_id, effective_from, effective_to (nullable). Latest effective wins. A weekly roster is just many short assignments; rosters UI writes these rows.
- `shift_overrides` — name (e.g. "Ramadan 2027"), from_date, to_date, scope (`tenant|branch|department|shift`), scope_id, start_time, end_time, grace_in_min…, priority. Applied on top of the resolved shift for dates in range. Jummah: an override with `weekday=5` and different times / a longer break.
- `holidays` — date, name, type (`gazetted|company|optional`), branch_id (nullable = all), is_paid (default true)

### 5.4 Punches
- `punches` — employee_id (nullable until mapped), device_pin, device_id, punched_at (UTC), local_date (Y-m-d in tenant tz, indexed), direction (`in|out|unknown`), source (`adms|agent|usb|kiosk|mobile|manual|api`), verify_type (`fp|face|card|pin|password|photo|none`), workcode, lat, lng, accuracy_m, photo_path, external_id (device-side uid if any), dedupe_hash (sha1 of `device_serial|device_pin|punched_at`, unique), is_manual (bool), reason (required when manual), entered_by_user_id, status (`valid|duplicate|unmapped|rejected`), meta (json)
- `unmapped_pins` — device_id, device_pin, first_seen_at, count, resolved_employee_id — surfaces "someone punched with PIN 47 but no employee has PIN 47".

### 5.5 Attendance ledger (output of the status engine)
- `attendance_days` — employee_id, date, **status** (enum §7.2), shift_id, override_id, holiday_id, leave_request_id, first_in, last_out, worked_min, break_min, late_min, early_out_min, overtime_min, expected_min, flags (json: `late, early_out, missing_out, missing_in, manual_punch, auto_closed, geofence_fail, overtime`), source_summary (json: counts per source), computed_at, computed_version, is_locked (bool), locked_by_period_id, manual_override (json: status + reason + user + at). Unique (employee_id, date).
- `attendance_corrections` — employee_id, date, requested_by, type (`add_punch|remove_punch|change_status|mark_od|mark_wfh`), payload (json), reason, attachment_path, status (`pending|approved|rejected`), approver chain (json), decided_by, decided_at

### 5.6 Leave
- `leave_types` — name, code, is_paid, annual_quota_days, accrual (`yearly_upfront|monthly`), carry_forward_max, max_consecutive_days, allow_half_day, allow_short_leave (hours), requires_attachment_after_days, sandwich_rule (bool), applies_to (json: employment_types), gender_restriction (nullable), counts_weekends (bool), is_active
- `leave_balances` — employee_id, leave_type_id, year, opening, accrued, used, pending, adjusted, remaining (computed), notes
- `leave_requests` — employee_id, leave_type_id, from_date, to_date, day_part (`full|first_half|second_half`), short_leave_from/to (nullable times), days (decimal), reason, attachment_path, status (`pending|approved|rejected|cancelled`), approver_chain (json), current_step, decided_by, decided_at, decided_note, created_by (self or HR on behalf)
- `leave_adjustments` — manual balance changes with reason (encashment, bonus leave, correction)

### 5.7 Attendance periods (month lock)
- `attendance_periods` — year, month, status (`open|review|locked`), locked_by, locked_at, summary (json snapshot). Locked periods reject recompute and edits; unlock requires owner/admin + reason (audited).

### 5.8 Payroll-lite (phase 3)
- `salary_structures` — employee_id, effective_from, basic, allowances (json), deductions_fixed (json), per_day_rate (for daily wage), overtime_rate_normal, overtime_rate_holiday, late_deduction_rule_id
- `advances` — employee_id, amount, issued_on, instalments, remaining, status
- `payroll_runs` — period_id, status, generated_by, totals (json)
- `payslips` — payroll_run_id, employee_id, lines (json), gross, deductions, net, pdf_path, sent_at

### 5.9 Other
- `announcements` — title, body, audience (json), publish_at, expires_at, requires_ack
- `announcement_acks` — announcement_id, employee_id, acked_at
- `visitors` — name, phone, cnic (nullable), company, host_employee_id, purpose, photo_path, checked_in_at, checked_out_at, badge_no, entered_by
- `settings` — key, value (json). All rule parameters that are not on `shifts` live here (§7.4).
- `notification_logs` — channel, recipient, template, payload, status, provider_ref, error
- `ai_queries` — user_id, prompt, generated_sql, row_count, duration_ms, status, feedback
- `activity_log` — from spatie/activitylog

Indexes that matter: `punches(employee_id, local_date)`, `punches(device_id, punched_at)`, `punches(dedupe_hash)` unique, `attendance_days(employee_id, date)` unique, `attendance_days(date, status)`, `leave_requests(employee_id, from_date, to_date, status)`.

---

## 6. Punch ingestion (all sources feed the same pipeline)

Every source ends in `IngestPunchAction` → normalise → dedupe → map PIN → store → dispatch `RecomputeAttendanceDay(employee_id, local_date)` (queued, debounced per employee-day for 30s). Sources never write `attendance_days` directly.

### 6.1 ADMS / iClock push (ZKTeco "Cloud Server" mode)
- Central routes under `adms.hazri.pk/iclock/*`, implemented by the chosen package. Device identified by `SN` query param → `device_registry` → tenant.
- Flow (library handles wire format; we handle business logic):
  1. `GET /iclock/cdata?SN=…&options=all` — handshake; respond with server options (transfer flags, timezone, realtime on).
  2. `POST /iclock/cdata?SN=…&table=ATTLOG` — attendance lines (`PIN  datetime  status  verify  workcode …`). Parse → ingest. Respond `OK`.
  3. `POST /iclock/cdata?SN=…&table=OPERLOG|USERINFO|FP|FACE|BIODATA|USERPIC|ATTPHOTO` — accept and **discard** biometric templates (FP/FACE/BIODATA/USERPIC). USERINFO may be used to auto-create `unmapped_pins` entries with the device-side name to help mapping. ATTPHOTO is stored only if tenant setting `store_punch_photos=true` (default true, photos kept 90 days).
  4. `GET /iclock/getrequest?SN=…` — device polls for commands every ~10s. Serve queued `device_commands` (add/update/delete user, sync time, reboot, check).
  5. `POST /iclock/devicecmd` — command results → mark command done/failed.
- Unknown serial → respond `OK` but log to `central.unknown_devices` (serial, ip, first_seen) so super-admin can attach it to a tenant with one click.
- `comm_key` mismatch → reject.
- Heartbeat updates `devices.last_seen_at`; scheduler marks `offline` after 15 min silence and notifies tenant admin (once per outage).
- Time: ADMS sends device local time. Convert using `devices.timezone` (default tenant tz) to UTC. Push a time-sync command daily and whenever drift > 2 min is observed.

### 6.2 Bridge agent (pull over LAN, for devices without ADMS)
Repo folder `/agent`. Python 3.11, `pyzk`, packaged as `hazri-agent.exe` (Windows service via `pywin32` / NSSM) and a Linux systemd unit for Raspberry Pi.

Config `agent.yaml`:
```yaml
server: https://api.hazri.pk
api_key: <agent key>
poll_seconds: 60
devices:
  - serial: ZK1234567        # must exist in tenant devices table
    ip: 192.168.1.201
    port: 4370
    password: 0
```
Behaviour:
- Every poll: connect, `get_attendance()`, filter records newer than the stored watermark (`last_uid`/`last_timestamp` per device in a local SQLite file), batch POST to `POST /api/v1/agent/punches`. On 2xx advance the watermark. Never clears device memory unless `clear_after_sync: true`.
- Also uploads `get_users()` weekly (PIN + name only) to help mapping.
- Polls `GET /api/v1/agent/commands` for user push (`set_user`), delete user, time sync; executes via pyzk; reports results.
- Reports agent + device health every poll; server shows "agent last seen".
- Idempotent: server dedupes by `dedupe_hash`; agent may safely resend.

`POST /api/v1/agent/punches` payload:
```json
{ "agent_version": "1.2.0",
  "device_serial": "ZK1234567",
  "punches": [ { "pin": "47", "timestamp": "2026-09-28T09:02:11+05:00",
                 "status": 0, "verify": 1, "uid": 5812 } ] }
```
Auth: `Authorization: Bearer <agent_key>`; key resolves to tenant; device serial must belong to that tenant.

### 6.3 USB log import
- Tenant page "Import from USB". Accepts ZKTeco `*_attlog.dat` (header-less, tab-separated: PIN, `YYYY-MM-DD HH:MM:SS`, status, verify, workcode…) and a Haziri CSV template (`employee_code_or_pin, date, time, direction`). Verify the exact `.dat` column order against a real file from a K40/K50 before finalising the parser; make the parser tolerant of extra columns.
- Preview step shows: rows parsed, duplicates, unmapped PINs (with inline "map to employee" dropdown), date range. Then import.
- Stored in `usb_imports` with a downloadable log.

### 6.4 Manual entry (receptionist / HR)
- Form: employee (search), date, in time, out time (optional), reason (required), note. Creates `punches` with `source=manual`, `is_manual=true`, `entered_by_user_id`.
- Bulk mode: pick a date, table of all employees with In/Out inputs, mark absent/present quickly. Common for offices with no machine at all.
- Receptionist role can add but not edit/delete manual punches after the day is over; HR can, with reason. All audited.

### 6.5 Kiosk (tablet at reception)
- Route `/kiosk/{token}`, no login; token bound to a `kiosks` row. Full-screen PWA, large buttons, works on a cheap Android tablet in Chrome kiosk mode.
- Modes (tenant-configurable): **PIN** (employee `device_pin` + optional 4-digit `kiosk_pin`), **QR** (scan the employee's QR from their `/me` page; QR is a signed token rotating daily), **Face** (phase 4: on-device face match against enrolled photo using a JS face-embedding library; never send templates to server, only match result + selfie).
- Every kiosk punch captures a selfie (if `require_photo`) and posts `{ pin, mode, direction (auto|in|out), photo, client_time }`. Server time is authoritative; `client_time` is stored in meta.
- Offline: queue punches in IndexedDB, retry with backoff, show "X punches pending" badge. Server dedupes on replay.
- Shows a friendly confirmation ("Good morning, Ali — 09:02, In") for 3 s, then resets. Spoken confirmation optional.

### 6.6 Mobile self-punch (PWA `/me`)
- Employee logs in (phone + OTP via SMS/WhatsApp, or email/password). "Punch In / Punch Out" buttons.
- Sends GPS + accuracy + selfie. Server checks geofence against the employee's branch (or any allowed site) unless `work_mode=field`. Out-of-fence punches are stored with flag `geofence_fail` and go to HR for approval instead of being auto-valid.
- Rate-limit: one punch per 60 s per employee.

### 6.7 Normalisation and dedupe rules
- Same `(device_serial, pin, punched_at)` → duplicate, ignored.
- Punches within `dedupe_window_sec` (default 60) of a previous valid punch for the same employee → marked `duplicate` (double tap on the sensor).
- `direction`: use device status codes when the tenant enables `trust_device_direction` (default false, because most offices never press the In/Out keys). Otherwise `unknown`, and the status engine infers direction by pairing.
- Unmapped PIN → stored with `status=unmapped`, employee null, listed in `unmapped_pins`. When HR maps the PIN, all such punches are re-attached and recomputed.

---

## 7. Status engine (the core; deterministic, fully tested)

### 7.1 Trigger and scope
`RecomputeAttendanceDay(employee_id, date)` runs on: new/changed/deleted punch, leave approved/cancelled, holiday added/removed, shift assignment/override change, employee joined/left date change, manual override cleared. Nightly job recomputes the last 3 days for all active employees (catches late-arriving USB imports). Locked periods are skipped and logged.

`RecomputeAttendanceRange(employee_ids[], from, to)` for bulk changes (chunked jobs).

### 7.2 Status enum (`AttendanceStatus`)
| Status | Meaning | Counts as paid day? |
|---|---|---|
| `NOT_JOINED` | date < joined_on | – |
| `LEFT` | date > left_on | – |
| `HOLIDAY` | public/company holiday | yes (if `holidays.is_paid`) |
| `WEEKLY_OFF` | employee's off day | yes |
| `LEAVE_PAID` | approved paid leave (full day) | yes |
| `LEAVE_UNPAID` | approved unpaid leave | no |
| `HALF_DAY_LEAVE` | half-day approved leave + worked other half | 0.5 + worked half |
| `OFFICIAL_DUTY` | OD / outstation marked by HR or approved correction | yes |
| `WFH` | work-from-home marked/approved | yes |
| `PRESENT` | worked ≥ `half_day_if_worked_less_than_min` (or first/last punch pairing OK) | yes |
| `HALF_DAY` | late beyond `half_day_if_late_after_min` or worked < `half_day_if_worked_less_than_min` | 0.5 |
| `ABSENT` | working day, no valid punch, no leave | no |

Flags (in `attendance_days.flags`, independent of status): `late`, `early_out`, `missing_out`, `missing_in`, `auto_closed`, `manual_punch`, `geofence_fail`, `overtime`, `short_leave`, `sandwich_applied`.

### 7.3 Resolution algorithm
```
resolve(employee, date):
  if date < joined_on → NOT_JOINED ; if left_on && date > left_on → LEFT
  shift = resolveShift(employee, date)            # assignment → override(s) by priority → default
  window = shift window on that date (handle crosses_midnight: window may end next day)
  punches = valid punches for employee within [window.start - 4h, window.end + 6h] (configurable)

  if holiday(date, employee.branch)            → HOLIDAY  (still compute worked_min/overtime if punches exist)
  if weeklyOff(shift ∪ branch, date)           → WEEKLY_OFF (same)
  leave = approved leave covering date
  if leave && day_part=full                    → LEAVE_PAID / LEAVE_UNPAID
  od/wfh marker for date                        → OFFICIAL_DUTY / WFH

  if no punches:
      if leave && day_part=half                 → HALF_DAY_LEAVE with flag missing_in (other half absent)
      → ABSENT
  pair punches:
      first_last mode: first_in = earliest, last_out = latest (if only one punch → missing_out)
      paired mode: alternate in/out; odd count → missing_out on the last
  compute late_min = max(0, first_in - (shift.start + grace_in))
          early_out_min = max(0, (shift.end - grace_out) - last_out)
          worked_min = sum(paired) - break_min ; expected_min = shift length - break_min
          overtime_min = max(0, worked_min - expected_min) if eligible && > overtime_after_min, capped
  status:
      if leave half-day                          → HALF_DAY_LEAVE
      elif late_min > half_day_if_late_after_min → HALF_DAY, flag late
      elif worked_min < half_day_if_worked_less_than_min && !missing_out → HALF_DAY
      else                                       → PRESENT
  flags per thresholds; missing_out: if auto_close_missing_out_after_min set and now > first_in + that → set last_out = first_in + expected, flag auto_closed
  sandwich rule: if leave type has sandwich_rule and a weekly off/holiday sits between two leave days → mark those days LEAVE_* too, flag sandwich_applied (computed in leave approval, not here)
  manual_override present → status from override, keep computed numbers, flag manual
```

### 7.4 Rule parameters (tenant `settings`, with defaults)
`dedupe_window_sec=60`, `trust_device_direction=false`, `lates_per_absent=3` (0 = disabled; applied at period level → "deduction days"), `late_count_resets=monthly`, `half_day_counts_as=0.5`, `absent_deduction_days=1`, `unpaid_leave_deduction_days=1`, `overtime_enabled=false`, `overtime_rounding_min=15`, `punch_search_window_before_min=240`, `punch_search_window_after_min=360`, `auto_absent_after_days=1` (mark ABSENT only after the day is over), `store_punch_photos=true`, `photo_retention_days=90`, `geofence_default_radius_m=150`, `allow_future_manual_punch=false`, `employee_can_see_others=false`.

### 7.5 Period computations (monthly)
Computed on demand and snapshotted when locking a period:
- Present days, half days, absents, paid leave days, unpaid leave days, holidays, weekly offs, OD/WFH days, late count, early-out count, total worked hours, overtime hours.
- **Deduction days** = absents + unpaid leave + (half days × 0.5) + floor(late_count / lates_per_absent) (if enabled and employee not exempt).
- Payable days = calendar days − deduction days (or, for daily-wage: present + half×0.5 + paid holidays if configured).

### 7.6 Tests (mandatory before any UI work)
Pest tests in `tests/Unit/StatusEngine/` covering at minimum: on-time present; late within grace; late beyond grace; late → half day; short worked → half day; missing out; auto-close; night shift crossing midnight (punches on two dates); holiday with work; weekly off; full paid leave; unpaid leave; half-day leave with/without punches; OD/WFH; sandwich rule; employee not yet joined / left; Ramadan override changes thresholds; duplicate punch tap; unmapped then mapped PIN; locked period rejects recompute; manual override; daily-wage payable days; lates_per_absent rollup. Use a fixed tenant timezone `Asia/Karachi` and freeze time.

---

## 8. Roles and permissions

| Role | Scope | Can |
|---|---|---|
| `super_admin` (central) | all tenants | provision/suspend tenants, plans, device registry, impersonate tenant owner (audited) |
| `owner` | tenant | everything below + billing + settings + unlock periods + delete data |
| `admin` | tenant | everything except billing/deletion |
| `hr` | tenant | employees, shifts, leave approvals (final step), corrections, manual punches (any time), reports, lock periods, payroll-lite |
| `manager` | own department(s) / direct reports | view team attendance, approve team leave/corrections (first step), request OD/WFH for team, team reports |
| `receptionist` | branch | manual entry for today/yesterday, USB import, visitors, kiosk management, view today's board |
| `employee` | self | own attendance, leave requests, corrections, punch (if enabled), announcements |

Implement with spatie permissions named `module.action` (`attendance.view_all`, `attendance.manual_entry`, `leave.approve_final`, `reports.export`…). Roles are seeded; owner can create custom roles from permissions (phase 2).

Approval chains: setting `leave_approval_chain` = `["manager","hr"]` (default) or `["hr"]`; same for corrections. Manager missing → skip to next step.

---

## 9. Modules and features by phase

Each phase is shippable on its own. Do not start a phase until the previous one's "Done when" holds.

### Phase 1 — Core (sellable to offices with an old machine + Excel)
**Tenant setup**: provisioning command + central panel; owner login; company profile (name, logo, timezone, week start, financial year start); branches; departments; designations.
**Employees**: CRUD, Excel import (template download → upload → preview → import with error report), photo, device PIN, employment type, joined/left dates, status. Bulk actions (assign shift, assign branch, deactivate).
**Shifts**: CRUD, default shift, assignment per employee/department/branch with effective dates, shift overrides (Ramadan, Jummah), weekly offs.
**Holidays**: CRUD + "load Pakistan gazetted holidays {year}" seeder (editable; dates for Eid etc. are provisional and flagged for confirmation).
**Manual entry**: single + bulk daily sheet.
**Status engine** + recompute jobs + nightly job.
**Reports (all with Excel export + filters by branch/department/date)**:
1. Daily muster roll: employee, shift, in, out, status, late/early min, flags, source.
2. Monthly attendance sheet: employee rows × day columns with status codes (P, A, L, H, WO, HD, OD, WFH, ML/CL/AL codes), totals columns.
3. Late/early register.
4. Absent register.
5. Employee monthly statement (single employee, printable PDF).
**Dashboard**: today's present/absent/late/on-leave counts, live "in office now" list, unmapped PINs alert, device status, pending approvals.
**Audit log** viewer.
Done when: a receptionist can enter a month of attendance manually for 50 employees and HR exports a correct monthly sheet; all §7.6 tests pass.

### Phase 2 — Machine sync + leave
**ADMS receiver** (§6.1), **bridge agent** (§6.2), **USB import** (§6.3), device management page (add device, choose type, comm key, show setup instructions per type with screenshots for the ZKTeco menu path, last seen, unsynced count, remote commands: sync time, push all employees, delete employee, reboot).
**Unmapped PIN resolution** page.
**Leave**: types, balances (yearly upfront + monthly accrual + carry-forward job on year start), requests (self / HR on behalf), approval chain, half-day and short leave, attachments, sandwich rule, leave calendar (who's off this week), balance report, leave register.
**Corrections**: employee requests (missing punch, wrong status), OD/WFH requests, approval chain, auto-apply on approval.
**Periods**: review → lock; lock snapshot; unlock with reason.
**Notifications v1**: email + in-app; events: leave requested/approved/rejected, correction decided, device offline, unmapped PINs > 0, daily summary to HR at configurable time.
Done when: a K40 (via agent) and an ADMS-capable device both feed punches into a tenant with zero manual steps; leave approved through manager → HR changes the ledger; month lock works.

### Phase 3 — Self-service, kiosk, mobile, WhatsApp, payroll-lite
**Employee PWA `/me`**: login via phone OTP or email; own calendar view; monthly summary; leave balance + request; corrections; announcements; QR badge; punch in/out (if enabled) with geofence.
**Kiosk** (§6.5) PIN + QR modes.
**Mobile punch** (§6.6) with HR approval queue for out-of-fence punches.
**WhatsApp + SMS channels**: `NotificationChannel` drivers (first concrete driver chosen per §16); templates in Urdu/English; events: late arrival to manager (optional), absent without leave to manager at 11:00, forgot-to-punch-out reminder, leave decisions, payslip delivery, broadcast.
**Announcements** with acknowledgement.
**Payroll-lite**: salary structure per employee; monthly salary sheet = structure + period computations → gross, deduction (absent/unpaid/half/late-rollup × per-day rate), overtime pay, advances instalment, net; Excel + bank transfer CSV (configurable columns per bank); payslip PDF per employee; advances register; period must be locked before a payroll run.
**Visitors** log with photo + badge print + today's list.
Done when: an employee can apply for leave on their phone and get a WhatsApp decision; a tenant with no machine runs entirely on a tablet kiosk; HR generates a salary sheet from a locked month.

### Phase 4 — Rosters, multi-company, analytics, AI, kiosk face
**Rosters**: weekly/monthly planner (grid: employees × days → shift), publish, copy last week, shift swap requests, auto-detect shift from punch time when `auto_shift_detection=true` (nearest shift start within ±3h).
**Multi-company**: one owner login switching between tenants (central `user_tenants` pivot).
**Analytics**: punctuality score per employee (0–100), department comparison, trend charts (12 months), absenteeism cost (deduction days × per-day rate), device reliability, top-10 late list.
**AI features** (§12).
**Kiosk face mode**, punch-photo gallery for HR ("show me all punches for PIN 47 this week").
**Custom domains**, **white-label** (logo/colours/email sender), **webhooks + public API** (§13).
**Billing**: plans, invoices, manual payment confirmation, suspension on non-payment with grace period, usage (employee count) enforcement.

---

## 10. Reports catalogue (all: filters, Excel export; PDF where noted)

| # | Report | Key columns |
|---|---|---|
| R1 | Daily muster | code, name, dept, shift, in, out, worked, late, early, status, flags, source |
| R2 | Monthly sheet (PDF) | employee × days grid + totals (P/A/L/HD/LV/WO/H/OD) + deduction days |
| R3 | Late register | date, employee, shift start, in, late min, running late count |
| R4 | Early-out register | analogous |
| R5 | Absent register | date, employee, consecutive absent streak |
| R6 | Missing punch report | date, employee, which punch missing, auto-closed? |
| R7 | Overtime report | date, employee, worked, expected, OT min (rounded), eligible |
| R8 | Leave balance | employee × leave types: quota, used, pending, remaining |
| R9 | Leave register | request-level list with approvals |
| R10 | Employee statement (PDF) | one employee, month, day rows + summary |
| R11 | Department summary | dept: headcount, present %, late %, absent %, OT hrs |
| R12 | Payroll input sheet | employee: payable days, deduction days breakdown, OT hrs, unpaid leave |
| R13 | Salary sheet (phase 3, PDF + bank CSV) | gross, each deduction, net |
| R14 | Manual entry audit | all manual/edited punches with who/why |
| R15 | Device sync report | device, punches/day, last sync, gaps |
| R16 | Visitor log | daily/monthly |
| R17 | Punctuality scorecard (phase 4) | score, rank, trend |

Status codes used in grids: `P` present, `L` late (present), `HD` half day, `A` absent, `WO` weekly off, `H` holiday, `OD` official duty, `WFH`, `LV` paid leave (or the leave-type code: `CL`, `SL`, `AL`), `UL` unpaid leave, `MO` missing out, `–` not joined/left.

---

## 11. Notifications

`App\Contracts\NotificationChannel` with `send(Recipient $to, Template $tpl, array $data): DeliveryResult`. Drivers: `MailChannel`, `LogChannel`, `WhatsAppChannel` (provider chosen in §16; Meta Cloud API is the reference implementation), `SmsChannel` (Pakistani gateway; provider abstracted). Per-tenant credentials in `settings` (encrypted). Templates stored in DB with `{{ placeholders }}`, Urdu + English variants, per-event enable/disable and quiet hours. Every send logged in `notification_logs`. Retry ×3 with backoff via queue.

Events: `LeaveRequested`, `LeaveDecided`, `CorrectionDecided`, `EmployeeAbsentNoLeave` (11:00 tenant time), `EmployeeLate` (optional), `MissingPunchOut` (shift end + 2h), `DeviceOffline`, `UnmappedPinsPending`, `DailySummary`, `PeriodLocked`, `PayslipReady`, `Broadcast`, `ProbationEnding` (7 days before), `ContractEnding` (30 days before), `BirthdayToday`.

---

## 12. AI features (assistance layer only)

All calls server-side through `App\Services\Ai\AnthropicClient` (model ID in `config/hazri.php`, key in `.env`). Every call logs prompt/response metadata in `ai_queries`. Hard rules: AI never writes to attendance/leave/payroll tables; AI outputs are shown as suggestions with a human confirm step.

1. **Ask Haziri (NL → report)**: user types "who in Sales was late more than 3 times in August?". Pipeline: (a) prompt with a *curated read-only schema* (views: `v_attendance_days`, `v_employees`, `v_leave`, `v_punches_summary`) and the tenant's status codes; (b) model returns SQL in a JSON envelope; (c) validator: single `SELECT`, only whitelisted views, no subquery into other schemas, `LIMIT ≤ 5000`, runs under a **read-only DB user** scoped to the tenant DB with a 5 s timeout; (d) render table + "export" + "save as report"; (e) thumbs up/down stored. On validation failure, ask the model once to repair, then fail gracefully.
2. **Monthly narrative**: after period lock, generate a 200–300-word plain-English (and Urdu) summary from the period snapshot JSON: attendance rate, top lates, absentee patterns, leave usage, OT, comparison with previous month. Stored with the period, editable, sendable to owner.
3. **Anomaly flags** (rule-based detection, AI-written explanation): buddy punching (two PINs punching within 5 s on the same device on ≥ N days), ghost employee (active, salaried, zero punches for 30 days), sudden pattern shift (avg arrival moved > 45 min vs prior 4 weeks), impossible sequences (in at branch A and B within 20 min). Each flag has a one-line generated explanation and a "dismiss / investigate" action.
4. **Missing-punch suggestions**: for `missing_out` days, suggest an out time from the employee's median out time over the last 20 working days; shown in the corrections queue as a one-click accept (no model call needed; keep it statistical).
5. **Attachment reading**: on leave requests with a medical certificate image/PDF, extract dates/doctor/clinic into a suggestion box for HR (vision call). Never auto-approve.
6. **Setup assistant**: during onboarding, paste the company's existing attendance policy text → model proposes shift/grace/late/leave settings as a diff the owner confirms.

Feature flag `ai_reports`; per-tenant monthly token budget; disable gracefully when over budget.

---

## 13. APIs

### 13.1 Agent API (central, bearer agent key)
- `POST /api/v1/agent/punches` (§6.2)
- `POST /api/v1/agent/users` — device user list (pin, name, card)
- `GET  /api/v1/agent/commands?device_serial=` — pending commands
- `POST /api/v1/agent/commands/{id}/result`
- `POST /api/v1/agent/heartbeat` — agent version, devices reachable, errors

### 13.2 Kiosk/PWA internal API (tenant, session or kiosk token)
- `POST /kiosk/{token}/punch`, `GET /kiosk/{token}/config`
- `POST /me/punch`, `GET /me/attendance?month=`, `POST /me/leave`, `POST /me/corrections`

### 13.3 Public tenant API (phase 4, personal access tokens, rate-limited)
- `GET /api/v1/employees`, `GET /api/v1/attendance?from&to&employee`, `GET /api/v1/leave/balances`, `POST /api/v1/punches` (source `api`), `GET /api/v1/periods/{ym}/payroll-input`
- Webhooks (HMAC-signed): `punch.created`, `attendance_day.updated`, `leave.decided`, `period.locked`, `payroll.generated`

---

## 14. Deployment on Plesk

Repository layout:
```
/                Laravel app
/agent           Python bridge agent (own README, build script)
/deploy          deploy.sh, cron.txt, plesk-notes.md
/docs            this spec, ADRs, device setup guides (with screenshots)
```

Plesk setup (document in `/deploy/plesk-notes.md`):
1. Subscription for `hazri.pk` with **wildcard subdomain** `*.hazri.pk` (Plesk: add subdomain `*`), plus `admin`, `adms`, `api` either as explicit subdomains or covered by the wildcard (all point to the same document root `httpdocs/public`).
2. PHP 8.3 FPM, `memory_limit=512M`, `max_execution_time=120` for imports, `upload_max_filesize=20M`.
3. Wildcard TLS: Let's Encrypt wildcard needs a DNS-01 challenge → either host DNS in Plesk's DNS server or use `acme.sh` with the DNS provider's API (Cloudflare recommended). Document the chosen path.
4. MySQL: app user with `CREATE DATABASE` privilege (stancl creates `hazri_t_*`), plus a **read-only user** for AI queries. Ensure `sql_mode` compatible; set `max_connections` for many tenant DBs.
5. Cron (Plesk scheduled tasks, every minute):
   - `cd /var/www/vhosts/hazri.pk/httpdocs && php artisan schedule:run >> storage/logs/cron.log 2>&1`
   - `cd … && php artisan queue:work database --stop-when-empty --max-time=55 --tries=3 >> storage/logs/queue.log 2>&1`
6. Storage: `php artisan storage:link`; punch photos under `storage/app/tenant/{id}/photos` (stancl per-tenant storage prefix). Nightly rsync/backup of `storage` + `mysqldump` of all `hazri_*` DBs (script in `/deploy/backup.sh`, keep 14 days).
7. Firewall: only 80/443 inbound. Devices reach `adms.hazri.pk:443`.

`/deploy/deploy.sh` (idempotent, run over SSH or from a Plesk Git post-deploy hook):
```bash
set -e
cd /var/www/vhosts/hazri.pk/httpdocs
php artisan down --retry=30 || true
git pull --ff-only
composer install --no-dev --optimize-autoloader --no-interaction
npm ci && npm run build
php artisan migrate --force
php artisan tenants:migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache && php artisan event:cache
php artisan filament:optimize
php artisan up
```
Dedicated instance: identical script, different `.env`, single tenant row.

Environment keys to define in `.env.example`: `HAZRI_MODE`, `HAZRI_DEDICATED_TENANT`, `HAZRI_CENTRAL_DOMAIN`, `HAZRI_ADMS_DOMAIN`, `HAZRI_API_DOMAIN`, `ANTHROPIC_API_KEY`, `ANTHROPIC_MODEL`, `AI_DB_READONLY_USERNAME/PASSWORD`, `WHATSAPP_PROVIDER`, `SMS_PROVIDER`, plus per-provider keys.

---

## 15. Engineering conventions for this repo

- **No per-client code, ever.** Variation = settings, feature flags, plans.
- Business logic in `app/Actions/*` (single-purpose invokable classes) and `app/Services/*`; Filament resources and controllers stay thin.
- Enums in `app/Enums/*` for statuses, sources, roles, employment types. Never raw strings.
- Every attendance-affecting write goes through an Action that dispatches recompute. Direct model writes to `attendance_days` are forbidden outside `App\Services\Attendance\StatusEngine`.
- Time handling: store UTC; convert at the edges with the tenant (or device/branch) timezone; `local_date` is always derived from tenant tz. Never use `now()` without tz awareness in engine code.
- Jobs are idempotent and chunked (≤ 500 employees per job).
- Tenant-aware everything: queue jobs carry tenant id (stancl handles), scheduled commands iterate tenants with `tenants:run`.
- Tests: Pest; status engine ≥ 90% coverage; feature tests for each ingestion source using fixture payloads (`tests/Fixtures/adms/*.txt`, `usb/*.dat`, `agent/*.json`).
- Migrations: additive; seeders for roles, default shift, leave types (CL/SL/AL as defaults, paid), settings, current-year holidays.
- Errors: never swallow ingestion errors silently; log with tenant + device context and surface in the device page.
- Security: rate-limit all public endpoints; kiosk tokens rotatable; agent keys hashed; HMAC on webhooks; signed URLs for photos; encrypt provider credentials; CSP headers; impersonation audited.
- Localisation: `en` and `ur` language files from day one (UI strings through `__()`); RTL-ready layout for Urdu in the employee PWA.
- Docs: ADR file per non-trivial decision in `/docs/adr/`.

---

## 16. Decisions still open (fill in before Phase 3)

| Decision | Options | Default if unanswered |
|---|---|---|
| Product name + domain | — | `Haziri`, `hazri.pk` |
| Filament vs custom Livewire UI | Filament / Livewire | Filament |
| Device package | `msaied/zkteco` / `tanemrahman/zkteco-adms` | evaluate `msaied/zkteco` first |
| WhatsApp provider | Meta Cloud API direct / local BSP | Meta Cloud API |
| SMS gateway | local Pakistani provider | abstracted; `LogChannel` until chosen |
| Payment collection | manual bank / JazzCash / Easypaisa / gateway | manual with reference number |
| Pricing model | per employee/month tiers | starter (≤25 emp), business (≤100), enterprise/dedicated |
| Exact `.dat` column order for target devices | needs a real sample file | tolerant parser + preview step |
| Face-on-kiosk library | JS face-embedding lib (on-device) | phase 4 |

---

## 17. Suggested order of work for Claude Code

1. Repo scaffold: Laravel, stancl/tenancy (central + tenant DBs), spatie permission/activitylog, Filament panels (`central`, `tenant`), Pest. Provisioning command + one seeded demo tenant.
2. Tenant schema for §5.1–5.7; enums; factories; seeders.
3. **StatusEngine** service + `RecomputeAttendanceDay` job + the full §7.6 test suite. Get this green before any screen.
4. Employees, shifts, overrides, holidays, manual entry (single + bulk) in the tenant panel.
5. Reports R1–R5, R10; Excel/PDF exports; dashboard.
6. Ingestion pipeline (`IngestPunchAction`, dedupe, unmapped PINs) with fixture-driven tests; USB import UI.
7. ADMS receiver on the central domain; device registry; device page with setup guides.
8. Bridge agent (`/agent`) + agent API.
9. Leave module + corrections + approval chains + periods/lock.
10. Notifications framework + email driver + events.
11. Employee PWA, kiosk (PIN/QR), mobile punch + geofence.
12. WhatsApp/SMS drivers; payroll-lite; visitors.
13. Rosters, analytics, AI features, public API/webhooks, billing, white-label.

Definition of done for every step: migrations + tests + a short entry in `/docs/CHANGELOG.md` + any new setting documented in `/docs/settings.md`.
