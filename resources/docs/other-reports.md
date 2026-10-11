---
title: People and audit reports
group: Reports
order: 60
summary: The employee list for printing, the record of times entered by hand, excused days, visitors, and how well each attendance machine is sending punches.
menu: Reports → Reports → People & audit
panel: reports/employee-information | reports/manual-entry-audit | reports/exemptions | reports/visitor-log | reports/device-sync
screens: other-reports-device-sync=/admin/reports/device-sync | other-reports-manual-entry-audit=/admin/reports/manual-entry-audit
---

## In short

- **Employee information**: the staff list with personal and job details (CNIC, address, date of birth, joining date), for printing or Excel.
- **Manual entry audit**: every time entered by hand and every punch removed, with who did it, when and why.
- **Exemptions**: days excused for everyone or a branch or department (for example a strike or heavy rain).
- **Visitor log**: visitors in a date range. It appears only if your plan includes the visitor log.
- **Device sync**: punches per machine, and days with no punches, so you can spot a machine that stopped sending.

## Employee information

**Shows:** one line per employee: **Id**, **Employee name**, **Father / husband**, **CNIC** (written as 12345-1234567-1), **Address** (with city), **D.O.B**, **Joining date**, **Grade**, **Department**, **Designation**, **Category** and **Status**. Under the table: the number of **Employees**.

**Use it:** to print a staff list for a file or an inspection, or to get all details in Excel.

**Filters:** the common filters only (branch, department, designation, shift, category, contractor, employee status, employee). Set **Employee status** to *Active* to leave out people who have left.

To change any detail, open the employee under **People → Employees**. See [Employees](/help/employees).

## Manual entry audit

**Shows:** every punch that was entered by hand, and every punch that was removed, in the date range:

| Column | What it means |
|---|---|
| **Date** / **Time** | The day and time of the punch. |
| **Code** / **Name** | The employee. |
| **Dir.** | In or out. |
| **Reason** | The reason given when it was entered, or when it was removed. |
| **Entered by** | The user who entered it. Empty for punches that came from a machine or phone. |
| **Entered at** | When it was saved in {app}. |
| **Removed** | When it was removed. Empty if it is still in use. |

![Manual entry audit with the reason and who entered each time](other-reports-manual-entry-audit.jpg)

**Use it:** to check that hand-made changes are honest, for example before an audit or when a manager questions an employee's attendance. Hand-entered times are also shown in yellow on the [Employee monthly report](/help/monthly-attendance-report).

**Filters:** **From**, **To** and the common filters.

See [Manual entry](/help/manual-entry) and [Corrections](/help/corrections) for how times are entered by hand.

## Exemptions

**Shows:** each excused day in the date range: **Date**, **Day**, **Exemption type**, **Reason**, **Applies to** (*Whole company*, *Branch: …* or *Department: …*), **Entered by** and **Created on**. Under the table: the number of **Days**.

**Use it:** to remember why attendance was excused on a day, for example when an employee asks why a day was not marked absent.

**Filters:** **From** and **To** only. It opens on 1 January of this year to today.

Exemptions are added under **Shifts & holidays → Exemptions**. See [Exemptions](/help/exemptions).

## Visitor log

This report appears only if your plan includes the visitor log, and only for users with full report access: Owner, Admin, HR and Branch manager (their branches' visitors only). Managers and Receptionists do not see it; reception uses the [Visitors](/help/visitors) page instead.

**Shows:** each visitor who checked in during the date range: **Badge**, **Name**, **Phone**, **Company**, **Visiting** (the employee they came to see), **Purpose**, **In** (date and time), **Out** and **Minutes** (how long they stayed). Visitors who have not checked out have an empty **Out**.

**Use it:** for security records, or to see who visited a person on a day.

**Filters:** **From** and **To** only. See [Visitors](/help/visitors).

## Device sync

**Shows:** one line per attendance machine:

| Column | What it means |
|---|---|
| **Device** / **Serial** | The machine's name and serial number. |
| **Type** | How it connects, for example cloud (ADMS) or sync agent. |
| **Status** | The machine's current status. |
| **Last sync** | When it last sent punches, for example *2 hours ago*, or *never*. |
| **Punches** | Total punches it sent in the date range. |
| **Avg / day** | Punches per day on average. |
| **Days without data** | Days in the range with no punches at all from this machine. |

![Device sync report with punches and days without data for each machine](other-reports-device-sync.jpg)

**Use it:** once a week. A machine with many **Days without data** (that are working days), or a **Last sync** of several days ago, has probably stopped sending. Weekly offs and holidays will also count as days without data when nobody punches, so compare with your calendar.

**Filters:** **From** and **To** only. All machines are listed.

To fix a machine, see [Attendance machines](/help/zkteco-machines), the [Sync agent](/help/sync-agent) or [USB import](/help/usb-import).

## Who can see these reports

Owner, Admin, HR and Branch manager can view and download them (Branch managers see only their branches' staff in Employee information and Manual entry audit). Managers can view them for their own team, without downloads, except Visitor log; see above.

## Common problems

### Visitor log is not on the Reports page

Your plan does not include the visitor log, or your role does not have full report access (Owner, Admin, HR and Branch manager do; Managers and Receptionists do not). See [Users and roles](/help/users-and-roles).

### Device sync shows "never" for a machine

The machine has never sent a punch to {app}. Check its cloud server settings, or that the sync agent is running. See [Attendance machines](/help/zkteco-machines).

### A machine has many "Days without data"

It stopped sending, it was switched off, or nobody used it (for example a branch closed on Sundays). If staff punched but nothing arrived, take the punches with [USB import](/help/usb-import) and fix the connection.

### Manual entry audit shows a removed machine punch

Someone deleted a punch that came from a machine, for example a double punch. The **Reason** column shows why. If it was removed by mistake, enter the time again with [Manual entry](/help/manual-entry).

### CNIC or address is empty in Employee information

The detail is not filled in on the employee's profile. Open the employee under **People → Employees** and add it, or fill it for many people at once with [Employee import](/help/employee-import).
