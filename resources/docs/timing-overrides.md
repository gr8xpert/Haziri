---
title: Timing overrides
group: Shifts & holidays
order: 40
summary: Change working hours or grace time for a period, such as Ramadan timings or a longer Friday prayer break, without editing your shifts.
menu: Shifts & Holidays → Timing overrides
panel: shift-overrides
screens: timing-overrides-list=/admin/shift-overrides | timing-overrides-new=/admin/shift-overrides >> New timing override
---

## In short

- A timing override changes some shift rules for a date range, for example *Ramadan 2027: 09:00–15:00, no break*. When the range ends, the normal shift comes back by itself.
- It can apply to the whole company, one branch, one department or everyone on one shift.
- Pick a **Weekday** to make it apply on that day only, for example Friday for a Jummah break.
- Fill in only the values that change. Blank fields keep the shift's own value.
- If two overrides cover the same day, the one with the higher **Priority** wins.
- Only the Owner, Admin and HR can use this page.

## What you see on this page

![Timing overrides list with name, dates, scope, weekday and times](timing-overrides-list.jpg)

| Column | What it means |
|---|---|
| **Name** | For example *Ramadan 2027*. |
| **From date / To date** | The first and last day the override is used. |
| **Scope** | Whole company, Branch, Department or Shift. |
| **Weekday** | Empty = every day in the range. Otherwise only that weekday. |
| **Start time / End time** | The new working hours, if changed. |
| **Priority** | Which override wins when two cover the same day. |

The newest dates are at the top.

## Add a timing override

1. Click **New timing override**.
2. **Name** — for example *Ramadan 2027*.
3. **Priority** — default 10. Higher wins.
4. **From date** and **To date**.
5. **Weekday** — leave on *Every day*, or pick one day (Friday for Jummah).
6. **Scope** — *Whole company*, *Branch*, *Department* or *Shift*. For the last three, choose which one in **Applies to**.
7. Under **Changes (leave blank to keep the shift value)**, fill in only what changes:
   **Start time**, **End time**, **Break min**, **Grace in min**, **Grace out min**, **Late after min**, **Half day if late after min**, **Half day if worked less than min**.
8. Click **Create**.

![The new timing override form](timing-overrides-new.jpg)

{app} then works out attendance again for the affected days that have already passed (up to about two months back). Days in a locked month do not change.

## Examples

**Ramadan hours for everyone**
Name *Ramadan 2027*, From 8 Feb 2027, To 9 Mar 2027, Every day, Whole company, Start time 09:00, End time 15:00, Break min 0.

**Friday prayer break**
Name *Jummah*, From 1 Jan 2026, To 31 Dec 2026, Weekday Friday, Whole company, Break min 90.

**Ramadan and Friday together**
Give the Friday override a higher priority (for example 20) than the Ramadan one (10). On a Friday in Ramadan, both apply: first Ramadan, then Friday on top, so Friday's break wins and Ramadan's hours stay.

> **Tip:** For a permanent change, edit the shift itself under [Shifts](/help/shifts). Use an override only for a period that ends.

## How it fits with shifts

{app} first finds the employee's shift for the day (see [Shifts](/help/shifts)), then lays every matching override on top, lowest priority first. A field you leave blank keeps the value from the shift (or from a lower-priority override).

An override with scope *Shift* applies to everyone whose shift that day is that shift.

## Common problems

### The override did not change anything

Check that the dates cover the day, the **Weekday** is right (or *Every day*), and the scope matches the employee's branch, department or shift. Also check you filled in at least one field under **Changes**.

### Two overrides fight each other

Both cover the same day and change the same field. The one with the higher **Priority** wins. Edit the priorities.

### Old days still show the old timing

Only days in the last two months are worked out again, and never days in a locked month. Unlock the month first; see [Month lock](/help/month-lock).

### I can't save: the To date is before the From date

**To date** must be the same as or after **From date**. Fix the dates.
