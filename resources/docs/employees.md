---
title: Employees
group: People
order: 10
summary: Add your staff, keep their details up to date, give them a shift and a branch, and mark them as left when they go.
menu: People → Employees
panel: employees
screens: employees-list=/admin/employees | employees-form=/admin/employees/create
---

## In short

- Every person whose attendance you track must be an employee in {app}. Only **Code**, **First name** and **Joined on** are required.
- **Machine PIN** links the employee to the fingerprint or face machine. It must be the same number as the user ID on the machine, or their punches will not reach them.
- The list opens on **Active** employees only. Use the **Status** filter to see people who are on leave or have left.
- When someone leaves, do not delete them. Fill in **Left on** (or use **Mark as left**) so their old attendance and payroll stay in the reports.
- To add many people at once, use **Import from Excel** (see [Import employees](/help/employee-import)).
- Owner, Admin and HR can add and change employees. Managers, branch managers and reception can only look.

## What you see on this page

![The Employees list, with code, name, PIN, branch, department and status](employees-list.jpg)

Each row is one employee:

| Column | What it means |
|---|---|
| **Code** | Your own employee number, for example E001. Each code is used once. |
| **Name** | First and last name. |
| **PIN** | The **Machine PIN**: the user ID on the fingerprint or face machine. A dash means no PIN yet. |
| **Branch** / **Department** | Where the person works. |
| **Employment type** | Permanent, Contract, Probation, Daily wage or Intern. |
| **Status** | Active (green), On leave (yellow) or Left (grey). |
| **Joined on** | The joining date. |

More columns (**Designation**, **Grade**, **Payroll ID**) are hidden. Turn them on with the columns button above the table.

Use the search box to find someone by code, name or PIN. Use the **filters** button to show one **Branch**, **Department**, **Designation**, **Grade**, **Contractor**, **Status** or **Employment type**. The **Deleted records** filter shows employees who were deleted.

> **Branch managers** see only the employees of their own branches.

## Add one employee

1. Click **New employee** at the top right.
2. Fill in the **Main details**:
   - **Code**: your employee number (up to 30 characters, not used before).
   - **Machine PIN**: the user ID enrolled on the fingerprint/face machine. You can leave it blank and add it later.
   - **First name** and **Last name**.
   - **Department** and **Designation** (job title).
   - **Joined on**: the first working day.
   - **Shift**: leave it blank to use the company default shift.
   - **Location / branch**: shown only if you have more than one branch. With one branch, the employee is put in it by itself.
   - **Employment type** (default *Permanent*) and **Status** (default *Active*).
3. Add a **Photo** if you like. This box appears only if your plan includes the kiosk tablet or mobile punch.
4. Open **More details** for anything else (all optional). See the table below.
5. Click **Create**.

![The new employee form, with Main details, Photo and More details](employees-form.jpg)

The employee is counted from the **Joined on** date. Days before that date are not marked absent.

> **Tip:** Fields marked with a red **\*** are required. Everything in **More details** can be filled in later.

### What is in More details

| Part | Fields |
|---|---|
| **Personal** | Father's / husband's name, **CNIC** (13 digits, no dashes), Gender, Date of birth, City, Address. **Payroll ID** appears only if your plan includes payroll. |
| **Contact** | Cell phone, Home phone, Email, Emergency contact. The cell phone is used for the phone sign-in code and for WhatsApp / SMS messages. |
| **Employment** | Grade, **Manager**, Contractor (only if you have contractors), Probation ends on, Contract ends on, **Left on**, Leaving reason, Office phone, Office ext., Official mobile. |
| **Attendance** | Card no, **Kiosk PIN (4 digits)** (and **Remove kiosk PIN** once one is set), **Work mode**, **Can punch from phone**, Overtime eligible, Late deduction exempt, **Earns leave**. |
| **Payroll & statutory** | NTN, No income tax, Provident fund member, EOBI registered, EOBI number, PESSI / SESSI registered, Social security number. Shown only if your plan includes tax, EOBI / social security or provident fund. |

Some fields change how {app} treats the person:

- **Manager**: this person approves the employee's leave and corrections first. See [Leave requests](/help/leave-requests).
- **Work mode**: *Office*, *Field* or *Remote*. *Field* and *Remote* staff can punch from the phone anywhere; they are never limited by the office area or the office internet. See [Phone punching](/help/phone-punching).
- **Can punch from phone** (on by default): off means the person must punch on the machine or kiosk. Shown only if your plan includes mobile punch.
- **Kiosk PIN (4 digits)**: a second, secret PIN asked on the kiosk tablet after the Machine PIN. Shown only if your plan includes the kiosk. Once a PIN is set the box stays empty: leave it empty to keep the PIN, type a new one to change it, or turn on **Remove kiosk PIN** and save to take it away. See [Kiosk](/help/kiosk).
- **Overtime eligible**: off means extra hours are not counted as overtime.
- **Late deduction exempt**: no late roll-up or late adjustment for this person.
- **Earns leave**: off means no leave builds up, for example for daily wagers.

## Change an employee

Click the row (or **Edit**), change what you need, and click **Save changes**. Changes are recorded in the [Audit log](/help/audit-log).

The **Shift** field is only on the new employee form. To change a shift later, use **Assign shift** (below) or the [Roster](/help/roster).

## Do the same thing for many employees

Tick the boxes on the left of the rows, then open the bulk actions menu above the table:

- **Assign shift**: pick the **Shift**, the **Effective from** date and, if it is only for a while, **Effective to** (blank = until changed). See [Shifts](/help/shifts).
- **Move to branch**: pick the new branch.
- **Mark as left**: give the **Left on** date and a **Reason**. The status becomes *Left*.

## When someone leaves

Open the employee and fill in **Left on** (under **More details → Employment**). A **Leaving reason** box appears; pick one (reasons are set under [Organisation](/help/organisation)). Then set **Status** to *Left* and save. Or use **Mark as left** from the list, which does both.

People who have left:

- disappear from the list (until you filter for *Left*), the kiosk and the face enrolment list,
- stay in all old reports, attendance and payroll,
- do not count towards your plan's employee limit.

If your plan includes final settlement, see [Final settlements](/help/final-settlements).

> **Tip:** Use **Delete** (on the edit page) only for a person added by mistake. A deleted employee can be brought back: filter on **Deleted records**, open the person and click **Restore**.

## Give an employee the staff app

The staff app (**/me**) needs a login. Go to **System → Users & roles**, click **New user**, give the person's name, email and a password, choose the role **Employee**, and pick them in **Linked employee**. See [Users and roles](/help/users-and-roles) and [Staff app](/help/staff-app).

## Common problems

### "The code has already been taken."

Another employee already has this code. Codes must be different, even for people who have left or were deleted. Check the **Status** and **Deleted records** filters to find the other person.

### "The Machine PIN has already been taken."

Two employees cannot share a machine PIN. Find the other person by searching the PIN (also with the **Deleted records** filter on). If that person has left, give the PIN to the new person only after you remove it from the old one.

### "Your plan allows 25 active employees. Mark someone as left or upgrade the plan."

You have reached the employee limit of your plan (the number shows your limit). Mark people who have gone as left, or upgrade under [Billing](/help/billing).

### I can't find an employee in the list

The list shows only **Active** employees at first. Open the **filters** and set **Status** to *On leave* or *Left*, or turn on **Deleted records**. Branch managers see only their own branches.

### An employee punches on the machine but has no attendance

The **Machine PIN** is blank or different from the user ID on the machine. Fix the PIN on the employee. Punches with an unknown PIN wait under [Unmapped PINs](/help/unmapped-pins); when you save the right PIN, the waiting punches with it are attached to the employee and the days are worked out again.

### The CNIC is refused

Type the 13 digits only, without dashes or spaces, for example 3520212345671.

### I can't see the Photo box, Kiosk PIN or Can punch from phone

These fields appear only when your plan includes the kiosk tablet or mobile punch. The **Payroll & statutory** part appears only when your plan includes tax, EOBI / social security or provident fund.
