---
title: Payroll overview
group: Payroll
order: 10
summary: How salaries are worked out from attendance, and the steps to follow at the end of every month.
menu: Payroll
panel: 
screens: payroll-overview-runs=/admin/payroll-runs
---

## In short

- {app} works out each salary from the employee's **salary structure** and the attendance of a **locked month**.
- The month-end order is always: lock the month → run payroll → check the draft → finalise → pay the bank → print reports.
- A payroll run stays a **draft** until you finalise it. You can recalculate a draft as often as you like.
- **Finalise & send payslips** cannot be undone: advances are recovered, payslip PDFs are made and staff are told their net pay.
- The Payroll menu appears only if your plan includes Payroll. Tax, EOBI, provident fund, gratuity, bonuses and final settlements are extra parts (Payroll Full).
- Tax, EOBI and social security figures change with each budget. Check them with your accountant every year.

## What payroll does

{app} already knows, for every day of the month, whether each person was present, absent, late, on leave or on a holiday (see [How attendance works](/help/how-attendance-works)). When you lock the month, these days are added up for each employee: days absent, unpaid leave, half days, lates, overtime and so on.

Payroll takes those totals and the employee's salary and makes a payslip:

| Part | Where it comes from |
|---|---|
| **Basic salary and allowances** | The employee's [salary structure](/help/salary-structures). |
| **Attendance deduction** | Absent days, unpaid leave, half days and late roll-ups from the locked month, multiplied by the per-day rate. |
| **Overtime** | Overtime hours from attendance, multiplied by the overtime rates on the salary structure. |
| **Fixed deductions** | Lines on the salary structure, for example a loan or a canteen charge. |
| **Advance recovery** | One instalment of each open [advance](/help/bonuses-and-advances). |
| **Bonuses and fines** | One-off amounts added for that month under **Bonuses & Adjustments** (Payroll Full). |
| **Income tax, EOBI, provident fund** | Worked out from the [Payroll rules](/help/payroll-rules) and tax slabs (Payroll Full). |

Net pay = all earnings − all deductions. The company's share of EOBI, social security and provident fund is shown on the payslip but is not taken from the employee.

## What needs which part of the plan

| Part | What you get |
|---|---|
| **Payroll Lite** | Salary structures, advances, payroll runs, salary sheet, payslips, bank file and bank letter, the Payroll rules page (per-day rate). |
| **Payroll Full** — each part can be switched on separately | Income tax (tax slabs), EOBI & social security, provident fund, gratuity, bonuses & one-off pay items, final settlements. |

If a menu item or a section on **Payroll rules** is missing, your plan does not include that part. Ask the account owner to look at **Billing**.

Only the **Owner**, **Admin** and **HR** roles can see the Payroll menu. Managers, reception and employees cannot.

## Before your first payroll

1. Add a **salary structure** for every employee who is paid through {app}. People without one are skipped (the run shows them under **No salary set**).
2. Check the deduction settings under **System → Company settings → Deductions & overtime**: how many lates make one absent, what a half day and an absent day deduct, and whether daily-wage staff are paid for holidays. See [Company settings](/help/company-settings).
3. On **Payroll → Payroll rules**, choose how the per-day rate is worked out, and (with Payroll Full) check the tax, EOBI, social security, provident fund and gratuity figures.
4. On each employee's profile, under **Payroll & statutory**, switch on **EOBI registered**, **PESSI / SESSI registered**, **Provident fund member** or **No income tax** where they apply. See [Employees](/help/employees).
5. With income tax on, make sure **Payroll → Tax Slabs** has rates for the current tax year (July to June).

## The month-end steps

![The Payroll runs page with a draft and a final run](payroll-overview-runs.jpg)

1. **Fix the attendance.** Clear missing punches, approve leave and corrections. Look at the month under **Attendance → Months (lock)** → **View**.
2. **Lock the month.** On **Attendance → Months (lock)**, click **Lock** on the month. A month can be locked only after its last day. See [Month lock](/help/month-lock).
3. **Add this month's extras.** Bonuses, arrears or fines under **Payroll → Bonuses & Adjustments**; new advances under **Payroll → Advances**.
4. **Run payroll.** On **Payroll → Payroll runs**, click **Run payroll**, pick the locked month and confirm. A **draft** run appears. See [Payroll runs](/help/payroll-runs).
5. **Check the draft.** Open the row menu (⋮) → **Salary sheet (Excel)** or **Salary sheet (PDF)** and check every line. Look at **No salary set** and the **Tax rates** warning. If you change anything (a salary, a bonus, a rule), click **Recalculate**.
6. **Finalise.** Row menu → **Finalise & send payslips**. Advances go down by one instalment, payslip PDFs are saved and each employee is told their net pay (by email or WhatsApp, where set up).
7. **Pay the bank.** Row menu → **Bank transfer CSV** for online banking, or **Bank letter (PDF)** to sign and send to your bank.
8. **Print the reports.** Salary sheet, tax register, EOBI / social security challan and provident fund statement are under **Reports**. See [Payroll reports](/help/payroll-reports).

> **Tip:** Lock and run payroll for one month before you start the next one. Tax for each month depends on the earlier payslips of the same tax year.

## When an employee leaves

Use [Final settlements](/help/final-settlements) (Payroll Full) to pay the last days' salary, leave encashment, gratuity and provident fund, and recover open advances.

## A note on statutory figures

{app} comes with the tax slabs for 2025-26 and 2026-27 and with common EOBI, social security and provident fund rates. These are a starting point, not advice. Rates and the minimum wage change in the federal and provincial budgets each June. Ask your accountant or tax adviser to check **Payroll rules** and **Tax Slabs** every year, and before your first payroll.

## Common problems

### There is no Payroll menu

Your plan does not include Payroll, or your role is not Owner, Admin or HR. Ask the account owner.

### "Lock the month before running payroll."

Payroll only works on a locked month, so the numbers cannot change under it. Lock the month under **Attendance → Months (lock)**, then run payroll again.

### My month is not in the "Locked month" list

Only locked months are listed. A month can be locked only after it has ended, for example the September month on or after 1 Oct 2026 (or later if your attendance month starts on another day).

### Some employees are missing from the run

They have no salary structure that starts on or before the last day of the month. The run counts them under **No salary set**. Add a structure, then click **Recalculate**.

### I need to change a payroll that is already final

A final run cannot be changed or recalculated. Put the difference into next month as a bonus or a deduction under **Bonuses & Adjustments**, with a clear label such as "Arrears for September".
