---
title: Payroll runs
group: Payroll
order: 40
summary: Calculate a month's salaries from a locked month, check the draft, finalise it, and download the salary sheet and bank files.
menu: Payroll → Payroll runs
panel: payroll-runs
screens: payroll-runs-list=/admin/payroll-runs | payroll-runs-new=/admin/payroll-runs >> Run payroll
---

## In short

- A payroll run is one month's salaries for everyone. There is one run per month.
- Click **Run payroll** and pick a **Locked month**. The run starts as a **draft**.
- A draft can be checked and **Recalculate**d as often as needed, for example after a salary or bonus change.
- **Finalise & send payslips** makes the run final: advances are recovered, payslip PDFs are saved and staff are told their net pay. It cannot be undone.
- From the row menu (⋮) you can download the **Salary sheet**, a **Bank transfer CSV** and a **Bank letter (PDF)**.

This page appears only if your plan includes Payroll. Owner, Admin and HR can use it.

## What you see on this page

![Payroll runs list](payroll-runs-list.jpg)

| Column | What it means |
|---|---|
| **Month** | The attendance month the run is for. |
| **Status** | *draft* (yellow) or *final* (green). |
| **Employees** | How many payslips were made. |
| **Gross** | Total earnings of everyone. |
| **Deductions** | Total deductions (attendance, advances, tax, EOBI, provident fund and others). |
| **Net** | Total to pay: gross − deductions. |
| **No salary set** | Employees in the month who have no salary structure. They get no payslip. |
| **Paid in settlement** | Leavers whose [final settlement](/help/final-settlements) already pays this month's salary. They get no payslip, so the days are not paid twice. |
| **Tax rates** | A red warning such as *"No 2026-27 rates: add them, then recalculate"* when that tax year has no tax slabs yet. |

## Run payroll for a month

Before you start, the month must be locked under **Attendance → Months (lock)**. See [Month lock](/help/month-lock).

1. Click **Run payroll** at the top right.
2. Pick the month under **Locked month**. Only locked months are listed.
3. Click **Submit**. You see *"Payroll calculated (draft)"* and the run appears in the list.

![The Run payroll window](payroll-runs-new.jpg)

If the month already has a draft run, **Run payroll** recalculates it. Nothing is duplicated.

Who is included: every employee who has attendance in the locked month and has a salary structure in force on the last day of the month. This includes people who joined or left during the month: monthly staff are paid only for the days they were employed (a **Not employed** line takes off the days before joining or after leaving). Check their lines on the salary sheet.

## Check the draft

Open the row menu (⋮) and download **Salary sheet (Excel)** or **Salary sheet (PDF)**. The sheet is marked *DRAFT* until the run is final. Check:

- The **No salary set** count. Add the missing [salary structures](/help/salary-structures).
- Attendance deductions that look too high. The days come from the locked month; see the **Month summary** under **Attendance → Months (lock)** → **View**.
- Bonuses, fines and advances for the month.
- The **Tax rates** warning, if income tax is on.

If you change anything, open the row menu and click **Recalculate**. You see *"Recalculated"*.

> **Tip:** If the attendance itself is wrong, the owner has to unlock the month, fix it, and lock it again. Then click **Recalculate** on the draft run.

## Finalise and send payslips

1. Open the row menu (⋮) and click **Finalise & send payslips**.
2. Read the message *"Advances are recovered, payslip PDFs generated and employees notified. This cannot be undone."* and confirm.

What happens:

- Each open advance goes down by one instalment; advances paid in full become *settled*.
- A payslip PDF is made for each employee and kept by {app}.
- Provident fund contributions are added to each member's provident fund balance (Payroll Full).
- Each employee gets a *"Payslip for …"* message with their net pay, by email or WhatsApp where set up (see [Notifications](/help/notifications)).
- The status changes to *final*. **Recalculate** and **Finalise** disappear from the menu.

## Pay the bank

From the row menu (⋮):

- **Bank transfer CSV** — a spreadsheet file for your bank's bulk transfer upload. Columns: account, name, amount, reference ("Salary" and the month). Only employees with a net pay above Rs 0 are listed.
- **Bank letter (PDF)** — a letter to your bank asking it to transfer each salary. You can type the **Bank / branch** name, for example "HBL, Main Boulevard branch". Print it, sign it and send it.

Both use the **Bank name** and **Account / IBAN** from the salary structure in force on the last day of the run's month, the same one the pay comes from. A newer structure (for example with a new bank account) is used from its own **Effective from** month.

## Reports

The salary sheet, tax register, EOBI and social security challan, provident fund statement and gratuity liability are under **Reports**. See [Payroll reports](/help/payroll-reports).

## Common problems

### "Lock the month before running payroll."

The month is not locked. Lock it under **Attendance → Months (lock)**, then click **Run payroll** again.

### "Payroll for this month is already final."

That month already has a final run, which cannot be changed. Make corrections in the next month with **Bonuses & Adjustments**.

### "No 2026-27 rates: add them, then recalculate"

The tax year of this month has no income tax slabs, so {app} used the latest earlier year's rates. Add the new rates under **Payroll → Tax Slabs** (or copy the old year and update it), then click **Recalculate**. See [Payroll rules](/help/payroll-rules).

### Some employees have no payslip

They are counted under **No salary set**: they have no salary structure in force on the last day of the month. Add one and recalculate. Leavers counted under **Paid in settlement** get their last salary in the final settlement instead. Employees with no attendance at all in the month are not included either.

### An employee's overtime is missing

Overtime is paid only when overtime is on in **Company settings**, the employee is marked overtime eligible, and the salary structure has an **OT rate / hour**. Fix it and recalculate the draft.

### "You cannot run payroll."

Your role does not include payroll. Only the Owner, Admin and HR can run it.
