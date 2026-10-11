---
title: Audit log
group: System
order: 30
summary: See who changed what and when, with the values before and after, for employees, punches, leave, settings and more.
menu: System → Audit log
panel: audit-logs
screens: audit-log=/admin/audit-logs
---

## In short

- The audit log records changes made in {app}: who did it, when, which record, and the values before and after.
- It is read-only. Nobody can edit or delete entries.
- Owner, Admin and HR can see it.
- Machine punches are not listed (there are too many); manual punches and changes to punches are.
- Use the filters **Record type** and **Event** to narrow the list, and **View** to see the details.

## What you see on this page

![The Audit log, newest changes first](audit-log.jpg)

Each row is one change, newest first:

| Column | What it means |
|---|---|
| **Created at** | Date and time of the change. |
| **By** | The login that made the change. *System* = {app} did it by itself (for example a scheduled job or a machine). |
| **Event** | *Created*, *Updated* or *Deleted*, or a special event such as *Unlocked*, *Face enrolled* or *Face deleted*. |
| **Record** | The kind of record, for example *Employee*, *LeaveRequest*, *Setting* or *Punch*. |
| **#** | The record's number. |
| **Description** | A short description of the change. |

Click the **Created at** column header to change the sort order.

## How to find a change

1. Go to **System → Audit log**.
2. Click the **filters** button.
3. In **Record type**, pick the kind of record, for example *Employee*.
4. In **Event**, pick *Created*, *Updated* or *Deleted* if you want only one kind.
5. Click **View** on a row to see the details.

The **View** window shows **Created at**, **By**, **Event**, **Record**, and two lists:

- **Before** — the values before the change.
- **After** — the new values.

Only the fields that changed are listed. For example, if HR changed an employee's shift, you see the old shift number under *Before* and the new one under *After*.

## What is recorded

Changes to, among others:

- employees, branches, departments, users (name, email, phone, linked employee, active)
- punches entered by hand and changes to any punch, corrections, manual entries
- leave types, leave requests, leave balances and adjustments
- shifts, shift assignments, timing overrides, holidays, exemptions
- company settings (each changed setting appears as a *Setting* record)
- month lock and unlock (an unlock also records the reason)
- face enrolment, kiosks, devices
- salary structures, advances, pay items, payroll runs, final settlements, tax slabs
- API tokens, webhooks, notification texts, visitors, announcements

> **Tip:** Before you lock a month, look at the audit log filtered on *Punch* to see all manual punches made that month. The **Manual entry audit** report gives the same in a printable form; see [Other reports](/help/other-reports).

## Who can see it

Owner, Admin and HR. Managers, branch managers and receptionists cannot.

## Common problems

### I can't find the Audit log

Your role cannot see it. Only Owner, Admin and HR can.

### A machine punch is not in the audit log

Punches that come from machines are not recorded here, because there are thousands of them. Look in **Attendance → Punch log** instead. Manual punches and changes to punches are recorded.

### "By" says System

The change was made by {app} itself, not by a person: for example a scheduled task running at night.

### I changed someone's role but I can't find it

Role changes on **Users & roles** are not recorded in the audit log. Changes to a login's name, email, phone, linked employee and *Is active* are.

### I can't filter on Unlocked or Face enrolled

The **Event** filter offers only *Created*, *Updated* and *Deleted*. To find an unlock, filter **Record type** on the month record (*AttendancePeriod*) and look at the **Event** column.

### The Before or After list is empty

For a new record there is no *Before*, and for a deleted record there is no *After*. Special events (like *Unlocked*) have a description instead of lists.
