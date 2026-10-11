---
title: Shift swaps
group: Shifts & holidays
order: 30
summary: Approve or reject requests from two employees who want to exchange their shifts for one day, or enter a swap yourself.
menu: Shifts & Holidays → Shift swaps
panel: shift-swaps
screens: shift-swaps-list=/admin/shift-swaps | shift-swaps-new=/admin/shift-swaps >> New swap request
---

## In short

- A shift swap: two employees exchange their shifts for one date. For example, Ali works Bilal's night shift on 15 Sep 2026 and Bilal works Ali's morning shift.
- Staff ask from the staff app (**/me**). The colleague accepts or declines first; only then does the request come to you.
- The list opens on requests **Waiting for approval**. The number next to *Shift swaps* in the menu is how many are waiting.
- **Approve** changes both employees' shift for that day only and recalculates their attendance. **Reject** changes nothing.
- This page appears only if your plan includes *Rosters & shift swaps*. The Owner, Admin and HR see every swap. A **Manager** sees only the swaps asked by their own team (people whose manager they are) and can approve or reject those. Only the Owner, Admin and HR can enter a swap themselves.

## How a swap request moves

| Status | What it means |
|---|---|
| **Waiting for colleague** | The employee asked in the staff app. The colleague has not answered yet. |
| **Colleague declined** | The colleague said no. The request is closed. |
| **Waiting for approval** | The colleague accepted (or HR entered the swap). It is your turn. |
| **Approved** | The swap is done. Both employees work the other's shift on that date. |
| **Rejected** | You said no. The shifts stay as they were. |

Each step sends a notice to the next person: the colleague when asked, HR and the employee's manager when the colleague accepts, and the employee when the colleague answers.

## What you see on this page

![Shift swaps list with date, employee, swaps with, reason and status](shift-swaps-list.jpg)

| Column | What it means |
|---|---|
| **Date** | The day of the swap. |
| **Employee** | The person who asked. |
| **Swaps with** | The colleague. |
| **Reason** | What the employee wrote. |
| **Status** | See the table above. |
| **Rejection reason** | Why the swap was rejected. |

Use the **filters** button to see other statuses, for example everything already approved.

## Approve or reject

Click **Approve** or **Reject** on a row that is *Waiting for approval*. {app} shows both people's shifts for that date and asks you to confirm. To reject, you must write a **Reason**; it shows in the *Rejection reason* column and to the employee in the staff app.

When you approve, {app} checks again that both employees still have a shift that day and that the shifts are different. Each employee then gets the other's shift for that one date, as if you had changed it on the [Roster](/help/roster). Their attendance for that day is worked out again.

## Enter a swap yourself

Use this when two employees agree in person and do not use the staff app.

1. Click **New swap request**.
2. Pick the **Employee** and who they **Swaps with**.
3. Pick the **Date** (today or later).
4. Write a **Reason** if you like.
5. Under the fields, {app} shows each person's shift on that date, for example *Ali Khan: Morning (09:00–17:00) ⇄ Bilal Malik: Night (22:00–06:00)*. Check it.
6. Click **Create**.

![The New swap request form showing both employees' shifts](shift-swaps-new.jpg)

A swap you enter goes straight to *Waiting for approval*; there is no colleague step. Click **Approve** on it to make it happen.

## What staff can do in the app

- Ask a colleague from the **same branch and same department**.
- Have at most 3 requests waiting at one time.
- Ask only for today or a later date.

See [Staff app](/help/staff-app).

## Common problems

### "Both are on the same shift that day, so there is nothing to swap."

Both employees already work the same shift on that date. Check the date, or use the [Roster](/help/roster) to give one of them a different shift.

### "Both employees need a shift on that date."

One of them has no shift: no shift of their own and no default shift. Give them a shift, or mark one shift as default under [Shifts](/help/shifts).

### "There is already a swap request for one of you on that date."

One of the two already has an open request for that day. Approve, reject or wait for the open one first.

### "You already have 3 swap requests waiting. Wait for an answer first."

An employee can have only 3 open requests. A colleague must answer, or you must decide, before they can ask again.

### "Choose a colleague to swap with."

In the staff app, staff can only ask someone in the same branch and department who has not left. HR can enter a swap between any two employees with **New swap request**.

### A manager got a notice but sees no swap

A Manager sees a swap only when the employee who asked has them set as **Manager** on their profile. Check the employee's profile, or let HR decide it.

### The swap is in the list but I can't see Approve

The request is still *Waiting for colleague*. The colleague must accept in the staff app first. If they cannot, reject it and enter it again yourself with **New swap request**.
