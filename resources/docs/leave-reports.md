---
title: Leave reports
group: Reports
order: 40
summary: The three leave reports: every leave request in a date range, each person's balance for the leave year, and one leave type month by month.
menu: Reports → Reports → Leave
panel: reports/leave-register | reports/leave-balances | reports/monthly-leave-summary
screens: leave-reports-balances=/admin/reports/leave-balances | leave-reports-monthly-summary=/admin/reports/monthly-leave-summary
---

## In short

- **Leave register**: every leave request that touches a date range, with type, days, status and who approved it.
- **Leave balances**: each employee's balance for a leave year: quota, used, pending and left, for every active leave type.
- **Monthly leave summary**: one leave type for one month: brought forward, earned, taken, charged for lates and half days, without pay, and the closing balance.
- All three use the common filters and download to Excel and PDF. See [Reports](/help/reports-overview).
- To change a balance, use **Leave → Leave balances**, not the report. See [Leave balances](/help/leave-balances).
- These reports appear only if your plan includes *Leave management*.

## Leave register

**Shows:** every leave request whose dates overlap the chosen range, in date order. Requests of every status are listed: pending, approved, rejected and cancelled.

| Column | What it means |
|---|---|
| **Code** / **Name** | The employee. |
| **Type** | The leave type code, for example CL or SL. |
| **From** / **To** | The first and last day of the leave. |
| **Part** | Full day, or first / second half. |
| **Days** | Days charged, including any days added by the sandwich rule (a weekly off or holiday between two leave days that is counted as leave). |
| **Status** | Pending, Approved, Rejected or Cancelled. |
| **Approvals** | Each decision so far, for example *manager: approved by Sana Ahmed; hr: approved by Bilal Khan*. |
| **Reason** | What the employee wrote. |

**Use it:** to see who was on leave in a period, or to check who approved a leave.

**Filters:** **From**, **To**, the common filters and **Leave type**.

> **Tip:** To see only approved leave, filter the table in Excel by **Status**, or use the filters on **Leave → Leave requests**. See [Leave requests](/help/leave-requests).

## Leave balances

**Shows:** one line per employee. For each active leave type there are four columns, named with the type code:

| Column | What it means |
|---|---|
| **CL quota** | Days available this leave year: opening balance (carried forward) + days earned + adjustments. |
| **CL used** | Days already taken (approved leave). |
| **CL pending** | Days in requests still waiting for approval. |
| **CL left** | What the employee can still take. |

![Leave balances report with quota, used, pending and left for each leave type](leave-reports-balances.jpg)

**Use it:** at the end of the leave year, or when the owner asks how much leave people have left.

**Filters:** **Leave year** (this year by default; you can choose two years back or the next year) and the common filters. If your leave year does not start in January, it is shown like *2026-27*.

Leave types that are switched off are not shown.

## Monthly leave summary

**Shows:** one leave type for one month, per employee, like the leave sheet many HR offices keep by hand.

| Column | What it means |
|---|---|
| **Payroll ID**, **Emp #**, **Name**, **Designation**, **D.O.J** | The employee and date of joining. |
| **Prev month bal** | Balance brought forward from last month. |
| **Allotted** | Days earned this month. |
| **Total** | Prev month bal + Allotted. |
| **Availed** | Leave taken this month. |
| **Adj (late / half day)** | Days taken off this leave by adjustments in the month, mostly lates and half days charged to leave (if your company does that). |
| **Total deducted** | Availed + Adj. |
| **Credit leave** | Days added to this leave by adjustments in the month. |
| **LWP** | Leave without pay: days in the month marked as unpaid leave (of any type). |
| **Cur month bal** | The closing balance. |

![Monthly leave summary for one leave type](leave-reports-monthly-summary.jpg)

**Use it:** at month end, together with the [Monthly grid](/help/attendance-reports), to see how lates and half days were charged to leave.

**Filters:** **Month**, **Leave type** (required) and the common filters. The leave type starts on the type your company charges lates to (set in [Company settings](/help/company-settings)), or else the first paid leave type. People who joined after the month are left out.

The same leave numbers also appear at the bottom of each employee's [monthly report](/help/monthly-attendance-report).

## Who can see these reports

Owner, Admin, HR and Branch manager (their branches only) can view and download. Managers can view them for the people who report to them, without downloads.

## Common problems

### "Choose the filters above" on Monthly leave summary

No **Leave type** is chosen, or no month. Pick both. If the list of leave types is empty, no leave type is active; see [Leave types](/help/leave-types).

### A leave type is missing from Leave balances

Only active leave types are shown. Switch the type on under **Leave → Leave types**, or check that balances exist for that leave year.

### The balance in the report is wrong

The report only shows the stored balance. Open **Leave → Leave balances** to see how it was worked out and to add an adjustment. See [Leave balances](/help/leave-balances).

### Days in the Leave register are more than the dates

The sandwich rule added the weekly offs or holidays between leave days. This is set on the leave type; see [Leave types](/help/leave-types).

### A rejected or cancelled leave shows in the Leave register

The register lists every request, whatever its status, so you have the full history. Look at the **Status** column.

### "LWP" shows days for an employee

The employee had days marked as unpaid leave that month, for example an unpaid leave request. LWP counts all unpaid leave days in the month, not only this leave type. Those days are not paid. See [How attendance works](/help/how-attendance-works).
