---
title: Welcome to {app}
group: Getting started
order: 10
summary: What {app} does, how attendance data moves from the machine to the payslip, how to log in, and who can do what.
menu: User menu (your name, top right) → Profile / Switch company
panel: profile | switch-company
screens: welcome-panel=/admin
---

## In short

- {app} collects punches (from attendance machines, a kiosk tablet or staff phones), works out each employee's day, and turns the month into reports and payroll.
- HR, managers and the owner use the admin panel at **/admin**. Staff use the phone app at **/me**. Both are on your company's own address.
- What you see in the left menu depends on your **role** (Owner, Admin, HR, Manager, Branch manager, Receptionist) and on your plan.
- Change your own name, email or password under your name (top right) → **Profile**.
- The **?** button at the top right opens the help for the page you are on.

## What {app} does

{app} keeps your company's attendance, leave and (if your plan includes it) payroll in one place. Each company has its own address, for example `https://yourcompany.…/admin`, and its own data. Nobody from another company can see it.

![The admin panel after logging in: the menu on the left, today's numbers on the right](welcome-panel.jpg)

## How the data flows

Every number in a report comes from the same chain. If something looks wrong in a report, walk back along this chain.

1. **Punch** — an employee punches in or out. A punch is one time stamp: who, when, and from where. Punches come from:
   - fingerprint or face **machines** (see [Ways to connect machines](/help/devices-overview)),
   - a **kiosk** tablet at the gate (see [Kiosk tablets](/help/kiosk)),
   - the **staff app** on the employee's own phone (see [Punching from the phone](/help/phone-punching)),
   - a **manual entry** by HR (see [Manual entry](/help/manual-entry)).
   All punches are listed in **Attendance → Punch log**.
2. **Day** — {app} matches the punches to the employee's shift and works out the day: present, late, half day, absent, missing punch, overtime. See [How attendance is worked out](/help/how-attendance-works).
3. **Leave and holidays** — approved leave, public holidays and weekly offs fill the days when the employee did not come. See [Leave requests](/help/leave-requests) and [Holidays](/help/holidays).
4. **Corrections** — mistakes are fixed with corrections and manual entries, and the day is worked out again.
5. **Month lock** — when the month is checked, HR locks it under **Attendance → Months (lock)**. After that nobody can change it by accident. See [Locking a month](/help/month-lock).
6. **Reports** — daily, monthly and leave reports read the worked-out days. See [Reports](/help/reports-overview).
7. **Payroll** — the payroll run takes the locked month (days worked, absents, lates, leave, overtime) and makes the salary sheet and payslips. See [Payroll overview](/help/payroll-overview).

> **Tip:** If a day is wrong, fix the cause (a missing punch, a wrong shift, a leave not approved), not the report. The day is then worked out again and every report follows.

## Logging in

1. Open your company's address and go to **/admin**.
2. Enter your **Email address** and **Password**, and click **Sign in**.
3. Forgot your password? Click **Forgot password?** on the login page. You get an email with a link to set a new one.

Your login is made by the owner or an admin under **System → Users & roles**. See [Users and roles](/help/users-and-roles).

Staff do **not** use /admin. They open **/me** on their phone and sign in with their email and password, or with their mobile number and a 6-digit code. See [The staff app](/help/staff-app).

## The menu

The left menu is grouped. You only see the pages your role allows and your plan includes.

| Group | What is in it |
|---|---|
| **Attendance** | Punch log, punch approvals, punch photos, manual entry, corrections, month lock, visitors, anomalies |
| **People** | Employees, face enrolment, announcements |
| **Shifts & Holidays** | Roster, shifts, shift swaps, timing overrides, holidays, exemptions |
| **Leave** | Leave requests, leave balances, who's off, leave types |
| **Devices** | Machines, kiosks, approved tablets, USB import, unmapped PINs |
| **Reports** | All reports, analytics, the AI assistant |
| **Payroll** | Salary structures, advances, bonuses, payroll runs, final settlements, tax slabs, payroll rules |
| **Organisation** | Branches, departments, designations, grades, contractors, leaving reasons |
| **System** | Billing, company settings, WhatsApp & SMS, setup assistant, branding, users, notification texts and log, API, audit log |

Click a group name to open or close it. On a computer you can also collapse the whole menu to get more space. The bell at the top right shows messages {app} sends you inside the panel, for example a new leave request.

## Roles: who can do what

Each login has one or more roles. A role is a set of permissions.

| Role | What they can do |
|---|---|
| **Owner** | Everything, including billing and deleting data. Only an owner can make another owner. |
| **Admin** | Everything except billing and deleting data. |
| **HR** | Employees, attendance, manual entries, shifts, holidays, leave (final approval), corrections (final approval), locking months, reports and exports, payroll, announcements, audit log. Not: users, company settings, devices, unlocking a month. |
| **Manager** | Sees their own team, applies for leave for them, gives the first approval on leave and corrections, sees team reports. |
| **Branch manager** | Like HR for their own branches: attendance, manual entries, final approval of leave and corrections, reports, USB import. Set their branches under **Users & roles**. |
| **Receptionist** | Sees attendance and employees, makes manual entries, USB import, visitors, kiosks. |
| **Employee** | Uses the staff app (**/me**) only: own attendance, leave and corrections. Cannot open /admin. |

See [Users and roles](/help/users-and-roles) to add logins and change roles.

## Your profile and password

Click your name or initials at the top right, then **Profile**. Here you can change your **Name**, **Email address** and password (**New password** and **Confirm new password**). When you change the email or password, a **Current password** box appears: type your old password there. Click **Save changes** when done.

> **Tip:** Your email is your login name. If you change it, use the new email the next time you sign in.

## Switching company

If your email has a login in more than one company (for example a group with two firms), your user menu shows **Switch company**. It lists the other companies. Click one to open it; you are signed in there straight away, with the role you have in that company.

## Common problems

### "These credentials do not match our records"

The email or password is wrong, or the login was switched off. Check for spaces and Caps Lock, then use **Forgot password?**. If that does not help, ask your owner or admin to check your login under **System → Users & roles** (the *Is active* switch must be on).

### I can log in to the staff app but not to /admin

Your login has only the **Employee** role. The admin panel is for Owner, Admin, HR, Manager, Branch manager and Receptionist. Ask the owner or an admin to add the right role.

### A page I need is missing from the menu

Your role does not allow it, or your plan does not include that part of {app} (for example payroll, kiosks or the AI assistant). Ask your owner. The owner can see the plan on the **Billing** page and contact support to change it.

### "Your free trial has ended. Please contact support to continue."

The trial period is over and the company is paused. Your data is kept. Contact support to choose a plan.

### "This account is suspended. Please contact support."

Usually an invoice was not paid in time. Contact support. Once the payment is confirmed, the account opens again. See [Billing](/help/billing).

### I don't see Switch company

Switch company appears only when your email is linked to another company and you have an active login there with the same email. Ask support to link the companies, and the other company's admin to add a login with your email.
