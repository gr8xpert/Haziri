# Tenant settings

Stored per tenant in the `settings` table (`key` → JSON `value`), seeded from `config('hazri.settings_defaults')`,
read through `App\Services\Settings\TenantSettings`. Feature flags are stored as `feature.{flag}`.

| Key | Default | Meaning |
|---|---|---|
| `dedupe_window_sec` | 60 | Punches closer than this to the previous one are double taps |
| `trust_device_direction` | false | Use the device's In/Out key; otherwise direction is inferred by pairing |
| `pay_cycle_start_day` | 1 | Day the attendance/payroll month starts. 21 → "June" runs 21 May → 20 June |
| `leave_year_start_month` | 1 | Month the leave year starts (10 = Oct → Sep). Balances are keyed by the start year |
| `lates_per_absent` | 3 | N lates = 1 deduction day (0 = off) |
| `late_adjustment_mode` | salary | `salary` or `leave`: where late roll-ups and half days are deducted |
| `late_adjustment_leave_type` | null | Leave type code charged in `leave` mode |
| `late_count_resets` | monthly | Period over which lates are counted |
| `half_day_counts_as` | 0.5 | Deduction per half day |
| `absent_deduction_days` | 1 | Deduction per absent day |
| `unpaid_leave_deduction_days` | 1 | Deduction per unpaid leave day |
| `overtime_enabled` | false | Master switch; employees also need `overtime_eligible` |
| `overtime_rounding_min` | 15 | Overtime is rounded **down** to this |
| `punch_search_window_before_min` | 240 | Punches this long before shift start still belong to the day |
| `punch_search_window_after_min` | 360 | …and this long after shift end |
| `auto_absent_after_days` | 1 | A day with no punches becomes ABSENT only after it's over (+N−1 days) |
| `store_punch_photos` | true | Keep ADMS / kiosk punch photos |
| `photo_retention_days` | 90 | Photo retention |
| `geofence_default_radius_m` | 150 | Mobile punch geofence when the branch has none |
| `allow_future_manual_punch` | false | |
| `employee_can_see_others` | false | |
| `leave_approval_chain` | `["manager","hr"]` | Approval steps; a missing manager is skipped |
| `correction_approval_chain` | `["manager","hr"]` | |
| `daily_wage_paid_holidays` | *(unset → false)* | Pay daily-wage staff for holidays |
| `daily_summary_at` / `absent_alert_at` | 18:00 / 11:00 | Notification times |
| `quiet_hours` | `["22:00","08:00"]` | No WhatsApp/SMS in this window (queued until it ends) |
| `notify_employee_late` | false | Tell managers about late arrivals |
| `notify.{event}` | per event | Channels for an event, e.g. `["mail","whatsapp"]` |
| `auto_shift_detection` | false | Nearest shift start to the first punch (±3 h) when no assignment |
| `payroll_day_divisor` | `calendar` | Monthly salary ÷ days in month, or a fixed number (30 / 26) |
| `payroll_bank_csv_columns` | `["account","name","amount","reference"]` | Also available: bank, code, cnic |
| `ai_monthly_token_budget` | 2000000 | AI stops for the month once reached |
| `whatsapp_provider` / `sms_provider` | log | `meta` / `http`; credentials on the "WhatsApp & SMS" page (encrypted) |
| `brand_logo` / `brand_color` / `email_from_name` | — | White-label (plan feature `white_label`) |
| `feature.*` | from plan | `machine_sync`, `kiosk`, `mobile_punch`, `leave`, `payroll_lite`, `visitors`, `ai_reports`, `whatsapp`, `public_api`, `white_label` |
