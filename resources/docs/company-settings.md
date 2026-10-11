---
title: Company settings
group: Company setup
order: 10
summary: The company-wide rules: when your month and leave year start, how punches are read, what lates and absents deduct, who approves, and when alerts go out.
menu: System → Company settings
panel: company-settings
screens: company-settings=/admin/company-settings
---

## In short

- One page holds the rules for the whole company. Only the owner and admins can open it.
- **Attendance month starts on day** sets your pay month (for example 21 = 21st to 20th). **Leave year starts in** sets the leave year.
- **Deductions & overtime** decides what lates, half days, absents and unpaid leave cost, and whether they come from salary or from leave.
- **Leave approval** and **Correction approval** set who approves: *Manager, then HR*, *HR only* or *Manager only*.
- Click **Save settings** at the bottom. New rules apply to days worked out from now on; click **Recompute this month** to apply them to this month's days too.

## What you see on this page

![Company settings, with the Pay month, Punches, Deductions and Approvals sections](company-settings.jpg)

The page has four sections. Change what you need, then click **Save settings** (the bar stays at the bottom of the screen).

### Pay month & leave year

| Field | What it does |
|---|---|
| **Attendance month starts on day** | 1 to 28. 1 = calendar month. 21 = the *June* month runs 21 May → 20 June. Reports, month lock and payroll all use this month. |
| **Leave year starts in** | The month the leave year begins. January = calendar year. July = leave year July → June (financial year). Leave balances restart in this month. |

> **Tip:** Set these two before your first month lock and payroll. Changing them later moves the month and leave year boundaries for everything after.

### Punches

| Field | What it does |
|---|---|
| **Double-tap window** (sec) | Punches closer than this to the one before are treated as one (someone pressing twice). Default 60 seconds. |
| **Trust In/Out keys on machines** | On: use the In or Out key the employee pressed on the machine. Off (default): {app} decides in and out from the order of punches. Leave it off if staff forget to press the keys. |
| **Punches count from … before shift** (min) | How early a punch can be and still belong to that day's shift. Default 240 (4 hours). |
| **… until after shift end** (min) | How late after the shift end a punch still belongs to that day. Default 360 (6 hours). Important for night shifts. |
| **Store punch photos** | Keep the photos that machines, kiosks and phones take at a punch. |
| **Keep photos** (days) | Punch photos older than this are deleted every night (the punch itself stays). Default 90. 0 or empty keeps photos forever. |
| **Default geofence** (m) | For phone punching: how far from the branch location a punch is still accepted, when the branch has no distance of its own. Default 150 m. |
| **Reject mobile GPS worse than (0 = off)** (m) | A phone punch with a poor location fix is refused. 0 = no check. |
| **Selfie required for mobile punch** | Staff must take a photo when punching from the phone. |
| **Face check on PIN, QR card and phone punches** | Appears only if your plan includes face recognition on the kiosk. The punch photo is compared with the employee's enrolled face. Wrong face, no face or not enrolled → the punch waits in **Attendance → Punch approvals**. |
| **Allow future manual punch** | Lets HR enter a manual punch for a date after today. Off by default; then {app} says *"Manual punches cannot be entered for future dates."* |

### Deductions & overtime

| Field | What it does |
|---|---|
| **Lates that make one absent (0 = off)** | For example 3: every 3 lates in the month count as one absent day. Default 3. |
| **Half day deducts** | How much of a day a half day costs, from 0 to 1. Default 0.5. |
| **Absent deducts (days)** | Days cut for one absent day. Default 1. Some companies use 2 for an absent without notice. |
| **Unpaid leave deducts (days)** | Days cut for one day of unpaid leave. Default 1. |
| **Mark absent after (days)** | A day with no punches becomes *absent* only after this many days, so late-arriving machine data is not marked absent too early. Default 1. |
| **Pay daily-wage staff for holidays** | On: daily-wage staff are paid for public holidays. |
| **Late roll-ups & half days are deducted from** | *Salary*, or *Leave balance (then salary when it runs out)*. |
| **Leave type charged** | Shown only with *Leave balance*: the paid leave type the days come off, for example Casual Leave. Required. |
| **Overtime enabled** | Master switch for overtime. Each employee must also be marked as allowed overtime on their profile. |
| **Round overtime down to** (min) | Overtime is rounded down to this. With 15: 50 minutes counts as 45. |

These numbers feed the month's deduction days, and from there the payroll. See [How attendance is worked out](/help/how-attendance-works) and [Payroll overview](/help/payroll-overview).

### Approvals & notifications

| Field | What it does |
|---|---|
| **Leave approval** | *Manager, then HR* (default), *HR only* or *Manager only*. If an employee has no manager with a login, the manager step is skipped. See [Leave requests](/help/leave-requests). |
| **Correction approval** | The same choice for attendance corrections. See [Corrections](/help/corrections). |
| **Tell managers about late arrivals** | When on, the employee's manager gets an in-app message the first time someone is counted late that day (owner, admins and HR when the employee has no manager). Once per person per day. Off by default. |
| **"Not in yet" alert at** | Time of day when managers are told who has not punched in and has no leave. Default 11:00. |
| **Daily summary to HR at** | Time HR gets the day's summary (present, late, absent, on leave). Default 18:00. |
| **WhatsApp/SMS quiet from** / **… until** | No WhatsApp or SMS is sent between these times (default 22:00 to 08:00). Messages wait and go out when the quiet time ends. Email and in-app messages are not held back. |

## How to change a rule

1. Open **System → Company settings**.
2. Change the field.
3. Click **Save settings**. You see *Settings saved*.
4. If the rule should also apply to days already worked out this month, click **Recompute this month** at the top right and confirm. You see *Recompute queued*; the days are worked out again in the background within a few minutes.

**Recompute this month** works on the current attendance month, from its first day to today. Locked months are never changed. The button is shown to roles that may recompute attendance (Owner, Admin, HR).

> **Tip:** Change rules at the start of a month, not in the middle. Then the whole month follows one rule and is easy to explain to staff.

## Common problems

### I can't find Company settings

Only the owner and admins can open it. HR cannot. Ask your owner to change the setting, or to give you the Admin role under **Users & roles**.

### I changed a rule but this month's report did not change

Rules apply to days worked out after you save. Click **Recompute this month**, wait a few minutes, and open the report again. If the month is locked, unlock it first under **Attendance → Months (lock)**.

### "The leave type charged field is required"

You picked *Leave balance (then salary when it runs out)* but no leave type. Pick a paid leave type in **Leave type charged**. Only active, paid leave types are listed.

### "The attendance month starts on day field must not be greater than 28"

The month can start on day 1 to 28 only, so every month has that day. Use 28 instead of 29, 30 or 31.

### Night-shift punches land on the wrong day

The punch windows are too short. Increase **… until after shift end** so a punch after midnight still belongs to the shift that started the evening before. Then click **Recompute this month**.

### WhatsApp messages arrive in the morning, not at night

That is the quiet time. Messages are held between **WhatsApp/SMS quiet from** and **… until**. Change the times if you want them earlier.
