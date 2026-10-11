---
title: Bonuses and advances
group: Payroll
order: 30
summary: Add one-off bonuses, arrears and fines to a month's payroll, and give salary advances that are paid back in instalments.
menu: Payroll → Bonuses & Adjustments, Payroll → Advances
panel: pay-items | advances
screens: bonuses-and-advances-pay-items=/admin/pay-items | bonuses-and-advances-advance-new=/admin/advances >> New advance
---

## In short

- **Bonuses & Adjustments** adds a one-off amount to one month's payslip: pay (bonus, arrears, allowance) or deduct (fine, recovery).
- Each bonus or adjustment belongs to one **Pay month**. It is picked up the next time that month's payroll is run or recalculated.
- **Advances** are money given to an employee before payday. They are taken back from salary in equal **Instalments** (1 to 60 months).
- An advance instalment is taken in each payroll run, and the amount still owed goes down when the run is **finalised**.
- When an employee leaves, any advance still owed is deducted in their [final settlement](/help/final-settlements).

**Advances** appears if your plan includes Payroll. **Bonuses & Adjustments** appears only if your plan includes Payroll Full (bonuses, arrears and one-off pay items). Owner, Admin and HR can use both pages.

## Bonuses & Adjustments

![Bonuses and adjustments list](bonuses-and-advances-pay-items.jpg)

| Column | What it means |
|---|---|
| **Employee** | Code and name. |
| **Pay month** | The payroll month this amount goes into. |
| **Type** | *earning* (green): added to pay. *deduction* (red): taken from pay. |
| **Label** | The text printed on the payslip, for example "Eid bonus". |
| **Amount** | In rupees. |
| **Taxable** | A tick means the amount counts towards income tax. |

Use the **filters** button to show only **Pay** or only **Deduct** items.

### Add a bonus, arrears or a fine

1. Click **New bonus / adjustment**.
2. Pick the **Employee**.
3. Check **Pay month**. It fills in the current pay month; change it if the amount belongs to another month.
4. Choose **Type**: *Pay (bonus, arrears, allowance)* or *Deduct (fine, recovery)*.
5. Write a **Label**. This is what the employee sees on the payslip.
6. Enter the **Amount** (at least Rs 1).
7. Leave **Taxable** on for normal pay. Switch it off only for an amount that is free of income tax. **Taxable** has no effect on deductions.
8. Add a **Note** for your own records if you like, then click **Create**.

To change or remove an item, use **Edit** or **Delete** on its row.

> **Tip:** If the month's payroll run is already a draft, click **Recalculate** on the run after adding items. If the run is final, the item is not added any more. Put it into the next month instead.

> **Tip:** A taxable bonus raises that month's income tax: {app} adds the bonus to the yearly income once and takes the extra tax in the bonus month. The tax in the following months stays about the same. See [Payroll rules](/help/payroll-rules).

## Advances

| Column | What it means |
|---|---|
| **Employee** | Who got the advance. |
| **Issued on** | The date the money was given. |
| **Amount** | The full advance. |
| **Instalments** | How many months to pay it back over. |
| **Remaining** | How much is still owed. |
| **Status** | *active* (yellow) while money is owed; *settled* (green) when it is paid back. |

The list shows **active** advances first. Use the **Status** filter to see settled ones.

### Give an advance

1. Click **New advance**.
2. Pick the **Employee**.
3. Set **Issued on** (today by default).
4. Enter the **Amount**.
5. Enter **Instalments**: the number of months to recover it over, from 1 to 60. Use 1 to take it all back next payday.
6. Add a **Note** if you like, for example "Medical emergency", and click **Create**.

![The New advance form](bonuses-and-advances-advance-new.jpg)

### How an advance is paid back

- Each instalment = amount ÷ instalments, rounded up to the next rupee. The last instalment is only what is left.
- Example: an advance of Rs 50,000 over 3 instalments is recovered as Rs 16,667, Rs 16,667 and Rs 16,666.
- The instalment appears on the payslip as **Advance recovery (issued …)** in every payroll run for a month that ends on or after the issue date.
- **Remaining** goes down only when you **finalise** the payroll run. Recalculating a draft does not change it.
- When **Remaining** reaches 0, the status changes to *settled* and recovery stops.

### Change an advance

Click **Edit**. You can change **Issued on**, **Instalments** and **Note**. The **Employee** and **Amount** cannot be changed after the advance is created. To spread the rest over more months, increase **Instalments**.

> **Tip:** An advance cannot be deleted from this page, so check the amount before you click **Create**. For a one-off recovery that is not a real advance, use a *Deduct* item under Bonuses & Adjustments instead.

## Common problems

### The bonus is not on the payslip

The payroll run was calculated before you added it, or the **Pay month** is a different month. Check the month, then click **Recalculate** on the draft run. A final run cannot change.

### I can't see Bonuses & Adjustments in the menu

Your plan does not include Payroll Full bonuses and one-off pay items. Ask the account owner to look at **Billing**.

### The advance was not deducted

The advance was **Issued on** a date after the last day of that payroll month, or its status is already *settled*. Check the date, then recalculate the draft run.

### Remaining did not go down

**Remaining** changes only when the payroll run is finalised with **Finalise & send payslips**. A draft run shows the instalment but does not record it.

### Income tax went up in the bonus month

This is expected. The extra tax on a taxable bonus is taken in the month it is paid; later months are not affected. If the bonus should not be taxed, edit it and switch **Taxable** off, then recalculate.
