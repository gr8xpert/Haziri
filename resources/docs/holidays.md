---
title: Holidays
group: Shifts & holidays
order: 50
summary: Keep the list of public, company and optional holidays, so nobody is marked absent on those days.
menu: Shifts & Holidays → Holidays
panel: holidays
screens: holidays-list=/admin/holidays | holidays-load=/admin/holidays >> Load Pakistan holidays
---

## In short

- On a holiday, staff who do not come are shown as *Holiday*, not absent. Anyone who works on a holiday gets all those hours as overtime (if overtime is on for them).
- **Load Pakistan holidays** adds the public holidays for a year in one click. Islamic holidays come in as **Provisional** (moon sighting); confirm or move them when the government announces the dates.
- A holiday can be for all branches or for one branch only, and paid or unpaid.
- An **Optional** holiday is a day off only for the people who take it. Choose them with **Who takes it**, or staff take it themselves in the staff app.
- The number next to *Holidays* in the menu is how many provisional holidays in the next 30 days still need confirming.
- Only the Owner, Admin and HR can use this page.

## What you see on this page

![The Holidays list for this year, with provisional Eid dates marked](holidays-list.jpg)

| Column | What it means |
|---|---|
| **Date** | The day, for example *Mon, 14 Sep 2026*. |
| **Name** | For example *Independence Day*. |
| **Type** | *Gazetted* (public holiday), *Company* (your own) or *Optional*. For optional holidays, a line shows how many people are taking it. |
| **Branch** | *All*, or the one branch it is for. |
| **Paid** | A tick means it is a paid day off. |
| **Provisional** | A warning sign means the date still needs confirming. |

The list shows the current year. Use the **filters** button to see last or next year, or only provisional holidays.

## Load the public holidays for a year

1. Click **Load Pakistan holidays**.
2. Pick the **Year** (next year is chosen for you).
3. Confirm. {app} tells you how many holidays were added.

![The Load Pakistan holidays window](holidays-load.jpg)

This adds the fixed national days (Kashmir Day, Pakistan Day, Labour Day, Youm-e-Takbeer, Independence Day, Iqbal Day, Quaid-e-Azam Day) and, where {app} knows them, the expected Eid, Ashura and Eid Milad un Nabi dates. Dates that already have an all-branch holiday are skipped, so you can run it again safely.

> **Tip:** Islamic dates depend on the moon. When the federal notification is out, **Edit** the provisional holiday to the right date, or click **Confirm** if the date is right.

## Add one holiday

1. Click **New holiday**.
2. Fill in **Date** and **Name**.
3. **Type** — *Gazetted*, *Company* or *Optional*.
4. **Branch** — leave on *All branches*, or pick one.
5. **Paid** — on by default. Off means it is still a day off, but not paid.
6. **Needs confirmation** — switch on if the date may still move (moon sighting).
7. Click **Create**. A date can hold only one holiday for the same branches (one for all branches, and one per branch).

## Add several days at once

For Eid or a long closure, click **Add holiday range**. Fill in **Name**, **From**, **To**, **Type**, **Branch** and **Paid**. {app} saves one holiday for every day in the range. If a day already has a holiday for the same branch, it is replaced.

## Optional holidays

An optional holiday is a normal working day for everyone, except the people who take it.

- **HR picks:** click **Who takes it** on the holiday's row and choose the employees.
- **Staff pick:** in the staff app, staff see optional holidays for the next 90 days and can take or give one back until the day itself.

Their attendance for that day is updated straight away.

## What happens on a holiday

- Staff who do not punch show **Holiday** on that day.
- Staff who do punch: all their worked time counts as overtime, if overtime is switched on in Company settings and the employee is overtime eligible.
- An unpaid holiday is still a day off, but it is marked as unpaid for payroll.
- Leave that covers a holiday does not count the holiday as a leave day, unless the leave type counts calendar days. With the **Sandwich rule**, a holiday between two leave days can count as leave; see [Leave types](/help/leave-types).
- Adding, changing or deleting a holiday works out attendance again for that day (if it is in the last two months and the month is not locked).

Holidays also show on [Who's off](/help/whos-off).

## Common problems

### Staff were marked absent on a holiday

The holiday is missing, is on the wrong date, or is for another branch. Add or fix it. Attendance for that day is worked out again by itself, unless the month is locked; see [Month lock](/help/month-lock).

### Load Pakistan holidays added no Eid dates

{app} does not have the Islamic dates for that year yet, so only the fixed national days were added. Add the Eid days yourself with **Add holiday range** and switch on **Needs confirmation**.

### "0 holidays added"

All those dates already have an all-branch holiday. Nothing was changed.

### "There is already a holiday on this date for these branches."

Each date can have one holiday for all branches, plus one for each branch. **Edit** the existing holiday instead of adding a second one.

### An employee says they took an optional holiday but shows absent

They are not in **Who takes it** for that holiday. Add them there; their day is worked out again.

### The menu shows a yellow number next to Holidays

There are provisional holidays in the next 30 days. Click **Confirm** on each one when the date is final, or **Edit** it to the right date.
