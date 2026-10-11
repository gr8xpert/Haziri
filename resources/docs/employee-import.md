---
title: Import employees
group: People
order: 20
summary: Add or update many employees at once from an Excel or CSV file: our template, or the staff list you already have.
menu: People → Employees → Import from Excel
panel: employees/import
screens: employee-import=/admin/employees/import
---

## In short

- Go to **People → Employees** and click **Import from Excel**. You can use our template or your own staff list (.xlsx or .csv, up to 5 MB).
- {app} guesses which of your columns is which. You check the guesses, and they are remembered for next time.
- Every row needs an **Employee code**, a **First name** (or one full **Name** column) and a **Joining date**.
- A code that is already in {app} **updates** that employee; a new code **adds** a new employee.
- Nothing is saved until you click **Import … rows**. Rows with errors are skipped; the others can be imported.
- Owner, Admin and HR can import.

## Before you start

1. Add your branches and departments under [Organisation](/help/organisation) first. Branches must already exist. Departments can be created during the import.
2. If you want our layout, click **Download template** at the top right. The file has three sheets:
   - **Employees**: the columns, with one example row (Ali Khan). Gender, employment type, work mode and overtime have drop-down lists.
   - **Instructions**: what to type in each column, and the allowed values.
   - **Codes**: your own branch and department codes, designations and grades, so you can copy them.
3. Delete the example row and put in your staff.

> **Tip:** You do not have to use the template. A staff list from your old system or from accounts works too, as long as it has one heading row on top.

## How to import

![The Import employees page with the file box and Check file button](employee-import.jpg)

1. Click **Import from Excel** on the Employees page.
2. In **Excel (.xlsx) or CSV file: our template, or your own list**, choose your file.
3. Click **Check file**. {app} reads the file. Nothing is saved yet.
4. Look at **Match your columns**. For each of **Your column** you see the value from the **First row** and the **{app} field** it will go into. Change any wrong choice, or pick **— Skip this column —** for columns you do not need.
5. If a yellow box says **Still needed: …**, match a column to each field it names. Required fields have a **\*** in the list.
6. Tick **Create missing departments** if your file has department names that are not in {app} yet.
7. Look at the **Preview**: **New**, **Updates** and **Errors**. The table shows the first 50 rows that will be imported, each with a **New** or **Update** badge.
8. Click **Import … rows** (for example **Import 120 rows**).

You go back to the Employees list with the message "120 employees imported".

> **Suggest the rest with AI:** if your plan includes AI, the button **Suggest the rest with AI (only the headings are sent)** fills in the columns that are still unmatched. Only the column headings go to the AI, never your employees' data. Always check its choices.

## Which columns {app} understands

{app} recognises the common names used in Pakistani staff lists, for example *Emp No*, *Token No*, *P.No* for the code; *Name* or *Employee Name* for the full name; *DOJ* or *Date of Joining* for the joining date; *AC-No*, *User ID* or *Enroll ID* for the machine PIN; *Dept* or *Section* for the department.

| {app} field | What to put in it |
|---|---|
| **Employee code** \* | Your unique employee number. |
| **First name** \* / **Last name** | Or one **Full name** column: "Muhammad Ali Khan" becomes first name *Muhammad Ali*, last name *Khan*. |
| **Joining date** \* | Day first: 15/01/2024. 15-01-2024, 15.01.24, 15 Jan 2024 and 2024-01-15 also work. |
| **Machine PIN** | The user ID on the fingerprint / face machine. |
| **CNIC** | 13 digits, with or without dashes. |
| **Gender** | male / female / other (M and F also work). |
| **Branch (code or name)** | Must already exist in {app}. |
| **Department (code or name)** | Existing, or new if you tick **Create missing departments**. |
| **Designation** / **Grade** | New ones are created by themselves. |
| **Employment type** | permanent, contract, probation, daily_wage or intern. *Permanent* if empty. |
| **Work mode** | office, field or remote. *office* if empty. |
| **Overtime eligible (yes / no)** | yes, y, 1 or haan mean yes. Anything else means no. |
| Others | Card number, Cell phone, Email, Date of birth, Father's / husband's name, Address, City, Payroll ID, Home phone, Emergency contact. |

Fields that are not in the import (for example the manager, kiosk PIN or payroll details) can be filled in later on each employee. See [Employees](/help/employees).

## Updating employees that are already in {app}

Upload a file with the same codes. Those rows show as **Update** in the preview.

- Empty cells in **Father's / husband's name**, **Address**, **City**, **Payroll ID**, **Home phone** and **Emergency contact** do not wipe what is already saved.
- The other columns are saved as they are in the file. An empty **Branch**, **Department** or **Designation** cell clears it, and an empty **Employment type**, **Work mode** or **Overtime eligible** cell sets the default (*Permanent*, *office*, *no*).

> **Tip:** To update only a few fields, put the full, current data in the file (download your staff with the [Employee information report](/help/other-reports) first), or change those employees by hand.

## Common problems

Errors are listed under **Preview** as "Row 5: …". Row numbers are the rows in your file (the heading is row 1). Rows with errors are skipped. Fix them in the file and upload again, or import the good rows now.

### "Could not read the joining date '31/02/2024' (use 15/01/2024)."

The date is not a real date, or {app} cannot tell the day from the month. Type it day first, like 15/01/2024. In Excel, check the cell is not text such as "Jan-24".

### "Unknown branch 'HO'. Add it under Organisation → Branches first."

The branch in the file is not in {app}, or it is spelled differently. Add the branch, or use the code or name shown on the **Codes** sheet of the template.

### "Unknown department 'Stores'. Tick "Create missing departments" to add it."

Tick **Create missing departments** under **Match your columns**. The preview then lists **New departments that will be created**.

### "PIN 47 already belongs to employee E012." or "PIN 47 appears twice in the file."

Two people cannot share a machine PIN. Check the PIN on the machine and in your file, and give each person their own.

### "Code E001 appears twice in the file."

The same employee code is on two rows. Remove the duplicate row or give it the right code.

### "Still needed: Employee code, Joining date."

No column is matched to these fields. Pick the right column in the **{app} field** list, or add the column to your file.

### "Could not read the file", or the file is refused

Only .xlsx and .csv files up to 5 MB are accepted. An old .xls file, or a damaged file, cannot be read. Open it in Excel and use **Save as → Excel Workbook (.xlsx)**, then upload again.

### I click Import and nothing is added

The new employees would take you over your plan's employee limit, so the whole import is cancelled. Check how many active employees your plan allows under [Billing](/help/billing). Mark people who have gone as left, import fewer rows, or upgrade the plan.
