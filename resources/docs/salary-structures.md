---
title: Salary structures
group: Payroll
order: 20
summary: Set each employee's basic salary, allowances, overtime rates, fixed deductions and bank account, and change them when pay changes.
menu: Payroll → Salary structures
panel: salary-structures
screens: salary-structures-list=/admin/salary-structures | salary-structures-form=/admin/salary-structures >> New salary structure
---

## In short

- A salary structure is what one employee earns each month: basic salary, allowances, overtime rates and fixed deductions.
- Every structure has an **Effective from** date. Payroll uses the latest structure that starts on or before the last day of the month.
- For a pay rise, add a **new** structure with a new date. Keep the old one: it shows the salary history.
- Daily-wage staff are paid **Per-day rate × days worked**; monthly staff are paid basic + allowances, minus attendance deductions.
- Mark allowances that are not taxed (for example medical) by switching **Taxable** off.
- The bank name and account here are used in the bank transfer file and bank letter.

This page appears only if your plan includes Payroll. Owner, Admin and HR can use it.

## What you see on this page

![Salary structures list](salary-structures-list.jpg)

| Column | What it means |
|---|---|
| **Code / Employee** | Whose salary this is. |
| **Effective from** | The date this salary starts. |
| **Basic** | Monthly basic salary. |
| **Per day rate** | Daily wage, if set. A dash means none. |

The newest dates are at the top. One employee can have several rows, one for each salary change. Use the search box to find an employee by code.

## Add a salary structure

1. Click **New salary structure**.
2. Pick the **Employee**.
3. Set **Effective from**: the first day this salary applies (it fills in the first day of this month).
4. Fill **Basic (monthly)**. For daily-wage staff you can leave it 0.
5. For daily-wage staff, fill **Per-day rate (daily wage)**.
6. Fill **OT rate / hour** and **Holiday OT rate / hour** if the employee is paid overtime. If you leave the holiday rate empty, the normal rate is used for holidays and weekly offs too.
7. Under **Allowances**, click to add a line for each allowance: **Name** (for example House rent), **Amount**, and **Taxable**. Switch **Taxable** off for allowances that are free of income tax.
8. Under **Fixed deductions (EOBI, loan…)**, add any amount taken every month: **Name** and **Amount**.
9. Fill **Bank name** and **Account / IBAN** if you pay by bank transfer.
10. Click **Create**.

![The New salary structure form](salary-structures-form.jpg)

All amounts are whole rupees.

## How the salary is worked out

**Monthly staff** (every employment type except *Daily wage*):

- Earnings = **Basic** + all **Allowances** + overtime.
- Per-day rate = (basic + allowances) ÷ the divisor chosen on [Payroll rules](/help/payroll-rules): days in the month, 30, or 26.
- Attendance deduction = deduction days × per-day rate. Deduction days come from the locked month: absent days, unpaid leave, half days and late roll-ups, as set in [Company settings](/help/company-settings).

Example: basic Rs 60,000 + house rent Rs 20,000 = Rs 80,000. In a 30-day month with the divisor "Days in the pay month", the per-day rate is Rs 2,667. With 2 absent days, the deduction is Rs 5,334.

**Daily-wage staff** (employment type *Daily wage* on the employee's profile):

- Wages = **Per-day rate** × payable days. Payable days are days present, on official duty, working from home or exempted, plus part of half days. Holidays are paid only if **Pay daily-wage staff for holidays** is on in Company settings.
- Absent days are not paid, so there is no separate attendance deduction.
- Basic and allowances are not used for daily-wage staff.

**Overtime**: hours of overtime on normal days × **OT rate / hour**, and hours on holidays and weekly offs × **Holiday OT rate / hour**. Overtime is counted only if overtime is switched on in Company settings and the employee is marked as overtime eligible.

## Change a salary

- **Pay rise or new allowance:** click **New salary structure**, pick the same employee and set **Effective from** to the date the change starts. The old structure stays for earlier months.
- **A mistake in the current structure:** click **Edit** on the row and correct it. If the month's payroll is still a draft, click **Recalculate** on the run afterwards.

> **Tip:** Payroll uses the structure in force on the **last day** of the month. If a rise starts on 15 Sep 2026, the full September salary uses the new amount. Pay the difference for the first half with a deduction under **Bonuses & Adjustments** if you need to be exact.

## EOBI as a fixed deduction

If your plan does not include EOBI & social security, you can add EOBI here as a fixed deduction named "EOBI". When EOBI & social security is on and the employee is marked **EOBI registered**, {app} works out EOBI itself and drops any fixed deduction with "EOBI" in its name, so it is not taken twice.

## Common problems

### An employee is not in the payroll run

There is no structure for them with **Effective from** on or before the last day of the month. Add one, or correct the date, then click **Recalculate** on the run.

### The new salary did not show in payroll

The run was calculated before you changed the structure, or the new **Effective from** date is after the month's last day. Check the date, then **Recalculate** the draft run. A final run cannot change.

### A daily-wage employee got Rs 0

Their **Per-day rate (daily wage)** is empty, or the employee's **Employment type** is *Daily wage* but they had no payable days. Fill the rate and recalculate.

### Tax is taken on the medical allowance

That allowance line has **Taxable** switched on. Edit the structure, switch it off, and recalculate the draft run.

### The bank file has no account number

The employee's structure has no **Account / IBAN**. Edit the current structure and fill it.
