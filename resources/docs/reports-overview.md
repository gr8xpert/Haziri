---
title: Reports
group: Reports
order: 10
summary: How the Reports page works: find a report, set the filters, read the table, and download it as Excel or PDF.
menu: Reports → Reports
panel: reports
screens: reports-overview-hub=/admin/reports | reports-overview-filters=/admin/reports/late-register
---

## In short

- All reports are on one page: **Reports → Reports**. They are in five groups: **Daily**, **Monthly**, **Leave**, **People & audit** and **Payroll**.
- Each report has filters at the top. The table updates by itself as soon as you change a filter. There is no "Run" button.
- The **Excel** and **PDF** buttons at the top right download exactly what you see, with the same filters.
- A "month" in reports is your pay-cycle month. If your cycle starts on the 21st, "September 2026" means 21 Aug – 20 Sep.
- Managers see only their own team and cannot download. Payroll reports appear only for people who manage payroll.

## What you see on this page

![The Reports page, with the reports in groups and a line under each name](reports-overview-hub.jpg)

Each card is one report. The line under the name says what it shows and when to use it. Click a card to open the report.

| Group | Reports | Help topic |
|---|---|---|
| **Daily** | Daily attendance, Yesterday & today time, Missing punches, Late & early-out, Absent register | [Attendance reports](/help/attendance-reports) |
| **Monthly** | Employee monthly report (detailed) | [Monthly attendance report](/help/monthly-attendance-report) |
| **Monthly** | Monthly grid – all staff, Monthly totals, Overtime, Punctuality scorecard | [Attendance reports](/help/attendance-reports) |
| **Leave** | Leave register, Leave balances, Monthly leave summary | [Leave reports](/help/leave-reports) |
| **People & audit** | Employee information, Manual entry audit, Exemptions, Visitor log, Device sync | [Other reports](/help/other-reports) |
| **Payroll** | Payroll input, Salary sheet, Income tax register, EOBI & social security challan, Provident fund statement, Gratuity liability | [Payroll reports](/help/payroll-reports) |

You only see the reports you are allowed to open. Some appear only if your plan includes them (for example **Visitor log** or the payroll reports).

> **Tip:** Under **Reports** in the menu you also find **Analytics** (charts) and **Ask {app}** (ask a question in plain words). See [Analytics](/help/analytics) and [Ask {app}](/help/ask-assistant).

## How to run a report

1. Open **Reports → Reports** and click the report you need.
2. Set the filters at the top. Most reports open with dates already filled in (usually from the first day of this pay-cycle month to today).
3. The table appears under the filters. The line above it shows the period, and on the right how many rows there are.
4. Totals, or a legend that explains the codes, appear under the table.

![A report page: filters at the top, the table below](reports-overview-filters.jpg)

If the page says **Choose the filters above**, a required filter is still empty (for example the month or the payroll run). If it says **No data for these filters**, nothing matched: widen the dates or clear a filter.

### The common filters

Most reports have these filters after their own date or month filter. Leave a filter on **All** to include everyone.

| Filter | What it does |
|---|---|
| **Branch** / **Department** / **Designation** | Only staff in that branch, department or job title. |
| **Shift** | Only staff whose shift is that shift *today* (not on the report dates). |
| **Category** | The employment type, for example permanent, contract or daily wage. |
| **Contractor** | Only staff supplied by that contractor. This filter appears only if you have added contractors. |
| **Employee status** | Active, on leave or left. |
| **Employee** | One person. Type part of the code or name to search. |

In many tables the employee's name is a link. Click it to open that person's [detailed monthly report](/help/monthly-attendance-report) for the same month.

## How to download Excel or PDF

1. Set the filters so the table shows what you want.
2. Click **Excel** or **PDF** at the top right.

- **Excel** keeps amounts as plain numbers (no commas), so you can add them up in Excel.
- **PDF** is ready to print on A4. Wide reports print in landscape. It shows your company name at the top and the date and time it was made at the bottom.
- The file is named after the report and the period, for example `monthly-totals-september-2026.xlsx` or `daily-attendance-tuesday-15-sep-2026.pdf`.

> **Who can download:** Owner, Admin, HR and Branch manager. Managers can view reports for their team but do not see the **Excel** and **PDF** buttons.

## Who can see what

| Role | Reports |
|---|---|
| **Owner, Admin** | All reports. |
| **HR** | All reports. |
| **Branch manager** | Attendance, leave and people reports (including **Visitor log**), for their own branches only. No payroll reports. |
| **Manager** | Attendance and leave reports for the people who report to them only. No downloads, no **Visitor log** and no payroll reports. |
| **Receptionist, Employee** | No Reports page. Employees see their own attendance in the [staff app](/help/staff-app). |

The payroll reports need payroll permission (Owner, Admin, HR) and payroll in your plan.

## Live numbers and locked months

Attendance reports show the latest numbers. If someone fixes a punch or approves a leave, the report changes the next time you open it.

When a month is locked under **Attendance → Months (lock)**, its numbers stop changing. **Monthly totals** and **Payroll input** say in the line above the table whether the month is locked or still open. Lock the month before you print the final copy for salaries. See [Month lock](/help/month-lock).

## Common problems

### "Choose the filters above"

A filter that the report needs is empty, for example the month, the leave type or the payroll run. Fill it in and the table appears straight away.

### "No data for these filters"

Nothing matched. Check the dates, and set filters such as **Branch**, **Department** or **Employee** back to **All**. Remember that the **Shift** filter uses today's shift, not the shift on the report dates.

### I can't see the Excel and PDF buttons

You do not have permission to download reports. Managers can only view them. Ask the owner or HR to download the report, or to change your role under **System → Users**. See [Users and roles](/help/users-and-roles).

### A report is missing from the Reports page

Either your role cannot open it, or your plan does not include it. Payroll reports need payroll permission and payroll in your plan. **Visitor log** needs the visitor log in your plan and full report access (not a Manager's team access). See [Billing](/help/billing).

### The month in the report does not start on the 1st

Reports follow your pay cycle. If salaries run from the 21st to the 20th, the month "September 2026" is 21 Aug – 20 Sep. The pay cycle is set in [Company settings](/help/company-settings).

### The numbers changed after I printed the report

The month was not locked, and someone changed attendance or leave after you printed. Lock the month under **Attendance → Months (lock)** before you print the final copy.
