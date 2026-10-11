---
title: Leave requests
group: Leave
order: 20
summary: Apply for leave for a staff member, and approve, reject or cancel the requests staff send from their phones.
menu: Leave → Leave requests
panel: leave-requests
screens: leave-requests=/admin/leave-requests | leave-request-new=/admin/leave-requests >> New leave request
---

## In short

- Staff apply from the staff app (**/me**); HR or a manager can also apply for them with **New leave request**.
- The list opens on **Pending** requests. The number next to *Leave requests* in the menu is how many are waiting.
- A request goes through the approval steps set in **Company settings** (normally the employee's manager first, then HR).
- **Approve** takes the days off the employee's leave balance and marks those days as leave in attendance. **Reject** needs a reason.
- **Cancel** gives the days back, even after approval (unless the month is locked).

## What you see on this page

![Leave requests list, showing pending requests with Approve and Reject buttons](leave-requests.jpg)

Each row is one request:

| Column | What it means |
|---|---|
| **Code / Employee** | Who the leave is for. |
| **Type** | The leave type, for example Annual or Sick. A grey line under it such as *"Punched on 2 days of this leave"* means the machine recorded the employee at work on some of those days — worth a look before you approve. |
| **From / To** | The first and last day of the leave. |
| **Day part** | Full day, or first / second half. |
| **Days** | How many days come off the balance. Weekly offs and holidays are not counted, unless the leave type says otherwise. |
| **Status** | Pending (yellow), Approved (green), Rejected (red) or Cancelled (grey). |
| **Waiting for** | Whose turn it is to decide: *Manager* or *HR*. |

Use the **filters** button to show other statuses (for example all approved leave this year), one leave type only, or **Punched on a leave day**.

## Apply for leave for someone

1. Click **New leave request** at the top right.
2. Pick the **Employee** and the leave **Type**.
3. Choose **Day part**: *Full day*, or a half day (only one date, and only if the leave type allows half days).
4. Pick **From date** and **To date**.
5. For a short leave of a few hours, fill **Short leave from / to** instead (same date in From and To).
6. Write a **Reason**, and attach a file if needed (for example a medical certificate, up to 5 MB).
7. Click **Submit**.

![The New leave request form](leave-request-new.jpg)

The request then waits for approval like any other. {app} checks it straight away and tells you if something is wrong; see *Common problems* below.

> **Who can apply for whom:** HR and the owner can pick any employee. A manager can pick only themselves and the people who report to them.

## Approve, reject or cancel

- **Approve** — click it, add a note if you like, and confirm. If there is a second step (for example HR after the manager), the request moves to that person; *Waiting for* changes. After the last step the leave is final: the balance goes down and the days show as leave in attendance and reports.
- **Reject** — you must give a reason. The employee sees it in the staff app.
- **Cancel** — for a pending or approved request that is no longer needed. The days go back to the balance.
- **Read attachment (AI)** — shown when the request has an attachment and your plan includes AI. {app} reads the certificate and tells you whether its dates match the request. It is a suggestion; always look yourself.

The employee is told about each decision (in the staff app, and by SMS or WhatsApp if your company has them switched on).

## Who decides

The order of approvers is set under **System → Company settings** (leave approval steps). Usually:

1. **Manager** — the employee's manager, as set on the employee's profile.
2. **HR** — anyone with the HR role (or the owner).

If the employee has no manager, or the manager has no {app} login, the manager step is skipped and the request goes straight to HR.

HR and the owner can decide at any step. When they approve, the leave is final straight away, even if the manager has not looked at it yet. A rejection at any step ends the request.

## Common problems

### "Not enough balance" when applying

The employee has fewer days left than requested. Check **Leave → Leave balances**. Either apply for fewer days, use another type (for example Unpaid leave), or add days with an adjustment on the balance. A leave type can also be set to allow a negative balance.

### "There are no working days in the selected dates"

All the chosen dates are weekly offs or holidays for that employee, so there is nothing to take leave from. Check the dates and the employee's shift.

### "That month is locked"

The month was closed under **Attendance → Months (lock)**. Ask the owner to unlock the month (HR can lock a month but not unlock it), make the change, then lock it again.

### "There is already a leave request for these dates"

A pending or approved request already covers some of these days. Open it and cancel it, or change your dates.

### I can't see the Approve button

It is not your turn, or you are not allowed to decide this step. Look at **Waiting for**: if it says *Manager*, the employee's manager has to approve first. Managers only see requests from their own team.

### Half day or short leave is refused

The leave type does not allow it, or more than one date was chosen. Half days and short leave are for one date only; change the settings of the type under **Leave → Leave types** if needed.
