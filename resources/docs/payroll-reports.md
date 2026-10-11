---
title: Payroll reports
group: Reports
order: 50
summary: The payroll reports: the attendance numbers payroll uses, the salary sheet, income tax, EOBI and social security, provident fund and gratuity.
menu: Reports → Reports → Payroll
panel: reports/payroll-input | reports/salary-sheet | reports/tax-register | reports/statutory-challan | reports/pf-statement | reports/gratuity-liability
screens: payroll-reports-input=/admin/reports/payroll-input | payroll-reports-tax=/admin/reports/tax-register
---

## In short

- **Payroll input** shows the attendance numbers a payroll run uses (payable days, deductions, overtime). Check it before you run payroll.
- **Salary sheet**, **Income tax register** and **EOBI & social security challan** are made from a payroll run. Pick the run at the top.
- **Provident fund statement** and **Gratuity liability** show balances on a date you choose.
- A run that is not final is marked **DRAFT** in the title line. Do not file or pay from a draft.
- These reports need payroll permission (Owner, Admin, HR). Each one also needs that part of payroll in your plan.

## Which report needs what

| Report | Shown when your plan includes | Filters |
|---|---|---|
| **Payroll input** | Always (it is attendance) | **Month** + common filters |
| **Salary sheet** | Payroll Lite | **Payroll run** |
| **Income tax register** | Income tax (FBR slabs) | **Payroll run** |
| **EOBI & social security challan** | EOBI & social security | **Payroll run** |
| **Provident fund statement** | Provident fund | **As of** + common filters |
| **Gratuity liability** | Gratuity | **As of** + common filters |

The common filters (branch, department and so on) are explained in [Reports](/help/reports-overview). In the **Payroll run** list each run shows its month and status, for example *September 2026 (draft)* or *August 2026 (final)*. These reports start on the newest run; pick another one to see an older month.

## Payroll input

**Shows:** per employee for one month: **Calendar days**, **Present**, **Absent**, **Half days**, **Unpaid leave**, **Lates**, **Late deduction** (days deducted because of lates), **Charged to leave** (lates or half days taken from leave instead of salary), **Deduction days**, **Payable days** and **OT hours**.

![Payroll input report for last month](payroll-reports-input.jpg)

**Use it:** before you run payroll, to check that the numbers are right. It opens on **last month**.

The line above the table says **locked** or **NOT locked — provisional**. When the month is locked, the report shows the frozen numbers the payroll run will use. When it is not locked, it shows live numbers that can still change. Lock the month first; see [Month lock](/help/month-lock).

> **Tip:** A name marked *· daily wage* is paid only for the days worked, so fewer payable days is correct for them.

## Salary sheet

**Shows:** one line per employee in the run: **Code**, **Name**, **Gross**, then one column for each kind of deduction (for example *Attendance deduction*, *Income tax*, *EOBI*, *Advance*), **Total deductions** and **Net pay**. Under the table: total **Gross** and total **Net**.

**Use it:** for the owner's approval and for the accounts file. Payslips and bank files are made from the payroll run itself; see [Payroll runs](/help/payroll-runs).

## Income tax register

**Shows:** each employee who had income tax worked out in that run: **CNIC**, **NTN**, **Taxable income**, **Tax deducted**, **Taxable (year to date)**, **Tax (year to date)** and **Projected annual tax**. Under the table: the number of **Employees** and the total **Tax deducted**, for example *Rs 145,000*.

![Income tax register for a payroll run](payroll-reports-tax.jpg)

**Use it:** for the monthly withholding tax payment and the yearly statements. Tax slabs are set under **Payroll → Tax slabs**; see [Payroll rules](/help/payroll-rules).

## EOBI & social security challan

**Shows:** each employee with EOBI or social security in that run: **CNIC**, **EOBI no.**, **EOBI wage base**, **EOBI employee**, **EOBI company**, **EOBI total**, **Social security no.** and **Social security**. Under the table: **EOBI total** and **Social security** in rupees.

**Use it:** to fill in the challans and deposit the amounts each month.

## Provident fund statement

**Shows:** each provident fund member: **Employee share**, **Company share** and **Balance** up to the **As of** date (today by default). Under the table: **Fund total**.

**Use it:** for the fund's records, or when an employee asks for their balance. Only employees marked as provident fund members are listed.

## Gratuity liability

**Shows:** for each employee who has not left: **Joined**, **Years counted**, **Monthly basis** (the salary gratuity is worked out on) and **Gratuity** (what they would get if they left on the **As of** date). Under the table: **Total liability**.

**Use it:** for provisioning in the accounts, for example at year end. The rules (days per year, part years, minimum years) are set under **Payroll → Payroll rules**. For an employee who is actually leaving, use [Final settlements](/help/final-settlements).

## Common problems

### A payroll report is missing from the Reports page

You need payroll permission (Owner, Admin or HR), and your plan must include that part of payroll (for example Income tax for the tax register). Branch managers and managers never see payroll reports. See [Billing](/help/billing).

### "Choose the filters above" on Salary sheet

No **Payroll run** is chosen. The report starts on the newest run, so this usually means no payroll has been run yet, or the run was cleared from the list. Pick one in the list. If the list is empty, run payroll first; see [Payroll runs](/help/payroll-runs).

### The title line says "DRAFT"

The payroll run is not final yet, so amounts can still change. Check it, then finalise it under **Payroll → Payroll runs** before you pay or file.

### Payroll input says "NOT locked — provisional"

The month is not locked, so the numbers may still change. Lock it under **Attendance → Months (lock)**, then run payroll. See [Month lock](/help/month-lock).

### An employee is missing from the tax register or the challan

Only employees who had tax, EOBI or social security worked out in that run are listed. Check the employee's tax and EOBI details on their profile, and their salary structure. See [Salary structures](/help/salary-structures).

### An employee is missing from the Provident fund statement

Only provident fund members are listed. Mark the employee as a member on their profile.

### Gratuity shows 0 for an employee

They have not worked long enough yet (below the minimum years set in the payroll rules), or they have no salary structure to work the amount out from.
