---
title: Corrections
group: Attendance
order: 60
summary: Ask for a change to a day's attendance (add or remove a punch, change the status, official duty, work from home) and approve or reject these requests.
menu: Attendance → Corrections
panel: corrections
screens: corrections=/admin/corrections | corrections-new=/admin/corrections >> New request
---

## In short

- A **correction** is a request to fix one employee's attendance on one date. It goes through approval before anything changes.
- Five types: **Add punch**, **Remove punch**, **Change day status**, **Official duty** and **Work from home**.
- Staff send corrections from the staff app (**/me**). Managers and HR can send them from **New request**.
- The approval steps are set in **Company settings** (normally the manager first, then HR). The list opens on **Pending** requests, and the number next to *Corrections* in the menu is how many are waiting.
- When the last step approves, {app} applies the change by itself and works the day out again. Nothing is changed in a locked month.

## The five types

| Type | What happens when approved | Use it when |
|---|---|---|
| **Add punch** | A punch is added at the given time, as *In* or *Out*. | The employee came or left but the punch was not recorded. |
| **Remove punch** | The chosen punch is deleted. | A wrong punch, for example a double tap or a punch by someone else. |
| **Change day status** | The day's status is set by hand (for example Absent to Present), whatever the punches say. The day gets the flag *Changed by hand*. | A special case that the rules cannot handle. Use it rarely. |
| **Official duty** | The day becomes *Official duty*: paid, not absent. | Bank work, a client visit, field work. |
| **Work from home** | The day becomes *Work from home*: paid, not absent. | The employee worked from home with permission. |

Official duty and work from home can be requested for **future dates** too. The other types only for today or earlier.

> **Tip:** Holidays, off days and approved full-day leave are decided before official duty and work from home. If a holiday or leave is on that date, an approved *Official duty* request does not change the day. Use *Change day status* in that case. See [How attendance works](/help/how-attendance-works).

## What you see on this page

![Corrections list with pending requests and Approve / Reject buttons](corrections.jpg)

| Column | What it means |
|---|---|
| **Date** | The day to be fixed. |
| **Employee** | Whose attendance it is. |
| **Type** | One of the five types above. |
| **Details** | What exactly: *Out punch at 18:00*, *Change to Present*, *Whole day on official duty*, or the punch to remove. |
| **Reason** | Why the change is needed. Point at it to read all of it. |
| **Requested by** | Who sent it. *(self)* means the employee sent it from the staff app. |
| **Status** | Pending, Approved, Rejected or Cancelled. |
| **Approval** | Whose turn it is (*Waiting for manager*, *Waiting for HR*), or who decided it. |

Use the **Status** filter to see approved or rejected requests. The list starts on Pending.

Managers see only their own requests and their team's.

## Send a correction

1. Click **New request** at the top right.
2. Pick the **Employee** and the **Date**.
3. Pick the **Type**. A short line under it explains what it does.
4. Fill the extra fields for that type:
   - **Add punch**: the **Time** and the **Direction** (*In* or *Out*). For a missing out punch, {app} fills in the employee's usual out time when it has enough history; check it.
   - **Remove punch**: pick the punch from **Punch to remove**. The list shows that employee's punches on that date, with the time and source.
   - **Change day status**: pick the new **Status**.
5. Write the **Reason**.
6. Click **Submit**.

![The New request form for a correction](corrections-new.jpg)

The request then waits for approval.

> **Who can send for whom:** owner, admin, HR and branch managers can pick any employee. A manager can pick themselves and the people who report to them.

> **Tip:** For a night shift, an out punch after midnight happened on the next calendar date. Pick that date for an **Add punch** request; {app} still counts it for the night shift that started the evening before.

## Approve or reject

1. Click **Approve** or **Reject** on the row.
2. Write a **Note**. It is optional when you approve and required when you reject.
3. Click **Submit**.

If there is another step (for example HR after the manager), {app} says *Approved — waiting for HR*: nothing changes yet, the request moves to that person and *Approval* changes. When the last step approves, the message is *Approved and applied*. After the last step, {app} makes the change and works the day out again within a minute. A rejection at any step ends the request.

## Who decides

The order of approvers is set in **System → Company settings → Correction approval**: *Manager, then HR* (the default), *HR only* or *Manager only*.

- **Manager**: the employee's manager, as set on the employee's profile. If the employee has no manager, or the manager has no active {app} login, this step is skipped.
- **HR**: anyone with the HR, branch manager, admin or owner role.

HR-level people can decide at any step. When they approve, the change is final straight away, even if the manager has not looked yet.

The employee is told about the decision in the staff app.

## Common problems

### "That month is locked."

The date is in a locked month, so it cannot change. The owner can unlock it under [Month lock](/help/month-lock); then send or approve the correction and lock the month again.

### "Only OD / WFH can be requested for future dates."

You picked a date after today with another type. Wait until the day has happened, or use Official duty / Work from home.

### "You can only request corrections for yourself or your team."

The chosen employee does not report to you. Ask HR to send it, or check the **Manager** field on the employee's profile.

### "That punch does not belong to this employee and date."

The punch was already removed, or the employee or date changed after you picked it. Pick the employee and date again, then choose the punch.

### "Give the time (HH:MM) and whether it was in or out."

For **Add punch**, fill both **Time** and **Direction**.

### "This request is no longer pending."

Someone else already decided it. Reload the page.

### I can't see the Approve button

It is not your turn, or you cannot decide this step. Look at **Approval**: if it says *Waiting for manager*, the manager has to decide first, unless you are HR.
