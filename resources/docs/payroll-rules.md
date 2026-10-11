---
title: Payroll rules and tax slabs
group: Payroll
order: 50
summary: Set the per-day rate, income tax, EOBI, social security, provident fund and gratuity rules, and keep the income tax slabs up to date.
menu: Payroll → Payroll rules, Payroll → Tax Slabs
panel: payroll-rules | tax-slabs
screens: payroll-rules-page=/admin/payroll-rules | payroll-rules-tax-slabs=/admin/tax-slabs
---

## In short

- **Payroll rules** holds the rates {app} uses when it calculates payroll. Changes apply the next time payroll is calculated; final runs do not change.
- **Per-day rate = monthly salary ÷** sets how absent days are valued: days in the month, 30, or 26.
- Income tax is worked out from the **Tax Slabs** of the tax year (July to June), spread evenly over the months left in the year.
- {app} comes with the slabs for 2025-26 and 2026-27. After each budget, add the new year's slabs with **Copy slabs to a new tax year**.
- EOBI and social security are worked out on a fixed **wage base** (normally the minimum wage), only for employees marked as registered.
- These figures change with federal and provincial budgets. Check them with your accountant every year.

**Payroll rules** appears if your plan includes Payroll. Its Income tax, EOBI & social security, Provident fund and Gratuity sections appear only for the Payroll Full parts your plan includes. **Tax Slabs** appears only with the Income tax part. Owner, Admin and HR can use both pages.

## Payroll rules

![The Payroll rules page](payroll-rules-page.jpg)

Change the values and click **Save settings**. You see *"Payroll rules saved — They apply the next time payroll is calculated."* To apply them to a draft run, click **Recalculate** on it.

### Salary

| Field | What it does |
|---|---|
| **Per-day rate = monthly salary ÷** | *Days in the pay month* (default): 28 to 31, depending on the month. *30*: the same rate every month. *26 (working days)*: a higher per-day rate, so each absent day costs more. Used for attendance deductions of monthly staff. |

### Income tax (Payroll Full)

| Field | What it does |
|---|---|
| **Tax year starts in** | July for Pakistan. Leave it unless your accountant says otherwise. |
| **Surcharge on annual income above** | Only for tax year 2025-26 and earlier (default Rs 10,000,000). |
| **Surcharge (% of the tax)** | Only for tax year 2025-26 and earlier (default 9%). The surcharge was removed from tax year 2026-27, so these two fields do not affect 2026-27 payroll. |

### EOBI & social security (Payroll Full)

| Field | Default | What it does |
|---|---|---|
| **EOBI wage base** | Rs 37,000 | The wage EOBI is worked out on, normally the minimum wage. |
| **EOBI — employee** | 1% | Taken from the employee's pay. |
| **EOBI — company** | 5% | Paid by the company; shown on the payslip, not deducted. |
| **Social security (PESSI / SESSI) wage base** | Rs 37,000 | The wage social security is worked out on. |
| **Social security — company** | 6% | Paid by the company only. |

With the defaults, an EOBI-registered employee pays Rs 370 a month and the company Rs 1,850. Social security costs the company Rs 2,220 per registered employee. These apply only to employees with **EOBI registered** or **PESSI / SESSI registered** switched on in their profile.

> **Tip:** The minimum wage is usually raised in the budget. When it changes, update both wage bases here.

### Provident fund (Payroll Full)

| Field | Default | What it does |
|---|---|---|
| **Employee share** | 8.33% | Percent of basic salary, taken from the employee. |
| **Company share** | 8.33% | Percent of basic salary, added by the company. |

Only for employees marked **Provident fund member**, and not for daily-wage staff. Both shares build up the employee's provident fund balance, which is paid out in the [final settlement](/help/final-settlements).

### Gratuity (Payroll Full)

| Field | Default | What it does |
|---|---|---|
| **Based on** | Last basic salary | Or *Last gross salary* (basic + allowances). |
| **Days' pay per year** | 30 | Days of pay for each year of service. |
| **Minimum years of service** | 1 | Below this, no gratuity. |
| **Part year counts from (months)** | 6 | A part year of at least this many months counts as a full year. |

Gratuity = (salary ÷ 30) × days' pay per year × years of service. It is paid in the final settlement and shown in the **Gratuity liability** report.

## Tax Slabs

![The Tax Slabs page](payroll-rules-tax-slabs.jpg)

Each row is one band of annual taxable income for one tax year:

| Column | What it means |
|---|---|
| **Tax year** | For example *2026-27* = 1 July 2026 to 30 June 2027. |
| **Income above** | The band starts above this annual income. |
| **Up to** | The band ends here. *and above* means no upper limit. |
| **Fixed tax** | Tax on the income up to the start of the band. |
| **Rate** | Percent charged on the income above **Income above**. |

Use the **Tax year** filter to see one year at a time.

### The 2026-27 slabs that come with {app}

| Annual taxable income | Tax |
|---|---|
| Up to Rs 600,000 | 0 |
| Rs 600,000 – 1,200,000 | 1% of the amount above Rs 600,000 |
| Rs 1,200,000 – 2,200,000 | Rs 6,000 + 11% of the amount above Rs 1,200,000 |
| Rs 2,200,000 – 3,200,000 | Rs 116,000 + 20% of the amount above Rs 2,200,000 |
| Rs 3,200,000 – 4,100,000 | Rs 316,000 + 25% of the amount above Rs 3,200,000 |
| Rs 4,100,000 – 5,600,000 | Rs 541,000 + 29% of the amount above Rs 4,100,000 |
| Rs 5,600,000 – 7,000,000 | Rs 976,000 + 32% of the amount above Rs 5,600,000 |
| Above Rs 7,000,000 | Rs 1,424,000 + 35% of the amount above Rs 7,000,000 |

These follow the Finance Act 2026 as we understood it. Ask your tax adviser to confirm them.

### How monthly tax is worked out

Each month, {app}:

1. Adds up this month's **taxable pay**: basic, taxable allowances, overtime and taxable bonuses, minus the attendance deduction. Allowances and bonuses with **Taxable** off are left out.
2. Guesses the **income for the whole tax year**: taxable pay already paid this tax year + this month's taxable pay × the months left (including this one).
3. Works out the **annual tax** on that income from the slabs.
4. Takes off the tax already deducted this tax year, and divides the rest by the months left.

**Worked example (2026-27).** Ali's salary is basic Rs 100,000 + house rent Rs 40,000 (taxable) + medical Rs 10,000 (not taxable). He was not absent in July 2026.

- Taxable pay for July: Rs 100,000 + Rs 40,000 = **Rs 140,000**.
- Months left in the tax year, counting July: 12. Expected yearly income: Rs 140,000 × 12 = **Rs 1,680,000**.
- This is in the Rs 1,200,000 – 2,200,000 band: Rs 6,000 + 11% × (Rs 1,680,000 − Rs 1,200,000) = Rs 6,000 + Rs 52,800 = **Rs 58,800** for the year.
- No tax deducted yet, so July tax = Rs 58,800 ÷ 12 = **Rs 4,900**.

If his pay stays the same, every month's tax is Rs 4,900. If he is absent, gets a raise or a bonus, the yearly figure is worked out again and the tax for the remaining months goes up or down, so that by June the total is right.

Employees with **No income tax** switched on in their profile get no tax line.

### Add the rates for a new tax year

Do this each July, after the budget:

1. Click **Copy slabs to a new tax year**.
2. **Copy from** shows the latest year (for example 2026); **Into** shows the next year (2027). Click **Submit**.
3. You see *"Copied — now update the rates from the budget"*. Filter on the new year and **Edit** each band to match the new Finance Act. **Delete** bands that no longer exist; use **New tax slab** to add one.
4. If a payroll run for the new tax year is still a draft, click **Recalculate** on it.

> **Warning:** Copying into a year that already has slabs replaces that year's slabs.

To add a band by hand, click **New tax slab** and fill **Tax year** (the year it starts: *2026* = July 2026 – June 2027), **Annual income above**, **Up to** (blank = and above), **Fixed tax** and **Rate on the amount above**.

Until the new year's slabs are entered, payroll uses the latest earlier year and shows a warning on the run.

## Common problems

### "No 2026-27 rates: add them, then recalculate" on a payroll run

The tax year of that month has no slabs. Add them as shown above, then click **Recalculate** on the draft run.

### I changed a rule but the payslips did not change

Rules apply when payroll is calculated. Click **Recalculate** on the draft run. A final run never changes.

### There is no EOBI on an employee's payslip

The employee is not marked **EOBI registered** on their profile, or the EOBI & social security part is not in your plan. Switch it on under the employee's **Payroll & statutory** section and recalculate.

### EOBI is deducted twice

This should not happen when EOBI & social security is on: the calculated EOBI replaces a fixed deduction named "EOBI". If you see two lines, the fixed deduction has a different name (for example "Old age benefit"). Remove it from the [salary structure](/help/salary-structures).

### I can't see the Income tax or Gratuity section

That Payroll Full part is not in your plan. Ask the account owner to look at **Billing**.

### Tax looks wrong after a raise in the middle of the year

That is how spreading works: the extra tax for the whole year is shared over the months left, so months after a raise carry a bit more. The year's total will match the slabs.
