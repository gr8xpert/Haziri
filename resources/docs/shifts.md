---
title: Shifts
group: Shifts & holidays
order: 10
summary: Set the working hours, grace time, break, half-day and overtime rules that {app} uses to work out each day.
menu: Shifts & Holidays → Shifts
panel: shifts
screens: shifts-list=/admin/shifts | shifts-form=/admin/shifts/create
---

## In short

- A shift is a set of working hours and rules, for example *Morning 09:00–17:00 with 15 minutes grace*. {app} compares every punch with the employee's shift to decide present, late, early out, half day and overtime.
- Mark one shift as the **default**. Anyone without their own shift uses it.
- Give an employee a shift on their profile (**Shift**), with **Assign shift** on the employee list, on the [Roster](/help/roster), or by a [shift swap](/help/shift-swaps).
- When you change a shift, {app} works out the last 31 days again for everyone. Days in a locked month do not change.
- For short-term changes (Ramadan hours, Friday prayer break) do not edit the shift; add a [timing override](/help/timing-overrides).
- Only the Owner, Admin and HR can open this page.

## What you see on this page

![The Shifts list with code, name, start and end time, grace and break](shifts-list.jpg)

| Column | What it means |
|---|---|
| **Code / Name** | The short code and the full name of the shift. |
| **Start time / End time** | The working hours. |
| **Grace** | Minutes after the start time that still count as on time. |
| **Break** | Unpaid break minutes taken off the worked time. |
| **Night** | A tick means the shift ends the next day (for example 22:00–06:00). |
| **Default** | A tick means this shift is used for employees who have no shift of their own. |

Use **Edit** or **Delete** at the end of a row. **New shift** at the top right opens the form.

## Create or change a shift

1. Click **New shift** (or **Edit** on a row).
2. Fill in the form. The fields are explained below.
3. Click **Create** (or **Save changes**).

![The new shift form with the Timing, Late & early and Other sections](shifts-form.jpg)

### Timing

| Field | What it does |
|---|---|
| **Name** | For example *Morning* or *Night*. |
| **Code** | Filled in from the name; you can change it. It must be different from every other shift. |
| **Default shift** | Used for employees with no shift of their own. Only one shift can be the default: switching it on here switches it off on the others. |
| **Start time / End time** | The working hours. |
| **Ends the next day** | Switch on for a night shift that ends the next day. |
| **Unpaid break** | Unpaid break in minutes (default 60). It is taken off the worked time. |
| **Punch mode** | *First in, last out*: the day runs from the first to the last punch, and the break minutes are taken off. *Every in/out pair*: punches are paired (in, out, in, out); the time between pairs counts as break. Use this when staff punch out for lunch. |

### Late & early

| Field | Default | What it does |
|---|---|---|
| **Grace in** | 15 | Arrivals within this many minutes of the start time are on time. |
| **Late after** | blank | When the employee counts as late. Blank = same as grace. Late minutes are counted from the end of the grace time. |
| **Half day if late by more than** | 120 | If the employee is late by more than this, the day becomes a half day. |
| **Grace out** | 5 | Early-out minutes are counted from the end time minus this grace. |
| **Early out before end** | 30 | Leaving more than this many minutes before the end time is an early out. 0 = an early out starts right after the out grace. |
| **Half day if worked less than** | 240 | The least worked time for a full day. If the employee worked less than this (for example 4 hours), the day is a half day. |

### Other

| Field | What it does |
|---|---|
| **Auto-close a missing out after** | If someone forgets to punch out, {app} closes the day as a full shift after this many minutes from their first punch. Blank = never; the day stays *Missing out* until someone fixes it. |
| **Overtime after** | Extra time over the shift counts as overtime only when it is more than this. Once it is more, all of the extra time counts (not just the part beyond this). Blank = any extra time. |
| **Max overtime per day** | The most overtime one day can have. Blank = no limit. |
| **Weekly off days** | The days off for this shift, for example Sunday. Leave empty to use the branch weekly offs (set under **Organisation → Branches**). |

> **Tip:** Overtime is only counted when **Overtime** is switched on in **System → Company settings** and the employee is marked as overtime eligible. Any work on a weekly off or holiday counts as overtime.

## Example

Shift *Morning*: 09:00–17:00, grace 15, half day if late after 120, break 60.

- Ali punches in at 09:10 → on time.
- Ali punches in at 09:40 → late by 25 minutes (40 minus the 15 grace).
- Ali punches in at 11:30 → late by more than 120 minutes → half day.
- Ali punches 09:00 and 17:00 → worked 7 hours (8 hours minus the 60-minute break).
- Ali punches out at 16:40 → not an early out (20 minutes is within the 30 of *Early out before end*). Out at 16:20 → early out by 35 minutes (40 minus the 5 grace out).

See [How attendance works](/help/how-attendance-works) for the full rules.

## Which shift applies on a day

{app} looks for the employee's shift in this order:

1. A one-day shift on the [Roster](/help/roster) or from an approved [shift swap](/help/shift-swaps).
2. A monthly roster, or a shift given with **Assign shift** (the newest start date wins).
3. If **Auto shift detection** is on in Company settings and the employee has no shift: the shift whose start time is nearest their first punch (within 3 hours).
4. The default shift.

Then any [timing override](/help/timing-overrides) for that date is laid on top.

## Common problems

### "The code has already been taken"

Another shift already uses this code. A deleted shift still keeps its code. Choose a different code, for example *MOR2*.

### Staff show the wrong shift after I deleted a shift

People who were on the deleted shift now fall back to the default shift. Give them a new shift with **Assign shift** on the **Employees** list, or on the [Roster](/help/roster).

### Days show no late, no absent and no shift at all

There is no default shift, and the employee has no shift of their own. Edit one shift and switch on **Default shift**.

### I changed the grace time but old days did not change

Changing a shift recalculates only the last 31 days. Older days, and days in a locked month, stay as they were. To recalculate a locked month, unlock it under **Attendance → Months (lock)** first; see [Month lock](/help/month-lock).

### A night shift picks up the wrong punches

Check the **Start time** and **End time**. {app} looks for punches around the shift hours, and treats an end time earlier than the start time as the next day. Switch on **Ends the next day** for every night shift so the list and reports show it as a night shift.

### Staff who punch out for lunch show too few hours

With *First in, last out* the break minutes are always taken off the whole day. Change **Punch mode** to *Every in/out pair* so the real lunch time is used instead.
