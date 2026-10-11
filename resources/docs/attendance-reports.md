---
title: Attendance reports
group: Reports
order: 20
summary: The daily and monthly attendance reports: who came today, missing punches, lates, absences, the monthly grid, monthly totals, overtime and punctuality.
menu: Reports → Reports → Daily / Monthly
panel: reports/daily-muster | reports/yesterday-today | reports/missing-punches | reports/late-register | reports/early-out-register | reports/absent-register | reports/monthly-sheet | reports/month-summary | reports/attendance-summary | reports/department-summary | reports/branch-summary | reports/overtime | reports/punctuality
screens: attendance-reports-daily-muster=/admin/reports/daily-muster | attendance-reports-monthly-sheet=/admin/reports/monthly-sheet | attendance-reports-month-summary=/admin/reports/month-summary
---

## In short

- **Daily attendance** is the morning check: everyone on one day with in, out, late minutes and status.
- **Missing punches** lists days with no in or no out punch. Fix them before you lock the month.
- **Late & early-out** and **Absent register** list every late arrival, early leaving and absence in a date range.
- **Monthly grid – all staff** shows one line per person with a code for each day. **Monthly totals** shows the month's numbers per employee, department or branch.
- **Overtime** and **Punctuality scorecard** help you check extra hours and who comes on time.
- All of them use the common filters and download to Excel and PDF. See [Reports](/help/reports-overview).

## Daily reports

### Daily attendance

**Shows:** every employee on one day: **Code**, **Name**, **Department**, **Shift**, **In**, **Out**, **Worked** (hours:minutes), **Late** and **Early** (minutes), **Status**, **Flags** (for example *Late*, *Missing out punch*, *Manual punch*) and **Source** (where the punches came from: machine, phone, kiosk and so on).

**Use it:** every morning to see who is in, or to check a past day.

**Filters:** **Date** (today by default; you cannot pick a future date), **Attendance status** (for example only *Absent*, or **Late** to see only late comers), and the common filters.

Under the table you see a count for each status and the **Total**. A status of **Pending** means {app} has not worked out that day yet, for example because the employee has not punched today. People who joined after the chosen date are left out.

![Daily attendance report for one day, with in and out times and status](attendance-reports-daily-muster.jpg)

### Yesterday & today time

**Shows:** each employee, grouped by department: **Shift** (start–end), **Y/day out** (last punch yesterday), **Today in** (first punch today) and today's **Status**.

**Use it:** in the morning to see who stayed late last night and who has arrived today. Under the table: **In today** and **Total**.

**Filters:** **Date** and the common filters.

### Missing punches

**Shows:** each day in the date range where an employee has a punch missing: **Date**, **Code**, **Name**, **Missing** (*In* or *Out*), the **In** and **Out** times that do exist, and **Auto-closed** (*yes* when {app} closed the day by itself because the out punch never came).

**Use it:** before you lock the month. Fix each line with [Manual entry](/help/manual-entry), or ask the employee to send a [correction](/help/corrections).

**Filters:** **From**, **To** and the common filters.

### Late & early-out

**Shows:** every late arrival, or every early leaving, in the date range. Choose which one with **Show**: *Late arrivals* or *Early leaving*.

- Late arrivals: **Date**, **Code**, **Name**, **Shift start**, **In**, **Late (min)**, **Running count**.
- Early leaving: **Date**, **Code**, **Name**, **Shift end**, **Out**, **Early (min)**, **Running count**.

**Running count** is how many times this person has been late (or left early) so far in the range: 1 the first time, 2 the second time, and so on. Under the table: **Occurrences** and the number of **Employees**.

**Use it:** to warn staff who are often late, or to check late deductions.

**Filters:** **Show**, **From**, **To** and the common filters.

### Absent register

**Shows:** every absent day in the date range: **Date**, **Code**, **Name**, **Department** and **Consecutive** (how many working days in a row the person has been absent up to that day). Weekly offs and holidays do not break a run of absences. A day at work or on leave starts the count again.

**Use it:** to find people who stopped coming (a long run), for example before a warning letter. Under the table: **Absent days** and **Longest streak**.

**Filters:** **From**, **To** and the common filters.

## Monthly reports

### Monthly grid – all staff

**Shows:** one line per employee for the whole month: a code for each day, then the totals **P**, **A**, **HD**, **LV**, **UL**, **EX**, **Late**, **Leave adj.** (days charged to leave for lates or half days), **Ded.** (deduction days) and **Payable** (days to pay). The meaning of each code is printed under the table.

![Monthly grid with a coloured code for each day](attendance-reports-monthly-sheet.jpg)

| Code | Meaning |
|---|---|
| **P** | Present |
| **L** | Late (present, but came late) |
| **HD** | Half day |
| **A** | Absent |
| **WO** | Weekly off |
| **H** | Holiday |
| **OD** / **WFH** | Official duty / Work from home |
| **LV**, or the leave type code such as **CL**, **SL**, **AL** | Paid leave (with **½** after it for a half-day leave) |
| **UL** | Unpaid leave |
| **MO** | Missing out punch |
| **EX** | Exempted (excused) |
| **–** | Not joined yet, or already left |

**Use it:** to see the whole company's month on a few pages, for the notice board or the owner.

**Filters:** **Month** and the common filters. Click a name to open that person's [detailed monthly report](/help/monthly-attendance-report).

> **Tip:** A name marked *· daily wage* is paid only for the days worked, so a lower **Payable** number is correct for them.

### Monthly totals

**Shows:** the month's numbers: **Present**, **Late**, **Half day**, **Leave**, **OD / WFH**, **Absent**, **Off days**, **Holidays**, **Overtime (h)**, **Deduction days** and **Payable days**.

**Filters:** **Month**, **One line per** (*Employee*, *Department* or *Branch*) and the common filters. With *Department* or *Branch*, each line adds up its staff and shows how many **Employees** it has. Use this to compare departments or branches.

The line above the table tells you if the numbers are final:

- **Locked on 30 Sep 2026 — the numbers salaries were paid on**: the month is locked, and these numbers will not change.
- **Open** or **In review — live numbers, may still change**: the month is not locked yet.

Under the table: **Employees**, **Absent days**, **Lates** and **Deduction days**.

![Monthly totals, one line per employee](attendance-reports-month-summary.jpg)

### Overtime

**Shows:** each day someone worked overtime in the date range: **Date**, **Code**, **Name**, **Day** (the day's status), **Worked**, **Expected** (the shift's hours), **OT** (hours:minutes) and **OT (min)**. Under the table: **Total OT hours**.

**Use it:** to check overtime before you pay it. How overtime is counted is set on the shift; see [Shifts](/help/shifts).

**Filters:** **From**, **To** and the common filters.

### Punctuality scorecard

**Shows:** a score from 0 to 100 for each employee, from best to worst: **Rank**, **Code**, **Name**, **Score**, **vs previous** (the change compared with the same number of days just before, for example *+5* or *-12*), **Working days**, **Late**, **Half days** and **Absent**.

The score starts at 100. {app} takes off 50 × the share of working days with a late arrival, 75 × the share of half days, and 100 × the share of absent days. Only working days (present, half day or absent) count. Staff with no working days in the range are left out.

**Use it:** to reward punctual staff or to find people who need a talk. It opens on the last 30 days.

**Filters:** **From**, **To** and the common filters.

## Old report names

Some reports were joined together. Old links and bookmarks still work and open the new report:

- **Early-out register** opens **Late & early-out** with *Early leaving*.
- **Attendance summary**, **Department summary** and **Branch summary** open **Monthly totals** with one line per *Employee*, *Department* or *Branch*.

## Common problems

### A person shows "Pending" in Daily attendance

{app} has not worked out that day for them yet, usually because they have not punched today. If they are at work, check that the machine is sending punches (the **Device sync** report, see [Other reports](/help/other-reports)) and that their PIN is linked to them ([Unmapped PINs](/help/unmapped-pins)).

### The Monthly grid shows MO on many days

**MO** means the out punch is missing. The staff forgot to punch out, or the machine missed it. Open the **Missing punches** report to list them, then fix them with [Manual entry](/help/manual-entry) or [Corrections](/help/corrections).

### Monthly totals say "Open — live numbers, may still change"

The month is not locked yet, so the numbers can still change. Lock it under **Attendance → Months (lock)** when all fixes are done. See [Month lock](/help/month-lock).

### Someone is missing from Daily attendance

People who had not joined on that date are left out. Also check the filters (**Branch**, **Department**, **Employee status**), and remember that **Shift** keeps only people who were on that shift on that date. Managers see only the people who report to them.

### The Punctuality scorecard does not list an employee

They had no working days in the chosen dates (for example they were on leave the whole time), so there is nothing to score. Choose a longer date range.

### Late minutes look wrong

Late minutes depend on the shift start time, the grace minutes on the shift, and any [timing override](/help/timing-overrides) for that day. Check the employee's shift in the [Roster](/help/roster), and see [How attendance works](/help/how-attendance-works).
