# Tenant settings

Stored per tenant in the `settings` table (`key` → JSON `value`), seeded from `config('hazri.settings_defaults')`,
read through `App\Services\Settings\TenantSettings`. Feature flags are stored as `feature.{flag}`.

| Key | Default | Meaning |
|---|---|---|
| `dedupe_window_sec` | 60 | Punches closer than this to the previous one are double taps |
| `trust_device_direction` | false | Use the device's In/Out key; otherwise direction is inferred by pairing |
| `lates_per_absent` | 3 | N lates = 1 deduction day (0 = off) |
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
| `feature.*` | from plan | `machine_sync`, `kiosk`, `mobile_punch`, `leave`, `payroll_lite`, `visitors`, `ai_reports`, `whatsapp` |
