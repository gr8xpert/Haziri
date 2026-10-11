---
title: Leave balances
group: Leave
order: 30
summary: See how many days of each leave type every employee has earned, used and has left, and add or deduct days when needed.
menu: Leave → Leave balances
panel: leave-balances
screens: leave-balances-list=/admin/leave-balances
---

## In short

- One row per employee, per leave type, per leave year. **Remaining** = Opening + Accrued + Adjusted − Used − Pending.
- Balances are kept up to date by themselves: days are earned on the 1st of each month, and a new leave year opens (with carry-forward) on the 1st of its first month.
- **Create / refresh balances** does the same work now, for example after you add a new leave type or new staff.
- **Adjust** adds or takes off days with a reason (for example encashment or bonus leave).
- **Special rule** gives one employee different days for one leave type.
- HR and the Owner see everyone. A manager sees only their own team and themselves, and cannot change anything.
- These pages appear only if your plan includes *Leave management*. With it off, the Leave menu, the leave reports and the staff app's **Leave** tab are hidden.

## What you see on this page

![Leave balances list with opening, accrued, adjusted, used, pending and remaining](leave-balances-list.jpg)

| Column | What it means |
|---|---|
| **Code / Employee** | Who the balance belongs to. |
| **Type** | The leave type code. *Special rule* under it means this employee has their own rule for this type. |
| **Leave year** | For example *2026*, or *2026-27* if your leave year does not start in January. |
| **Opening** | Days carried forward from last leave year. |
| **Accrued** | Days earned so far this leave year. |
| **Adjusted** | Days added (+) or taken off (−) by hand. |
| **Used** | Days of approved leave, including sandwich days. |
| **Pending** | Days in requests still waiting for approval. They are held so the same days cannot be asked for twice. |
| **Remaining** | What the employee can still take. |

The list opens on the current leave year. Use the **filters** button to pick another **Leave year** or one **Type**. Search by code.

## How the numbers change

| What happens | Effect |
|---|---|
| Employee applies for leave | **Pending** goes up. |
| Leave is approved | **Pending** goes down, **Used** goes up. |
| Leave is rejected | **Pending** goes down. |
| Approved leave is cancelled | **Used** goes down. |
| 1st of each month | **Accrued** is worked out again (monthly types earn one more month). |
| First day of a new leave year | A new row opens. **Opening** = last year's remaining, up to **Carry forward max**. |

See [Leave types](/help/leave-types) for quota, accrual and carry-forward rules, and [Leave requests](/help/leave-requests) for approving.

## Create or refresh balances

1. Click **Create / refresh balances** at the top.
2. Confirm.

{app} creates any missing rows for the current leave year (with carry-forward from last year) and works out **Accrued** again for every active leave type and every employee who has not left. A type with **Applies to** or a **Gender restriction** gets rows only for the employees it applies to; an unused row for anyone else (nothing used, pending, adjusted or carried) is removed. Running it twice does no harm.

> **Tip:** Use it after you add a leave type, change a quota, or add staff, so the numbers show straight away instead of on the 1st.

## Add or take off days

1. Click **Adjust** on the row.
2. **Days** — a positive number adds days (for example *2*); a negative number takes them off (for example *-1.5*).
3. **Reason** — required, for example *Encashment* or *Bonus leave for Eid duty*.
4. Click **Submit**.

The **Adjusted** column changes straight away and the change is recorded with your name and the reason.

## A special rule for one employee

Use this when one person has a different deal, for example written in their appointment letter.

1. Click **Special rule** on the row.
2. Fill in only what is different: **Days per year**, **Carry forward max**, **Max consecutive days**. Blank = the normal rule.
3. Write the **Reason** (required).
4. Click **Submit**.

The rule is for this employee and this leave type, in every leave year. **Accrued** is worked out again at once. To remove the rule, open it, clear all three numbers, and submit.

Who wins: the employee's special rule, then the grade / category row on the leave type, then the leave type itself.

## Common problems

### "Not enough balance: 2 day(s) left, 3 requested."

The employee's **Remaining** is less than the request. Apply for fewer days, use another type (for example unpaid leave), add days with **Adjust**, or switch on **Allow negative balance** on the leave type.

### A man has a maternity leave balance

Check the leave type's **Gender restriction** and the employee's gender on their profile, then click **Create / refresh balances**. Unused rows for people the type does not apply to are removed. A row that already has days used or adjusted stays; fix it with **Adjust**.

### A new employee has no balance

Their rows are created on the 1st of next month, or when they first apply. Click **Create / refresh balances** to create them now.

### A new employee has less than the full quota

That is correct. Someone who joins during the leave year earns only the share for the months left (yearly types) or for the months worked (monthly types).

### An employee has 0 accrued

Check that **Earns leave** is on in their profile, and that the leave type has a quota for their grade or category.

### Last year's days did not carry forward

Carry-forward is capped by **Carry forward max** on the leave type (0 = nothing carries). Check the setting, then click **Create / refresh balances**.

### I can't see Adjust or Special rule

Only the Owner, Admin and HR can change balances. Managers can only look.

### Pending stays high

Requests are waiting for approval. Approve or reject them under [Leave requests](/help/leave-requests).
