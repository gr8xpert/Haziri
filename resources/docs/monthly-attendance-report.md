---
title: Employee monthly report
group: Reports
order: 30
summary: The detailed one-page monthly attendance report per employee, ready to print and sign, for one person or for everyone at once.
menu: Reports → Reports → Employee monthly report (detailed)
panel: reports/monthly-attendance | reports/employee-statement
screens: monthly-attendance-report=/admin/reports/monthly-attendance
---

## In short

- One page per employee for one month: every day with schedule, in, out, status, hours, overtime and remarks, then the totals and the leave balances.
- Pick the **Month** and an **Employee** to see it on screen, exactly as it prints.
- **PDF — this employee** prints one person. **PDF — all employees in filter** makes one PDF with a page for each person. **Separate PDFs (ZIP)** makes one file per person.
- More than 25 employees are prepared in the background. You get a notification (bell icon) with the download link.
- Times entered or corrected by hand are highlighted in yellow, and the reason is in **Remarks**.

## What you see on this page

![The Employee monthly report page with the month, branch, department and employee filters](monthly-attendance-report.jpg)

At the top are four filters:

| Filter | What it does |
|---|---|
| **Month** | The pay-cycle month. The dates it covers are shown under it, for example *September 2026 (21 Aug – 20 Sep)*. |
| **Branch** | Narrows the employee list, and decides who goes into the "all employees" PDF. |
| **Department** | The same, by department. |
| **Employee** | The person to show on screen. The list shows code and name. People who left more than three months ago are not in the list. |

When you change **Branch** or **Department**, the chosen employee is cleared. Pick one again.

Once you pick an employee, the page shows:

1. **The employee:** name, code, designation, department, branch, **Grade**, **Date of joining**, **Payroll ID**, and **Total duration / OT** for the month.
2. **The summary boxes:** **On time**, **Late**, **Half days**, **Absent**, **On leave**, **Holidays**, **Off days**, and **OD / WFH / exempt**.
3. **The days table:**

| Column | What it means |
|---|---|
| **Date** / **Day** | The date and weekday. |
| **Schedule** | *Full Day*, *Half Day* or *Off Day*. Off days are shown lighter. |
| **Shift** | The shift's start and end time that day. |
| **In** / **Out** | The first and last punch. A yellow time was entered or corrected by hand. |
| **Status** | The day's result in words, for example Present, Late, Absent, Paid leave. |
| **Duration** | Time between in and out. |
| **OT** | Overtime that day. |
| **Remarks** | Why the day looks the way it does: the holiday name, the leave reason, the reason for a manual punch or a changed status, the exemption reason, or *Worked on a leave day: cancel the leave?* |

4. **The leave table** (if the employee has leave types): for each type **Prev. balance**, **Allotted**, **Total**, **Availed**, **Adjusted (late / half day)** (days taken from leave because of lates or half days), **Credit**, **Without pay** and **Current balance**.

The printed PDF has the same parts, plus **Printed on**, **Printed by**, the hours box (**Total duration**, **Overtime**, **Worked (after breaks)**), and signature lines: **Prepared by**, **Checked by (HR)** and **Employee signature**.

## How to print one employee

1. Open **Reports → Reports** and click **Employee monthly report (detailed)**.
2. Choose the **Month**.
3. Choose the **Employee**. Their month appears on screen.
4. Click **PDF — this employee**. The file is named like `attendance-E001-2026-09.pdf`.

Click **Excel** to get the same days as a spreadsheet.

> **Tip:** You can also open this report by clicking an employee's name in **Monthly grid – all staff** or **Monthly totals**. It opens for the same month.

## How to print everyone at once

1. Choose the **Month**.
2. To print only part of the company, choose a **Branch** and / or **Department**. Leave both on *All* for everyone.
3. Click one of these:
   - **PDF — all employees in filter**: one PDF file (`attendance-2026-09.pdf`) with one page for each employee. Good for printing and filing.
   - **Separate PDFs (ZIP)**: a ZIP file (`attendance-2026-09.zip`) with one PDF per person, named like *E001 - Ali Khan - Sep 2026.pdf*. Good for sending each person their own report.

You do not need to pick an employee for this. Only people who worked for the company during that month are included (joined before the month ended, and not left before it started).

**Up to 25 employees:** the file downloads straight away. It can take a minute; the page shows *Preparing… one page per employee.*

**More than 25 employees:** you see *Preparing N reports in the background*. Carry on working. When it is ready, you get a notification under the bell icon: **Your attendance report is ready**, with a **Download** button. The link works for 7 days.

## Who can use it

- **Owner, Admin, HR, Branch manager** can view and download. Branch managers see only their branches.
- **Managers** can view the report for the people who report to them, but they do not see the download buttons.

## Common problems

### "Choose an employee"

No employee is picked, so there is nothing to show on screen. Choose one in **Employee**. You can still use **PDF — all employees in filter** or **Separate PDFs (ZIP)** without choosing anyone.

### "No employees match these filters."

Nobody in the chosen branch and department worked during that month. Set **Branch** and **Department** back to *All branches* / *All departments*, or check the month.

### The PDF and Excel buttons are greyed out

**PDF — this employee** and **Excel** work only after you choose an employee. If you see no buttons at all, your role cannot download reports (for example Managers).

### I clicked "PDF — all employees" and nothing downloaded

If you chose more than 25 employees, the file is made in the background. Wait for the notification under the bell icon and click **Download** there. The link expires after 7 days; make the file again if it has expired.

### An employee is not in the list

People who left more than three months ago are not shown. Also check the **Branch** and **Department** filters, and remember that Managers see only their own team.

### A time is yellow

It was entered or corrected by hand (for example through [Manual entry](/help/manual-entry) or an approved [correction](/help/corrections)). The reason is in **Remarks**. All hand-made changes are also listed in the **Manual entry audit** report; see [Other reports](/help/other-reports).

### The numbers changed after I printed

The month was not locked yet. Lock it under **Attendance → Months (lock)** before you print the final copy for signing. See [Month lock](/help/month-lock).
