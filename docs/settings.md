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
| `mobile_max_accuracy_m` | 0 | Reject mobile punches whose GPS accuracy is worse than this (0 = off) |
| `mobile_require_selfie` | false | Mobile punch needs a selfie |
| `face_match_threshold` | 0.6 | Kiosk face recognition: minimum similarity (0..1, same formula as the Human library) |
| `face_match_margin` | 0.05 | …and how far ahead of the next closest person the best match must be |
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

## White-label branding (feature `white_label`)

Set on the client's **Branding** page or by the super-admin (Companies → Branding, which can also lock it).

| Key | Meaning |
|---|---|
| `app_name` | Product name the client sees (titles, /me app, kiosk, PDFs, emails, SMS sender). Default: company name |
| `assistant_name` | Name of the AI assistant. Default: "Ask AI" |
| `brand_logo` / `brand_icon` | data: URLs (PNG/JPEG/WebP ≤ 150 KB); the icon is served at `/app-icon` |
| `brand_color` | Panel primary colour, PWA theme colour, avatar colour |
| `email_from_name` | Sender name on emails (no "via …" suffix when white-labelled) |
| `branding_locked` | Super-admin only: the client cannot change the above |

Env `HAZRI_NEUTRAL_DOMAIN`: a domain without the platform name pointing at the same server; white-label clients are
told to CNAME to it and to use `adms.<it>` / `api.<it>` for machines and the sync agent. White-label only changes the
branding: those clients are invoiced, see their Billing page and are suspended for non-payment like everyone else.

## Payroll Full (features payroll_tax, payroll_social, payroll_pf, payroll_gratuity, payroll_adjustments, payroll_settlement)

Edited under Payroll → Payroll rules; tax slabs under Payroll → Tax slabs (pre-filled for tax year 2025-26 — verify with FBR).

| Key | Default | Meaning |
|---|---|---|
| `payroll_day_divisor` | calendar | Per-day rate = monthly salary ÷ days in the pay month, 30 or 26 |
| `tax_year_start_month` | 7 | Tax year July → June |
| `tax_surcharge_threshold` / `tax_surcharge_percent` | 10,000,000 / 9 | Surcharge on the tax above this annual income |
| `eobi_wage_base`, `eobi_employee_percent`, `eobi_employer_percent` | 37,000 / 1 / 5 | EOBI on the minimum wage, employees marked EOBI registered |
| `social_security_wage_base`, `social_security_percent` | 37,000 / 6 | PESSI / SESSI, company only, employees marked registered |
| `pf_employee_percent`, `pf_employer_percent` | 8.33 / 8.33 | Of basic, PF members; ledger written when payroll is finalised |
| `gratuity_basis`, `gratuity_days_per_year`, `gratuity_min_years`, `gratuity_part_year_months` | basic / 30 / 1 / 6 | Gratuity = basis ÷ 30 × days × years |

Monthly income tax = (annual tax on [taxable so far this tax year + this month × months left] − tax already deducted) ÷ months left.
Taxable = earnings marked taxable (allowances can be exempt) minus attendance deductions. Employer contributions are listed on
the payslip but not deducted.
