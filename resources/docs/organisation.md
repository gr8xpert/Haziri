---
title: Organisation
group: Company setup
order: 30
summary: Set up branches, departments, designations, grades, contractors and leaving reasons, the lists you choose from on each employee.
menu: Organisation → Branches / Departments / Designations / Grades / Contractors / Leaving reasons
panel: branches | departments | designations | grades | contractors | separation-reasons
screens: organisation-branches=/admin/branches | organisation-branch-new=/admin/branches >> New branch | organisation-departments=/admin/departments
---

## In short

- The **Organisation** menu holds the lists you pick from on each employee: branch, department, designation, grade, contractor and leaving reason.
- A **branch** is an office, factory or shop. Its location and office internet addresses are used for phone punching; its weekly offs are used when a shift has none.
- Codes are filled in from the name; you can change them. Codes must be unique.
- Deleting an item does not delete employees: they just lose that branch, department, grade and so on. Deleting a **grade** also deletes the leave rules set for that grade.
- Owner, Admin and HR can manage these lists.

## Branches

A branch is one place where people work, for example *Head Office* or *Faisalabad Factory*.

![The Branches list](organisation-branches.jpg)

The list shows **Code**, **Name**, **Employees** (how many are in the branch) and **Geofence** (the distance for phone punching).

### Add a branch

1. Go to **Organisation → Branches** and click **New branch**.
2. Type the **Name**. The **Code** fills in by itself (you can change it, up to 20 characters).
3. Fill in the **Address**.
4. For phone punching, fill in **Latitude** and **Longitude** of the office. Tip: in Google Maps, long-press the office and copy the two numbers shown.
5. **Geofence radius m**: how far from that point (20 to 5,000 metres) a phone punch is accepted. Default 150.
6. **Office internet addresses** (optional): the public internet address(es) of the office connection, for example `39.45.10.22`. Type one and press Enter. When filled in, phone punches from any other connection wait in **Attendance → Punch approvals**, even inside the geofence.
7. **Timezone**: leave empty to use the company timezone (Pakistan). Only change it for a branch abroad.
8. **Weekly off days**: tick the days off, for example Sunday, or Friday for a branch that closes on Friday. Used only for staff whose shift has no weekly offs of its own. Default: Sunday.
9. Click **Create**.

![The New branch form](organisation-branch-new.jpg)

See [Punching from the phone](/help/phone-punching) for how the geofence and office addresses are checked.

> **Tip:** To move many employees to a branch at once, select them in **People → Employees** and use the bulk action **Move to branch**.

## Departments

A department is a part of the company, like *Accounts* or *Production*.

![The Departments list](organisation-departments.jpg)

1. **Organisation → Departments** → **New department**.
2. Type the **Name**; the **Code** fills in.
3. Optional: **Parent department** (for example *Packing* under *Production*) and **Head** (the department head, an employee).
4. Click **Create**.

The list shows **Code**, **Name**, **Parent**, **Head** and **Employees**. Departments are used to filter reports and lists.

## Designations

A designation is a job title, like *Accountant* or *Machine Operator*.

1. **Organisation → Designations** → **New designation**.
2. Type the **Name**. **Grade** here is a free text note (up to 20 characters), for example *G-5*; it is not linked to the **Grades** list.
3. Click **Create**.

## Grades

A grade is a pay or staff level, like *Officer* or *Worker*. Grades matter for leave: a leave type can give different days per year to different grades (see [Leave types](/help/leave-types), *Entitlement by grade / category*).

1. **Organisation → Grades** → **New grade**.
2. Type the **Name**; the **Code** fills in.
3. Click **Create**, then set each employee's grade on their profile.

> **Warning:** Deleting a grade also deletes the leave rules set for that grade on your leave types. Employees in that grade then get the leave type's normal quota. Before you confirm, the delete window says how many leave entitlements and how many employees are affected.

## Contractors

A contractor is a labour supplier who provides some of your staff.

1. **Organisation → Contractors** → **New contractor**.
2. Type the **Name** (the **Code** fills in) and **Phone**.
3. Keep **Active** on. Click **Create**.

Once at least one contractor exists, the employee form shows a **Contractor** field, and reports get a **Contractor** filter, so you can print attendance for one contractor's people.

## Leaving reasons

Leaving reasons are the choices for why someone left, for example *Resigned* or *Retired*. Five are added for you at the start.

1. **Organisation → Leaving reasons** → **New leaving reason**.
2. Type the **Name** and keep **Active** on. Click **Create**.

Switch **Active** off to hide a reason from the list without losing it on past leavers. The reason is picked when you mark an employee as left (see [Employees](/help/employees)).

## Who can do it

Owner, Admin and HR. Managers, branch managers and receptionists do not see the Organisation menu.

## Common problems

### "The code has already been taken"

Another branch (or department, grade, contractor) already uses this code. Change the code; for example add a number: `LHR2`.

### Phone punches go to Punch approvals although the employee is in the office

The phone is not on an address listed in **Office internet addresses** (for example it is on mobile data, or the office internet address changed). Check the address with your internet provider, or empty the field to rely on the geofence only.

### Every phone punch from the office waits for approval as "outside the geofence"

The branch **Latitude** / **Longitude** are wrong, or **Geofence radius m** is too small for your building. Check the location in Google Maps and try 200–300 m for a large site.

### "The latitude field must be between -90 and 90"

Latitude and longitude were swapped or mistyped. For Pakistan, latitude is about 24 to 37 and longitude about 61 to 77.

### I deleted a department and the employees have no department now

That is expected: deleting an item never deletes employees, it only clears the field. Create the department again and set it back on the employees (for example with an Excel import).

### The Contractor field is missing on the employee form

It appears only after you add at least one contractor under **Organisation → Contractors**.
