---
title: Final settlements
group: Payroll
order: 60
summary: Work out the full and final payment for an employee who is leaving: last salary, leave encashment, gratuity, provident fund and advances owed.
menu: Payroll → Final Settlements
panel: final-settlements
screens: final-settlements-list=/admin/final-settlements | final-settlements-new=/admin/final-settlements >> New settlement
---

## In short

- A final settlement is the last payment to a leaving employee, often called "full and final".
- Click **New settlement**, pick the employee and the **Last working day**. {app} works out the amounts as a **draft**.
- It includes salary for the unpaid days of the last month, leave encashment, gratuity, the provident fund balance, minus advances still owed.
- You can add your own lines (notice pay, bonus, damages) with **Add / edit lines**.
- **Finalise** closes the employee's advances and pays out their provident fund. It cannot be undone. Then print the **PDF**.

This page appears only if your plan includes Payroll Full (final settlement). Owner, Admin and HR can use it.

## What you see on this page

![Final settlements list](final-settlements-list.jpg)

| Column | What it means |
|---|---|
| **Employee** | Code and name. |
| **Last working day** | The employee's last day at work. |
| **Net payable** | What the company pays, after deductions. |
| **Status** | *draft* (yellow) or *final* (green). |

Each employee can have only one settlement.

Row buttons:

- **Details** — shows every line and the net payable.
- **Add / edit lines** — add extra lines and notes (draft only).
- **Recalculate** — works the amounts out again, for example after you fixed attendance or a salary (draft only). Lines you added by hand are kept.
- **Finalise** — makes the settlement final (draft only).
- **PDF** — downloads the settlement letter to print and sign.

## Before you start

1. Mark the employee as left on their profile (the **Left on** date), and choose a leaving reason. See [Employees](/help/employees).
2. Make sure their attendance and leave up to the last day are correct.
3. Check they have a [salary structure](/help/salary-structures).

## Prepare a settlement

1. Click **New settlement**.
2. Pick the **Employee**. People who have left are at the top of the list, with their leaving date. The **Last working day** fills in from the employee's profile.
3. Check or set the **Last working day**. If the employee is not marked as left, you see *"This employee is not marked as left yet."* You can still go on.
4. Click **Submit**. You see *"Settlement calculated — review it, then finalise"*.

![The New settlement window](final-settlements-new.jpg)

If the employee already has a draft settlement, it is recalculated with the new date.

## What is included

| Line | How it is worked out |
|---|---|
| **Salary** for the last pay month | (Basic + allowances) ÷ days in the pay month × payable days up to the last working day. Daily-wage staff: per-day rate × days worked. Left out if that month's payroll run already has a payslip for the employee. |
| **Leave encashment** | For each leave type with **Paid out on leaving** switched on: days left in the balance × (basic + allowances) ÷ 30. See [Leave types](/help/leave-types). |
| **Gratuity** | If gratuity is in your plan: (salary ÷ 30) × days' pay per year × years of service, as set on [Payroll rules](/help/payroll-rules). Nothing below the minimum years. |
| **Provident fund balance** | If the employee is a provident fund member: their own share plus the company share built up so far. |
| **Outstanding advances / loans** | Deducted: everything still owed on active [advances](/help/bonuses-and-advances). |
| Your own lines | Anything you add with **Add / edit lines**. |

**Gratuity example:** last basic salary Rs 60,000, joined 1 Mar 2021, last working day 30 Sep 2026. That is 5 years and 7 months; 7 months is more than 6, so it counts as 6 years. Gratuity = Rs 60,000 ÷ 30 × 30 × 6 = **Rs 360,000**.

## Add notice pay, bonus or damages

1. Click **Add / edit lines** on the row.
2. Under **Extra lines (notice pay, bonus, damages…)**, add a line: **Label**, **Type** (*Pay* or *Deduct*) and **Amount**.
3. Add **Notes** if you like, for example how notice was served.
4. Click **Submit**. You see *"Settlement updated"* and the net payable changes.

## Finalise and print

1. Click **Details** and check every line.
2. Click **Finalise** and confirm the message *"Outstanding advances are closed and the provident fund balance is paid out. This cannot be undone."*
3. Click **PDF** to download the settlement. Print two copies: one for the employee to sign, one for your file.

After finalising, the employee's active advances are marked *settled* and their provident fund balance goes to zero.

> **Tip:** Run and finalise payroll for the employee's last month **or** include that salary in the settlement, not both. If the payslip was made before the settlement, the salary line is left out of the settlement. If you make the settlement first and run payroll for that month later, the employee also appears in the payroll run; check the salary sheet so the same days are not paid twice.

## Common problems

### "Add a salary structure for this employee first."

There is no salary structure in force on the last working day. Add one under **Payroll → Salary structures**, then try again.

### "This employee's settlement is already final."

A final settlement cannot be changed or made again. Pay anything extra through **Bonuses & Adjustments** in a payroll run, or outside {app}.

### There is no leave encashment line

None of the employee's leave types has **Paid out on leaving** switched on, or the balance is 0. Change the leave type under **Leave → Leave types**, then click **Recalculate**.

### There is no gratuity line

Gratuity is not in your plan, the **Joined on** date on the employee's profile is wrong, or their service is shorter than the **Minimum years of service** on Payroll rules.

### The salary line is missing

A payroll run for that month already has a payslip for this employee, so the salary was paid there. Check the salary sheet for that month.

### I can't see Final Settlements in the menu

Your plan does not include Payroll Full final settlements, or your role is not Owner, Admin or HR.
