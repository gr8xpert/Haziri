---
title: Leave types
group: Leave
order: 10
summary: Set up the kinds of leave your company gives (annual, sick, casual, unpaid and so on) and their rules.
menu: Leave → Leave types
panel: leave-types
screens: leave-types-list=/admin/leave-types | leave-types-new=/admin/leave-types >> New leave type
---

## In short

- A leave type is one kind of leave, for example *Annual*, *Sick* or *Unpaid*, with its own yearly days and rules.
- **Annual quota days** is how many days a year each employee gets. **Accrual** decides if they get them all at the start of the leave year or bit by bit each month.
- **Carry forward max** is how many unused days move into the next leave year.
- Different grades or job categories can get different days with **Entitlement by grade / category**.
- Leave types cannot be deleted. Switch off **Active** to stop using one.
- Only the Owner, Admin and HR can open this page.

## What you see on this page

![The Leave types list with code, name, paid, quota and accrual](leave-types-list.jpg)

| Column | What it means |
|---|---|
| **Code** | Short code shown in the monthly attendance grid, for example *AL*. |
| **Name** | For example *Annual leave*. |
| **Paid** | A tick means it is paid leave. |
| **Quota** | Days per leave year. |
| **Accrual** | *Yearly, upfront* or *Monthly*. |
| **Grade rules** | How many grade / category rows the type has. |
| **Sandwich rule** | A tick means off days between two leave days count as leave. |
| **Active** | Only active types can be chosen in leave requests. |

## Create or change a leave type

1. Click **New leave type** (or **Edit** on a row).
2. Fill in the fields below.
3. Click **Create** (or **Save changes**).

![The new leave type form](leave-types-new.jpg)

| Field | What it does |
|---|---|
| **Name** / **Code** | The code is filled in from the name; you can change it (up to 10 letters). It must be different from every other type. |
| **Paid leave** | On: the day is paid and comes off the balance. Off (unpaid): no balance check, and payroll deducts the day. |
| **Annual quota days** | Days per leave year. |
| **Accrual** | *Yearly, upfront*: all the days are there from the first day of the leave year. *Monthly*: one twelfth is added each month. |
| **Carry forward max** | Unused days that move to the next leave year. 0 = none. |
| **Max consecutive days** | The most days one request can have. Blank = no limit. |
| **Requires attachment after days** | A file (for example a medical certificate) is needed for requests longer than this. Blank = never. |
| **Allow short leave hours** | Allows short leave of a few hours, up to this many hours. Blank = no short leave. |
| **Allow half day** | Allows first-half or second-half leave. |
| **Sandwich rule** | A weekly off or holiday between two leave days counts as leave too. Example: leave on Saturday and Monday, Sunday off → Sunday also comes off the balance. |
| **Counts weekends** | Count every calendar day in the request, not only working days. |
| **Applies to** | Tick the categories (Permanent, Contract, Probation, Daily wage, Intern) that can use it. None ticked = everyone. |
| **Gender restriction** | *Female only*, *Male only*, or *Everyone* (for example maternity leave). |
| **Active** | Off hides the type from new requests. |
| **Paid out on leaving** | The unused balance is paid in the final settlement. This appears only if your plan includes *Final settlement*. |
| **Allow negative balance** | Leave can be approved beyond the balance; the overdraft carries into the next leave year. |

> **Tip:** The leave year is set under **System → Company settings** (**Leave year starts in**). January = calendar year; July = July to June.

## Different days for different grades

Under **Entitlement by grade / category**, click **Add entitlement**. For each row choose a **Grade**, a **Category** (or both), the **Days / year**, and if needed **Carry forward max** and **Max consecutive days** (blank = same as above).

When more than one row fits an employee, the most specific wins: grade + category, then grade, then category. Employees that no row fits get the numbers at the top of the form.

Example: Annual leave 14 days for everyone, but grade *Manager* gets 20 and *Probation* staff get 0.

When you add, change or remove a grade row, {app} recalculates what everyone has earned of this type this leave year.

For one employee only (for example a deal in an appointment letter), use **Special rule** on [Leave balances](/help/leave-balances).

## How days are earned

- *Yearly, upfront*: an employee who joins during the leave year gets a share. Example: quota 12, joined in the 4th month → 9 days.
- *Monthly*: quota ÷ 12 for each month from joining up to and including this month. Example: quota 12, now in the 5th month of the leave year → 5 days.
- Days are rounded to the nearest half day.
- Employees with **Earns leave** switched off on their profile (for example daily wagers) earn nothing.

See [Leave balances](/help/leave-balances) for how balances are opened and carried forward, and [Leave requests](/help/leave-requests) for applying and approving.

## Common problems

### "This leave type is not active."

The type was switched off. Edit it and switch on **Active**, or choose another type.

### "This leave type does not apply to Probation employees."

**Applies to** does not include the employee's category (here: Probation). Tick it, or use another type.

### "This leave type is not available for this employee."

The type has a **Gender restriction** that does not match the employee. Check the employee's gender on their profile, or the restriction.

### "At most 3 consecutive days are allowed."

The request is longer than **Max consecutive days**. Split it, use another type, or raise the limit (on the type, the grade row, or a special rule).

### "An attachment (e.g. medical certificate) is required for more than 2 days."

The request is longer than **Requires attachment after days**. Attach the file to the request.

### "The code has already been taken"

Another leave type, maybe an old deleted one, uses that code. Choose another code.

### Changing the quota did not change balances

Balances update when you save the type's grade rows, at the start of every month, or when you click **Create / refresh balances** on [Leave balances](/help/leave-balances). Click it to update now.
