---
title: How attendance works
group: Attendance
order: 10
summary: How {app} turns punches into a status for each day (Present, Late, Half day, Absent and so on), with worked examples.
menu: Attendance
panel:
screens:
---

## In short

- A **punch** is one record of an employee at the machine, kiosk or phone. {app} looks at all punches near the shift and works out one result per employee per day: a **status** (for example Present or Absent) and **flags** (for example Late or Left early).
- The day is worked out again automatically whenever something changes: a new punch, an approved leave, a correction, a new holiday or a shift change. You never type the status yourself.
- **Late** means the first punch came after the shift start plus the grace time. **Left early** means the last punch was more than the shift's *Early out before end* minutes (default 30) before the shift end.
- A day with no punches becomes **Absent** only after the day is over (normally by the next morning). Until then it has no status yet.
- Holidays, weekly offs and approved full-day leave come before punches. A **Change day status** correction comes before everything.
- Once a month is locked, its days never change again. See [Month lock](/help/month-lock).

## The words you will see

- **Shift**: the working hours of an employee, for example 09:00 to 17:00. Set under [Shifts](/help/shifts).
- **Grace**: minutes after the shift start in which an arrival still counts as on time.
- **Status**: the one-word result of the day. Every day has one status.
- **Flag**: an extra note on the day. A day can have several flags, for example Present + Late + Missing out punch.
- **Recompute**: {app} working the day out again from the start.

## Statuses

| Status | Code in reports | When |
|---|---|---|
| **Present** | P (L in some reports when also late) | The employee punched and the day is a working day. |
| **Half day** | HD | The employee punched, but came very late, or worked too few hours (see below). |
| **Absent** | A | A working day with no punches at all, after the day is over. |
| **Off day** | WO | The employee's weekly off (for example Sunday). |
| **Holiday** | H | A public or company holiday that applies to this employee. |
| **Paid leave** | LV | An approved full-day leave of a paid leave type. |
| **Unpaid leave** | UL | An approved full-day leave of an unpaid leave type. |
| **Half-day leave** | HL | An approved half-day leave (first or second half). |
| **Official duty** | OD | Approved official duty: bank, client visit, field work. Paid, not absent. |
| **Work from home** | WFH | Approved work from home. Paid, not absent. |
| **Exempted** | EX | No punches, but the whole day was excused for everyone affected (strike, flood, road closure). See [Exemptions](/help/exemptions). |
| **Not joined yet** | – | A date before the employee's joining date. |
| **Left** | – | A date after the employee's leaving date. |
| *(blank)* | | Today, or a day that is not over yet, with no punches so far. |

## Flags

| Flag | What it means |
|---|---|
| **Late** | The first punch was after the late limit. |
| **Left early** | The last punch was before the shift end, beyond the out grace. |
| **Missing out punch** | Only an in punch was found (one punch, or an odd number in *Every in/out pair* mode). |
| **Missing in punch** | Only an out punch was found. Also set on a half-day leave day with no punches. |
| **Auto-closed** | The missing out punch was filled in automatically by the shift rule (see *Missing punches* below). |
| **Manual punch** | At least one punch of the day was typed in by hand. |
| **Outside office area** | A phone punch was made outside the office geofence (the allowed area around the office). |
| **Overtime** | The employee worked overtime (only when overtime is switched on). |
| **Short leave** | The employee had an approved short leave of a few hours. The day is still judged on its punches. |
| **Sandwich rule applied** | An off day or holiday between two leave days was counted as leave. |
| **Changed by hand** | The status was set by a *Change day status* correction. |
| **Exempted** | A company exemption covers this day (late arrivals or the whole day excused). |
| **Unpaid holiday** | A holiday the company does not pay for. It is deducted like a day without pay. |
| **Worked on a leave day** | The employee punched on a full day of leave. Maybe the leave was not needed. |

## How the status is decided

{app} checks these questions in this order. The first "yes" decides the status.

1. Is the date before the joining date, or after the leaving date? Then **Not joined yet** or **Left**.
2. Is the day a sandwich day of a leave (an off day or holiday between two leave days)? Then **Paid leave** or **Unpaid leave**.
3. Is it a holiday for this employee? Then **Holiday**.
4. Is it the employee's weekly off? Then **Off day**.
5. Is there an approved **full-day** leave? Then **Paid leave** or **Unpaid leave**.
6. Is there an approved **Official duty** or **Work from home** request for the day? Then **Official duty** or **Work from home**.
7. Are there **no punches**?
   - If the day is not over yet, the status stays blank.
   - If there is an approved half-day leave, **Half-day leave**.
   - If the whole day is exempted, **Exempted**.
   - Otherwise **Absent**.
8. There are punches:
   - If there is an approved half-day leave, **Half-day leave**.
   - If the late minutes are more than the shift's *Half day if late by more than* limit, **Half day**.
   - If both in and out punches are there and the worked time is less than the shift's *Half day if worked less than* limit, **Half day**.
   - Otherwise **Present**.

Last of all, if a **Change day status** correction was approved for the day, that status replaces the result. See [Corrections](/help/corrections).

> **Tip:** Punches on a holiday or off day do not make the day Present. The day stays Holiday or Off day. If overtime is on, the hours worked count as overtime.

## Which punches belong to which day

{app} does not simply take the punches of the calendar date. It takes the punches near the shift:

- From **4 hours before the shift start** to **6 hours after the shift end**. These numbers are the defaults; change them in **System → Company settings** (*Punches count from … before shift* and *… until after shift end*).
- For a 09:00–17:00 shift, punches between 05:00 and 23:00 count for that day.
- For a **night shift**, for example 22:00–06:00 with *Ends the next day* on, punches from 18:00 on 15 Sep until 12:00 on 16 Sep all count for **15 Sep**. So the morning punch at 06:05 closes the night shift of the day before.

**Which shift?** The employee's shift for that date (from the employee profile or the [Roster](/help/roster)). If they have none, the company's default shift. Then any [Timing overrides](/help/timing-overrides) for that date are applied, for example shorter Ramadan hours. Weekly offs come from the shift; if the shift has none ticked, from the employee's branch.

**Double taps.** If an employee taps twice within 60 seconds, the second tap is ignored. It shows as *Duplicate* in the [Punch log](/help/punch-log). Change the time in **Company settings → Double-tap window**.

**In and out keys.** Most machines cannot be trusted to know in from out, so {app} does not need them: with the normal mode (*First in, last out*) the first punch of the day is the in time and the last punch is the out time. Turn on *Trust In/Out keys on machines* only if your staff press the keys correctly.

## Late arrival

Each shift has a **Grace in** time (default 15 minutes). A first punch up to the shift start plus grace is on time. A punch after that is **Late**.

The late minutes are counted **after the grace**, not from the shift start.

**Example.** Shift 09:00–17:00, grace 10 minutes.

| First punch | Result | Late minutes |
|---|---|---|
| 08:50 | Present | 0 |
| 09:10 | Present (still inside the grace) | 0 |
| 09:14 | Present, **Late** | 4 |
| 10:30 | Present, **Late** | 80 |
| 11:20 | **Half day**, Late (late minutes 130 are more than the 120-minute half-day limit) | 130 |

If the shift has a **Late after** value, that decides when a punch becomes late instead of the grace. The late minutes are still counted after the grace. Leave *Late after* blank to use the grace.

Late is not counted:

- on a holiday, off day or full-day leave,
- when the employee has an approved leave for the **first half** of the day,
- when an [exemption](/help/exemptions) covers the employee that day.

## Leaving early

Each shift has two settings for this:

- **Early out before end** (default 30 minutes): a last punch counts as **Left early** only when it is more than this many minutes before the shift end. Set it to 0 to use only the out grace.
- **Grace out** (default 5 minutes): the early minutes are counted from the shift end minus this grace.

**Example.** Shift ends 17:00, early out before 30, grace out 5. Out at 16:40 is fine (20 minutes early is within 30). Out at 16:20 is **Left early** by 35 minutes (40 minutes early, minus the 5-minute grace). With early out before set to 0, out at 16:50 would already be Left early by 5 minutes.

Left early is not counted when the out punch is missing, when it was auto-closed, or when the employee has leave for the **second half** of the day.

## Worked time and half day

**Worked time** = time from the first punch to the last punch, minus the shift's unpaid break.

**Example.** Shift 09:00–17:00 with a 60-minute break. In 09:00, out 17:00: 8 hours minus 1 hour = **7 hours worked**. That is also the expected time for this shift.

A day becomes **Half day** when either:

- the late minutes are more than **Half day if late by more than** (default 120 minutes), or
- the worked time is less than **Half day if worked less than** (default 240 minutes = 4 hours). This is the least worked time for a full day. This check needs both an in and an out punch.

**Example.** In 09:00, out 12:30: 3 hours 30 minutes minus 1 hour break = 2 hours 30 minutes worked. That is less than 4 hours, so the day is **Half day** (also flagged Left early).

Leave a half-day field blank on the shift to switch that rule off.

### Shifts with break punches

The shift setting **Punch mode** decides how more than two punches are read:

- **First in, last out** (default): the day runs from the first punch to the last. Punches in between are ignored. The unpaid break is subtracted.
- **Every in/out pair**: punches are paired in order (1st with 2nd, 3rd with 4th, and so on) and the pairs are added up. The time between pairs counts as break, and only the rest of the unpaid break is subtracted.

**Example (Every in/out pair).** Punches 09:00, 13:00, 13:45, 17:00. Pairs: 09:00–13:00 = 4 h, 13:45–17:00 = 3 h 15 min. Total 7 h 15 min. The 45-minute gap is part of the 60-minute break, so 15 more minutes come off: **7 hours worked**.

## Missing punches

If only one punch is found, the day is still **Present** (or Late), with the flag **Missing out punch** (or **Missing in punch** if the single punch was an out key). The worked time is 0 and the half-day hours check is skipped.

To fix it, add the missing punch with a [correction](/help/corrections) or [Manual entry](/help/manual-entry).

A shift can also close missing out punches by itself: fill **Auto-close a missing out after** on the shift (in minutes). After that many minutes from the in punch, {app} sets the out time to a full scheduled day and adds the flag **Auto-closed**. Leave it blank to never auto-close.

## Absent

A working day with no punches becomes **Absent** only when the day is over: after midnight, and after the shift end for a night shift. {app} re-checks the last few days every night, so you normally see Absent the next morning.

The company setting **Mark absent after (days)** can wait longer. With 2, a day becomes Absent only after one more full day, which gives time for late machine data.

## Overtime

Overtime is counted only when **Overtime enabled** is on in **Company settings** and the employee has **Overtime eligible** ticked on their profile.

- On a working day: overtime = worked time minus expected time, but only if it is more than the shift's **Overtime after** minutes. Once it is more, all of the extra time counts, not just the part beyond *Overtime after*.
- On a holiday, off day or full-day leave: all worked time is overtime.
- It is capped at the shift's **Max overtime per day** (if set), then rounded **down** to the company's *Round overtime down to* (default 15 minutes).

**Example.** Shift 09:00–17:00, 60-minute break (expected 7 hours), *Overtime after* 30 minutes, rounding 15 minutes.

| Out time | Worked | Extra | Overtime |
|---|---|---|---|
| 17:20 | 7 h 20 min | 20 min | 0 (not more than 30) |
| 17:50 | 7 h 50 min | 50 min | 45 min |
| 19:10 | 9 h 10 min | 2 h 10 min | 2 h 0 min |

## From days to the month

At the end of the month the days are added up (see [Month lock](/help/month-lock)). With the default company settings:

- each **Absent** day deducts 1 day,
- each **Half day** deducts half a day,
- every **3 lates** deduct 1 day (*Lates that make one absent*; 0 switches this off),
- unpaid leave and unpaid holidays deduct 1 day each.

Late and half-day deductions come from the salary, or from a leave balance first if the company chose that under *Late roll-ups & half days are deducted from*. Change these rules in **System → Company settings**.

## When days are worked out again

- **Automatically**, about half a minute after a change: new punch, approved or cancelled leave, approved correction, holiday, exemption, shift, roster or employee change (joining date, branch and so on).
- **Every night**, the last 3 days are worked out again for everyone (more if **Mark absent after (days)** is 3 or more: that many days plus one).
- **By hand**: **Recompute this month** at the top of **System → Company settings** works out the whole current month again. Use it after you change a company rule. Only people who can open Company settings (owner and admin) see this button. For an earlier month that is not locked yet, use **Recompute this month** on that month under **Attendance → Months (lock)** (owner, admin and HR).

Days in a **locked** month are never worked out again. A change for one employee (a punch, leave or an approved correction) is applied however old the day is, as long as its month is not locked. A change for the whole company or a branch (a holiday, a shift) reaches back about two months, or further, to the day after the last locked month.

## Common problems

### "The employee came on time but shows Late"

Check the shift the employee was on that day and its **Grace in** and **Late after** values. Also check for a [timing override](/help/timing-overrides) that changed the start time for that date. The employee may also have no shift, so the default shift was used.

### A night-shift employee shows two half days instead of one full night

The shift is probably missing **Ends the next day**. Without it, the morning punch is read as the next day. Open the shift, turn on *Ends the next day*, then use **Recompute this month** in Company settings.

### Today shows no status for people who have not come

That is normal. A day with no punches becomes Absent only after the day is over, normally the next morning.

### The employee punched but the day is Absent

The punch may be outside the search window (more than 4 hours before the shift or 6 hours after it), waiting in [Punch approvals](/help/punch-approvals), or under an unknown PIN (see [Unknown PINs](/help/unmapped-pins)). Look the employee up in the [Punch log](/help/punch-log) and check the **Status** column.

### "Missing out punch" on many days

The employees forget to punch out, or the machine missed them. Add the out punch with a [correction](/help/corrections), or set **Auto-close a missing out after** on the shift if you want {app} to assume a full day.

### I changed a company rule but old days did not change

Rule changes apply to new calculations. Click **Recompute this month** at the top of **System → Company settings**. Locked months stay as they are.

### Overtime is always 0

Check that **Overtime enabled** is on in Company settings, that the employee has **Overtime eligible** on their profile, and that the extra time is more than the shift's **Overtime after** value.
