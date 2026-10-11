---
title: Month lock
group: Attendance
order: 70
summary: Close each month once its attendance has been checked, so the numbers are final and payroll can run on them.
menu: Attendance → Months (lock)
panel: periods
screens: month-lock=/admin/periods
---

## In short

- Each month goes **Open → In review → Locked**. Locking makes the month final: nothing in it can change any more.
- You add a month to the list with **Add month**, then click **Review**, then **Lock** once the month is over and checked.
- Locking saves a summary of every employee's month (absents, lates, deduction days). [Payroll](/help/payroll-runs) can only run on a locked month.
- Owner, admin and HR can lock. Only the **owner or admin** can **Unlock**, and must give a reason.
- "Month" means your company's attendance month. If it starts on the 21st, the June month runs from 21 May to 20 June.

## The three stages

| Status | What it means |
|---|---|
| **Open** (grey) | The month is running or just ended. Punches are still coming in and days are worked out again whenever something changes. |
| **In review** (yellow) | HR is checking: fixing missing punches, deciding leave and correction requests, approving punches. Everything can still change. This stage is only a marker for your team; it does not block anything. |
| **Locked** (green) | Final. The days are frozen. |

**While a month is locked:**

- days in it are never worked out again, whatever changes,
- new leave requests, corrections and decisions for dates in it are refused with *"That month is locked"*,
- approved leave in it cannot be cancelled,
- bulk manual punches skip its days.

## What you see on this page

![Months (lock) list with each month's status and totals](month-lock.jpg)

| Column | What it means |
|---|---|
| **Month** | The month. If your attendance month does not start on the 1st, the dates are shown too, for example *June 2026 (21 May – 20 Jun)*. |
| **Status** | Open, In review or Locked. |
| **Locked at** | When it was locked. |
| **Deduction days** | Total days to be deducted for all employees (absents, half days, late roll-ups, unpaid leave), from the saved summary. |
| **Lates** | Total late arrivals in the month. |

Click a month, or **View**, to open the **Month summary** report: every employee's numbers for that month, with a link to each person's day-by-day sheet.

## How to close a month

1. Click **Add month** at the top right. Pick the **Month** (last month is chosen at first) and submit. Skip this if the month is already in the list.
2. Click **Review** on the month. This tells your team the month is being checked.
3. Check the month:
   - open **View** and look at absents, lates and missing punches,
   - clear [Punch approvals](/help/punch-approvals), pending [Corrections](/help/corrections) and pending [Leave requests](/help/leave-requests),
   - fix missing punches (see the **Missing punches** report in [Attendance reports](/help/attendance-reports)).
4. Click **Lock** and confirm.

The month must be over before you can lock it. After locking:

- {app} saves the summary (the totals you see in the list).
- If your company deducts lates and half days from leave (*Late roll-ups & half days are deducted from* in Company settings), the days are taken from each employee's chosen leave balance now. See [Leave balances](/help/leave-balances).
- The owner is told that the month was locked.
- If your plan includes the AI assistant, a short written **Summary** of the month in English and Urdu appears. Click **Summary** to read or edit it.

> **Tip:** Rule changes in Company settings never reach a locked month. Make rule changes before you lock, and use **Recompute this month** in Company settings while the month is still current.

## Unlock a month

Sometimes a mistake is found after locking.

1. Click **Unlock** on the locked month (owner and admin only).
2. Write the **Reason**. It is saved in the [Audit log](/help/audit-log).
3. If the month's payroll is already final, the window warns you that the final payslips will not change. Tick **I understand the final payroll for this month will not change** to go on.
4. Submit.

The month goes back to **In review**. Leave days that were charged for lates and half days at locking are given back. Make your fixes, then **Lock** again; a new summary is saved and the charges are made again.

> **Tip:** If the month's payroll run is still a draft, click **Recalculate** on it after you lock again. A final run never changes: put any difference into the next month under [Bonuses & Adjustments](/help/bonuses-and-advances). See [Payroll runs](/help/payroll-runs).

## Common problems

### "A month can only be locked after it has ended."

The last day of the month (in your attendance month) is today or later. Wait until the next day.

### "That month is locked" when approving leave or a correction

The date falls in a locked month. Ask the owner or admin to unlock it, make the change, then lock it again.

### "Lock the month before running payroll."

Payroll needs final numbers. Lock the month on this page first.

### "Only the owner can unlock a month."

Your role cannot unlock. Ask the owner or an admin.

### "Only an open month can move to review."

The month is already in review or locked. You can lock it straight away.

### I can't see Months (lock) in the menu

Only owner, admin and HR can use this page.

### A month I need is not in the list

Months are not added by themselves. Click **Add month** and pick it.
