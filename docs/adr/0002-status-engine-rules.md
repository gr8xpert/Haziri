# ADR 0002 — Status engine interpretations

Status: accepted (2026-09-30)

The spec (§7.3) leaves some details open. The engine (`App\Services\Attendance\Engine\StatusEngine`) resolves them as follows.
Each rule is covered by a test in `tests/Unit/StatusEngine/`.

## Design

- The engine is **pure**: `resolve(DayContext): DayResult`. No DB, no clock — `now` is passed in.
  Loading the context and writing `attendance_days` happen in separate classes, so the whole rule set is unit-testable.
- The loader passes the employee's valid punches around the date. The engine keeps only those inside
  `[shift start − punch_search_window_before_min, shift end + punch_search_window_after_min]`.
  This is what assigns a night-shift 06:05 punch to the previous night.

## Rules

| Topic | Decision |
|---|---|
| Pending days | A working day with no punches returns `status = null` (pending) until the day **and** the shift are over, plus `auto_absent_after_days − 1` days. Only then is it `ABSENT`. |
| Late | Flag `late` when arrival is more than `late_after_min` (fallback `grace_in_min`) after the start. `late_min = arrival − (start + grace_in)` (the spec's formula). |
| Half day by lateness | `late_min > half_day_if_late_after_min`. |
| Early out | Flag `early_out` when the last out is more than `max(grace_out, early_out_before_min)` before the end. `early_out_min = (end − grace_out) − last_out`. `early_out_before_min = 0` means only the out grace applies. |
| Break | First/last mode: `worked = span − break_min`. Paired mode: the time between pairs already counts as unpaid, so only the part of `break_min` not already taken as out-time is deducted. |
| Auto-close | Once `now > first_in + auto_close_missing_out_after_min`, set `last_out = first_in + expected + break`, so the day counts as exactly the scheduled time. Flags `missing_out` + `auto_closed` both stay (R6 shows both). No early-out on auto-closed days. |
| Missing out, not closed | Status `PRESENT` (the half-day-by-worked-time check is skipped); worked = 0 in first/last mode. |
| Lone "out" punch | Only possible when `trust_device_direction` is on: `missing_in`, `last_out` set, `first_in` null. |
| Double taps | Punches less than `dedupe_window_sec` after the previously kept punch are dropped (ingestion also marks them `duplicate`; the engine repeats this so it doesn't depend on it). |
| Overtime | Only if the employee is `overtime_eligible` **and** the tenant setting `overtime_enabled`. Working day: `worked − expected`, and only when that is > `overtime_after_min`. Holiday / weekly off: all worked time. Capped at `max_overtime_min`, rounded **down** to `overtime_rounding_min`. |
| Precedence | sandwich leave → holiday → weekly off → full-day leave → OD/WFH marker → punches. A holiday beats ordinary leave; a sandwiched off day becomes leave. |
| Half-day leave | First-half leave skips the late check; second-half leave skips the early-out check. With no punches → `HALF_DAY_LEAVE` + `missing_in`, and the period rollup deducts 0.5. |
| Late / early on non-working days | Not computed (holiday, weekly off, full leave). |
| Manual override | Replaces the status and adds the flag `manual_override`; the computed numbers are kept. |
| Exemptions | A company / branch / department exemption for a date (`attendance_exemptions`). **Late**: no late flag, no late minutes, so no half day by lateness. **Absent** (whole day): also no half day by short hours, and a working day with no punches becomes `EXEMPTED` (paid) instead of `ABSENT`. Both add the flag `exempted`. Holidays, weekly offs and leave still win. |

## Period rollup (`PeriodCalculator`)

- Calendar days exclude `NOT_JOINED`, `LEFT` and pending days.
- Deduction days = absent × `absent_deduction_days` + unpaid leave × `unpaid_leave_deduction_days`
  + half days × `half_day_counts_as` + unworked half-day leave × 0.5 + ⌊lates ÷ `lates_per_absent`⌋.
- Daily wage payable = present + OD + WFH + exempted + half days × (1 − `half_day_counts_as`) + worked half-day leaves × 0.5
  (+ holidays when `daily_wage_paid_holidays`). The late rollup is not applied to daily wage.
- "Leave adjustment" mode (`late_adjustment_mode` = `leave`): the late roll-up and half-day days are charged to the
  `late_adjustment_leave_type` balance (rounded down to 0.5) instead of salary; whatever the balance cannot cover stays a
  salary deduction (unlimited when the type allows a negative balance). Not for daily wage or late-exempt staff. Locking
  the month writes the charge as a leave adjustment; unlocking reverses it.
- The "month" is the pay cycle: with `pay_cycle_start_day` = 21, period June runs 21 May → 20 June.
