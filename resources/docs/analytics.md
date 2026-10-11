---
title: Analytics
group: Insights
order: 10
summary: Charts and headline numbers for the whole company: punctuality score, deduction days, what absences cost, the attendance trend and who is late most.
menu: Reports → Analytics
panel: analytics
screens: analytics=/admin/analytics
---

## In short

- **Analytics** shows the big picture on one page: three headline numbers and two charts, for the whole company.
- **Punctuality score (30 days)** is a number from 0 to 100. Higher is better.
- **Absenteeism cost** is what last month's deduction days are worth in salary. It needs salary structures.
- The buttons at the top open the detailed reports: **Punctuality scorecard**, **Department comparison** and **Device reliability**.
- Owner, Admin, HR and Branch manager can open it. It does not need the AI feature.

## What you see on this page

![The Analytics page with headline numbers and charts](analytics.jpg)

### Headline numbers

| Box | What it means |
|---|---|
| **Punctuality score (30 days)** | A score out of 100 for everyone over the last 30 days. Under it: how many late and absent days out of how many working days. |
| **Deduction days, *month*** | Total days to deduct from salaries for last month (the last full pay-cycle month), from absences, lates and half days. |
| **Absenteeism cost, *month*** | Those deduction days in rupees, for example *Rs 185,000*, using each employee's per-day salary. Employees with no salary structure count as Rs 0. |

The line at the top of the page explains the score: *Punctuality score = 100 − 50 × late share − 75 × half-day share − 100 × absent share of working days.* For example, if out of 100 working days 10 were late and 2 absent, the score is 100 − 5 − 2 = 93. Only working days count: present, half day and absent. Leave, holidays and weekly offs do not.

### Charts

- **Attendance rate, last 12 months (%)**: for each month, the share of working days on which people came (present, half day, official duty or work from home) out of all working days including absences. A dip shows a bad month.
- **Most late arrivals, last 30 days**: the 10 people with the most late days. Point at a bar to see the number.

## The buttons at the top

| Button | Opens |
|---|---|
| **Punctuality scorecard** | The score and rank for every employee, with the change from the period before. |
| **Department comparison** | **Monthly totals** with one line per department. |
| **Device reliability** | The **Device sync** report: punches per machine and days with no data. |

See [Attendance reports](/help/attendance-reports) and [Other reports](/help/other-reports).

## How to use it

- **Once a month**, after locking the month, look at **Deduction days** and **Absenteeism cost** with the owner.
- **Every week**, look at **Most late arrivals**. Open the **Punctuality scorecard** to see the full list, and talk to the people at the top.
- If the attendance rate drops suddenly, check **Device reliability** first. A machine that stopped sending makes people look absent.

> **Tip:** The numbers are only as good as the attendance behind them. Fix [missing punches](/help/attendance-reports) and approve [leave requests](/help/leave-requests) before you trust the cost figure.

## Who can use it

Owner, Admin, HR and Branch manager (anyone with full report access). Managers do not see Analytics; they can use the reports for their own team instead.

## Common problems

### Absenteeism cost shows Rs 0

No employee has a salary structure, so {app} cannot put a rupee value on the deduction days. Add salary structures under **Payroll → Salary structures**. See [Salary structures](/help/salary-structures).

### Absenteeism cost looks too low

Employees without a salary structure count as Rs 0. Check that everyone has one.

### The attendance rate chart has gaps

There was no attendance data for those months, for example before your company started using {app}. The chart leaves those months empty instead of showing 0.

### The punctuality score is lower than expected

Absent days lower the score the most (100 × their share), then half days, then lates. Check the **Absent register** and fix wrong absences, for example missing leave or days when a machine was not sending.

### Deduction days here differ from Monthly totals

**Analytics** uses the same rules per employee as **Monthly totals** and payroll (for example daily wage staff, or staff excused from late deductions), so the totals match for the same month. If they differ, someone changed attendance after you opened one of the pages, or the month in **Monthly totals** is locked and shows the numbers saved at lock time. Use **Monthly totals** or **Payroll input** for salaries.

### I can't see Analytics in the menu

Your role does not have full report access. Managers only see reports for their own team. Ask the owner to change your role if needed.
