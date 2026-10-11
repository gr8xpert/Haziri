---
title: Dashboard
group: Getting started
order: 30
summary: The first page after logging in: today's attendance numbers, who is in the office now, and how each branch is doing.
menu: Dashboard (top of the menu)
panel:
screens: dashboard=/admin
---

## In short

- The dashboard shows **today** only. It refreshes by itself every minute.
- Six boxes at the top: **Present**, **Late**, **Not in yet / absent**, **On leave**, **Unmapped PINs**, **Devices offline**.
- **In office now** lists everyone who has punched in today and not yet punched out.
- With more than one branch (if your plan includes branch managers), a **Branch** box lets you look at one branch, and **Branches today** compares them.
- Roles that can see all attendance (Owner, Admin, HR, Branch manager and Receptionist) see everyone. A Manager sees only their own team: the people who report to them, and themselves.

## What you see on this page

![The dashboard with today's numbers and the In office now list](dashboard.jpg)

### Today's numbers

| Box | What it counts |
|---|---|
| **Present** | Employees who came today: present, half day, official duty or work from home. Under it: *of N active employees* (staff who have joined and not left). |
| **Late** | Employees who arrived after the shift start plus the grace time. |
| **Not in yet / absent** | Active employees with no punch today who are not on leave, not on a holiday, not on a weekly off and not excused by a company exemption. In the morning these are people who have not arrived yet; at the end of the day they are absent. |
| **On leave** | Employees on approved leave today (paid, unpaid or half-day leave). |
| **Unmapped PINs** | Machine PINs that sent punches but are not linked to any employee. Their punches do not count until you link them. See [Unmapped PINs](/help/unmapped-pins). |
| **Devices offline** | Attendance machines that have stopped sending. Check power and internet. See [Attendance machines](/help/zkteco-machines). |

A box turns yellow or red when something needs your attention, and grey when all is well (for example *Everyone on time*, *All devices online*).

A Manager sees only the first four boxes, counted for their own team (*of N in your team*), and **In office now** lists only their team.

### In office now

A list of people who punched in today and have no punch-out yet.

| Column | What it means |
|---|---|
| **Code** / **Name** | The employee. |
| **Department** | Their department. |
| **In since** | Time of their first punch today. |
| **Late** | *On time* (green) or how many minutes late (yellow). |

The earliest arrivals are at the top. Show 10, 25 or 50 rows per page at the bottom. If the list says *Nobody is checked in right now*, nobody has punched in yet today, or everyone has already punched out.

### Branches today

This table appears only if your plan includes branch managers and you can see more than one branch. One row per branch:

| Column | What it means |
|---|---|
| **Branch** | The branch name. |
| **Staff** | Active employees in the branch. |
| **Present**, **Late**, **Not in yet / absent**, **On leave** | As in the boxes above, for this branch. |
| **Attendance** | Present ÷ (staff − on leave − off today), as a percentage. Green from 90%, yellow from 75%, red below. |

## How to look at one branch

1. At the top of the dashboard, open the **Branch** box.
2. Pick a branch. The boxes and **In office now** now show that branch only.
3. Pick **All branches** to go back.

The **Branch** box appears only when you can see more than one branch. A branch manager sees only the branches set on their login.

## Where the numbers come from

The dashboard reads the worked-out day of each employee (see [How attendance is worked out](/help/how-attendance-works)). A machine punch reaches the dashboard within a minute or two of being sent. If a machine sends its punches late (for example after the internet comes back), the numbers change when they arrive.

> **Tip:** For a printable list of today's attendance, use **Reports** → *Daily attendance* or *Yesterday & today time*. See [Attendance reports](/help/attendance-reports).

## Common problems

### "Not in yet / absent" counts people who are on leave

Their leave is not approved yet. Only approved leave counts as *On leave*. Approve it under **Leave → Leave requests**.

### Someone is in the office but not in "In office now"

Their punch has not reached {app}. Check **Devices offline**, and look for their PIN under **Devices → Unmapped PINs**: if their machine PIN is not linked to them, their punches are not counted. A phone punch from outside the office may also be waiting under **Attendance → Punch approvals**.

### Someone left but still shows in "In office now"

They did not punch out, so {app} still sees them as in. Ask staff to punch out every evening. A missing punch-out is fixed later with a correction or manual entry.

### The dashboard is empty

A Manager sees only the people who report to them. If nobody is set to report to you (**Manager** on the employee's record), your boxes show 0; ask HR to set it. Staff with the Employee role have no dashboard and use the staff app. If you are HR or the owner and it is still empty, no employees have been added yet, or nobody has punched today.

### "Devices offline" shows a machine that is working

The machine has not reached {app} for a while: often its internet or the cloud server setting on the machine is wrong. Open **Devices → Devices** to see when it was last seen. See [Attendance machines](/help/zkteco-machines).
