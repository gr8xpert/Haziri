---
title: First-time setup
group: Getting started
order: 20
summary: The order to set up a new company in {app}, and how the Setup assistant reads your written policy and suggests settings.
menu: System → Setup assistant
panel: setup-assistant
screens: first-setup-assistant=/admin/setup-assistant
---

## In short

- A new company starts with one shift (*General 09:00–18:00*, Sunday off), three leave types (Casual, Sick, Annual), Pakistan's public holidays and standard rules. Change them to match your company.
- Set up in this order: company settings → branches and departments → shifts → holidays → leave types → employees → machines or kiosk → user logins → staff app.
- The **Setup assistant** reads your attendance policy (pasted text) and suggests settings. Nothing changes until you tick the changes and click **Apply selected**.
- The Setup assistant appears only if your plan includes the AI assistant, and only the owner or an admin can use it.
- After changing rules, use **Recompute this month** on **Company settings** so the days already worked out follow the new rules.

## What you get at the start

When your company is created, {app} adds:

| What | Starting value |
|---|---|
| Shift | *General (09:00–18:00)*: 15 min grace, 60 min break, Sunday weekly off. It is the default shift for everyone. |
| Leave types | Casual Leave 10 days, Sick Leave 8 days, Annual Leave 14 days a year |
| Holidays | Pakistan's public holidays for the current year |
| Leaving reasons | Resigned, Terminated, Lay-off, Retired, Contract ended |
| Rules | 3 lates = 1 absent, half day deducts 0.5 day, calendar month, leave year from January |
| Login | One owner login (the email you signed up with) |

## Recommended setup order

Work through these steps once. Each step uses what the step before it made.

1. **Company settings** — when your month starts (for example the 21st), when the leave year starts, late and absent rules, and who approves leave. See [Company settings](/help/company-settings).
2. **Organisation** — add your branches (with the office location for phone punching), departments and designations. See [Organisation](/help/organisation).
3. **Shifts** — edit the *General* shift or add your own (start and end time, grace, weekly offs). See [Shifts](/help/shifts). If people work changing shifts, use the [Roster](/help/roster).
4. **Holidays** — check the public holidays, add your company's own. See [Holidays](/help/holidays).
5. **Leave types** — change the quotas or add types like Maternity or Hajj leave. See [Leave types](/help/leave-types).
6. **Employees** — add them one by one or import them from Excel. Give each one their machine PIN (the number they use on the attendance machine). See [Employees](/help/employees) and [Importing employees](/help/employee-import).
7. **Punching** — connect your machines, set up a kiosk tablet, or switch on phone punching. See [Ways to connect machines](/help/devices-overview).
8. **Logins** — add logins for HR and managers, and give staff a login for the phone app. See [Users and roles](/help/users-and-roles).
9. **Staff app** — tell staff to open **/me** on their phone. See [The staff app](/help/staff-app).
10. **Notifications** — if you want WhatsApp or SMS messages, set them up. See [Notifications](/help/notifications).

> **Tip:** Run one or two weeks side by side with your old method. Check the daily reports, fix shifts and PINs, and only then lock the first month and run payroll.

## The Setup assistant

This page appears only if your plan includes the AI assistant. Only the owner or an admin can open it (**System → Setup assistant**).

You paste your written attendance and leave policy, and the AI suggests values for your rules and your default shift. You decide what to keep.

![The Setup assistant: paste your policy, then tick the changes you want](first-setup-assistant.jpg)

### How to use it

1. Copy the text of your policy (from Word, an email or your HR manual). It must be at least 40 characters.
2. Paste it into **Your attendance and leave policy**.
3. Click **Propose settings**. Wait while it shows *Reading the policy...*.
4. A table **Proposed changes** appears:

| Column | What it means |
|---|---|
| **Setting** | The rule it wants to change, for example `setting.lates_per_absent` or `shift.grace_in_min`. |
| **Now** | The value today (crossed out). |
| **Proposed** | The new value. |
| **From the policy** | Why: the part of your policy it used. |

5. Untick every change you do not want. All are ticked at first.
6. Click **Apply selected**. A message says how many changes were applied.

### What it can change

- **Rules:** lates that make one absent, what a half day deducts, what an absent deducts, overtime on or off, overtime rounding, when a day becomes absent, the double-tap window.
- **The default shift:** start and end time, grace time, half-day limits, break, weekly off days.

It does not change leave types, holidays, other shifts or employees. Do those by hand.

> **Tip:** Read every row before you apply. The AI can misread a policy. If a value looks wrong, untick it and set it yourself on **Company settings** or **Shifts**.

After applying, open **System → Company settings** and click **Recompute this month** so the days already worked out use the new rules.

## Common problems

### I can't find the Setup assistant

Your plan does not include the AI assistant, or you are not the owner or an admin. HR cannot open it. You can set everything by hand on **Company settings** and **Shifts**.

### Nothing happens when I click Propose settings

The policy text is too short (under 40 characters) or empty. Paste the full policy and try again.

### "This month's AI budget is used up."

Your company has used its AI allowance for this month. It starts again next month. Meanwhile, set the rules by hand on **Company settings**.

### "AI features are not enabled for your company."

The AI part of your plan is switched off. Ask support to switch it on, or set the rules by hand.

### I applied the changes but old days still show the old result

Rules apply to days worked out from now on. Go to **System → Company settings** and click **Recompute this month**. Locked months never change.

### Employees are marked absent on the wrong weekday

The weekly off days are wrong. Weekly offs come from the employee's shift, or from the branch when the shift has none. Check **Shifts & Holidays → Shifts** and the branch under **Organisation → Branches**.
