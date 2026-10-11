---
title: Users and roles
group: Company setup
order: 20
summary: Add logins for HR, managers and staff, choose what each one may do, link a login to an employee, and switch a login off.
menu: System → Users & roles
panel: users
screens: users-and-roles-list=/admin/users | users-and-roles-new=/admin/users >> New user
---

## In short

- A **user** is a login. An **employee** is a person on your staff list. They are separate; link them with **Linked employee**.
- The **role** decides what a login can do: Owner, Admin, HR, Manager, Branch manager, Receptionist or Employee.
- Staff who should use the phone app (**/me**) need a user with the **Employee** role and **Linked employee** set to themselves.
- Managers approve leave for the people who report to them, so a manager's login must be linked to their own employee record.
- To stop someone logging in, switch **Is active** off. Only the owner and admins can open this page; only an owner can make another owner.

## What you see on this page

![The Users & roles list](users-and-roles-list.jpg)

| Column | What it means |
|---|---|
| **Name** / **Email** | The person and their login email. |
| **Roles** | Their roles, as coloured labels. |
| **Branches** | Shown if your plan includes branch managers: the branches this login is limited to. *All branches* = no limit. |
| **Is active** | A tick = can log in. A cross = switched off. |

Search by name or email at the top of the list. Click **Edit** on a row to change a login.

## Roles

| Role | Opens | What they can do |
|---|---|---|
| **Owner** | Admin panel | Everything, including **Billing** and deleting data. |
| **Admin** | Admin panel | Everything except billing and deleting data. Manages users and company settings. |
| **HR** | Admin panel | Employees, attendance, manual entries, shifts, holidays, leave types and balances, final approval of leave and corrections, month lock, reports and exports, payroll, announcements, audit log. |
| **Manager** | Admin panel | Their own team only: sees team attendance and reports, applies for leave for them, gives the first approval of leave and corrections. |
| **Branch manager** | Admin panel | Like HR, but for their branches only (set **Limit to branches**): attendance, manual entries, final approval of leave and corrections, reports, USB import. No payroll, no settings. |
| **Receptionist** | Admin panel | Sees attendance and employees, makes manual entries (today and yesterday only), USB import, visitors and kiosks. |
| **Employee** | Staff app only | Own attendance, leave requests and corrections in **/me**. Cannot open /admin. |

A login can have more than one role; it gets everything each role allows.

> **Who is whose manager:** this is set on the employee's profile (**People → Employees**, field *Manager*), not here. The manager's own login must be linked to the manager's employee record, or {app} cannot tell who reports to whom.

## How to add a login

1. Click **New user** at the top right.
2. Fill in **Name** and **Email**. The email is the login name and must be unique.
3. Fill in **Phone** if the person will sign in to the staff app with a code sent by SMS. Each phone number can be used by one login only.
4. Type a **Password** of at least 8 characters. Click the eye icon to see what you typed. Give the password to the person; they can change it under **Profile**.
5. Pick one or more **Roles**.
6. If your plan includes branch managers: in **Limit to branches**, pick the branches this person may see. Leave it empty for all branches. Owners and admins always see everything.
7. In **Linked employee**, pick the person's own employee record (code — name). Needed for the staff app and for managers.
8. **Locale**: *English* or *اردو* (Urdu). This is the language of the staff app and of messages {app} sends this person.
9. Leave **Is active** on.
10. Click **Create**.

![The New user form](users-and-roles-new.jpg)

## Give staff the phone app

For each employee who should use **/me**:

1. **New user** → **Name**, **Email**, and a **Password** (or a **Phone** for sign-in by SMS code).
2. **Roles**: *Employee*.
3. **Linked employee**: the employee themselves.
4. **Create**, then tell them the address and password. See [The staff app](/help/staff-app).

> **Tip:** An HR officer or manager who is also an employee needs only one login: give it their admin role **and** link it to their employee record. They can then use both /admin and /me.

## Change or remove a login

- **Change a role or branch:** click **Edit**, change **Roles** or **Limit to branches**, and save.
- **Reset a password:** click **Edit**, type a new **Password**, and save. Leave the field empty to keep the old password.
- **Stop access:** click **Edit** and switch **Is active** off. The person can no longer log in to /admin or /me. Their history stays.

There is no delete button: switching a login off keeps the record of who did what in the [Audit log](/help/audit-log).

## Common problems

### "The email has already been taken"

Another login already uses that email, maybe a switched-off one. Search the list for the email and edit that login instead.

### "The phone has already been taken"

Another login uses this phone number. Each number can belong to one login only, because it is used to send the sign-in code.

### I can't pick the Owner role

Only an owner can give the Owner role. Admins see every other role.

### A manager can't see their team or approve leave

The manager's login has no **Linked employee**, or the team members do not have this person set as *Manager* on their employee profile. Fix both, and the manager sees their team.

### An employee can't sign in to the staff app

Check four things: the login exists, it is **Is active**, it has a **Linked employee**, and the email or phone is the one they type. For sign-in by code, SMS must be set up under **System → WhatsApp & SMS**.

### A branch manager sees every branch

**Limit to branches** is empty, which means all branches. Edit the login and pick their branches. If the field is missing, your plan does not include branch managers.
