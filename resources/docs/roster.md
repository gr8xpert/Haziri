---
title: Roster
group: Shifts & holidays
order: 20
summary: Plan who works which shift on each day of the week, or put a group on one shift for a whole month.
menu: Shifts & Holidays → Roster
panel: roster
screens: roster-week=/admin/roster | roster-generate=/admin/roster >> Generate monthly roster
---

## In short

- The roster is the list of who works which shift on which day. It shows one week: employees down the side, days across the top.
- A cell that says **Usual: …** means the employee works their normal shift. Pick another shift only for the days that are different.
- Nothing is saved until you click **Publish**. Publishing also recalculates attendance for those days.
- **Generate monthly roster** puts a whole group (everyone, a branch, a department, or everyone on one shift) on one shift for a full month. Day cells on the weekly roster still win over it.
- This page appears only if your plan includes *Rosters & shift swaps*. Only the Owner, Admin and HR can use it.

## What you see on this page

![The weekly roster: employees down the side, one drop-down per day](roster-week.jpg)

- **Week** — the arrows move one week back or forward. The week runs Monday to Sunday. Today's column is coloured.
- **Branch** — show **All branches** or one branch only.
- **Employee rows** — every employee who has not left, sorted by code.
- **Day cells** — a drop-down for each day. **Usual: Morning (09:00–17:00)** shows the shift they work if you change nothing. Highlighted cells differ from the employee's usual shift.
- **Copy last week**, **Publish**, **New shift** and **Generate monthly roster** buttons.

## Plan a week

1. Choose the week with the arrows, and a branch if you like.
2. In each cell that should be different, pick the shift. Leave the others on **Usual**.
3. To start from last week's plan, click **Copy last week**, then change what you need.
4. Click **Publish**. You see *Roster published*.

To undo a day, set the cell back to **Usual** and publish again.

> **Tip:** Publish before you move to another week or change the branch. Changes that are not published are lost.

**Copy last week** copies only the day cells you changed last week. It does not save anything; review the week and click **Publish**.

## Roster a whole month

1. Click **Generate monthly roster**.
2. **Month** — the attendance month. If your attendance month starts on, for example, the 21st (set in Company settings), the month runs from the 21st to the 20th.
3. **Shift** — the shift to use. Click **+** next to it to add a new shift quickly.
4. **Roster for** — *All employees*, *A branch*, *A department* or *Everyone now on a shift*. For the last three, choose **Which one**.
5. Confirm. {app} tells you how many employees were rostered for that month.

![The Generate monthly roster window](roster-generate.jpg)

Running it again for the same month replaces that month's roster; it does not add a second one. The monthly roster wins over the shift set on the employee's profile, but a day cell on the weekly roster still wins over it.

## Add a shift from the roster

**New shift** opens a short form: **Name**, **Code**, **Start time**, **End time**, **Ends the next day (night shift)** and **Unpaid break**. All other rules get their normal values. You can change them later under **Shifts & Holidays → Shifts**; see [Shifts](/help/shifts).

## What happens after you publish

- Each changed day is saved for that employee and date only.
- Attendance for past days (up to about two months back) is worked out again with the new shift. Future days use it when they come.
- Days in a locked month do not change. See [Month lock](/help/month-lock).

## Common problems

### I can't see Roster in the menu

Your plan does not include *Rosters & shift swaps*, or your role is not Owner, Admin or HR. Ask the account owner.

### My changes disappeared

You moved to another week or changed the branch before clicking **Publish**. Make the changes again and publish.

### "Choose who the roster is for."

You picked *A branch*, *A department* or *Everyone now on a shift* in **Roster for** but left **Which one** empty. Choose one and try again.

### "You cannot change rosters."

Your role does not have permission to manage shifts. Ask the Owner or HR.

### A past day still shows the old shift

The day is in a locked month, or older than about two months. Unlock the month first, then publish again.

### An employee is not in the list

Employees who have left are not shown. Check the branch filter, and check the employee's status under **People → Employees**.
