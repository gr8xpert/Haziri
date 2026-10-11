---
title: Manual Entry
group: Attendance
order: 50
summary: Enter in and out times for many employees on one date, on one sheet, when the machine was down or staff have no machine.
menu: Attendance → Manual Entry
panel: manual-entry
screens: manual-entry=/admin/manual-entry
---

## In short

- One date, one row per employee, with **In** and **Out** boxes. Type the times and click **Save changes**.
- The boxes are filled with the punches already recorded for that day, so you only add what is missing.
- **Fill empty rows with shift times** puts the shift start and end in every empty row in one click.
- Only new or changed times are saved, as manual punches with your **Reason**. Existing punches are **never deleted** here.
- Leave a row empty for an absent employee.

## When to use it

- The machine was broken or had no power for a day.
- A small office or site with no machine, where the receptionist or supervisor writes down who came.
- After a public event where everyone worked set hours.

For one employee, **Add one punch** in the [Punch log](/help/punch-log) is quicker. For one employee over many days, use **Bulk manual** there.

## What you see on this page

![Manual Entry sheet with In and Out boxes for each employee](manual-entry.jpg)

At the top:

| Field | What it does |
|---|---|
| **Date** | The day you are entering. Today is chosen at first. Future dates cannot be picked. |
| **Branch** | Show the employees of one branch only, or **All branches**. |
| **Reason** | Saved with every punch you enter, for example *Machine down*. It says *Manual entry* until you change it. |

Each row:

| Column | What it means |
|---|---|
| **Code / Employee** | The employee. Everyone who has joined by this date and has not left is listed, sorted by code. |
| **Shift** | The employee's shift times for this date, or *No shift*. |
| **In** | The first punch of the day if there is one. Type or change the in time here. |
| **Out** | The last punch if there are two or more. Type or change the out time here. |
| **Status** | A quick check of what you typed: *Absent* (both empty), *No out* (in only) or *Present*. It does not know about leave, holidays or off days; the real status is worked out after saving. |
| **Present** button | Fills this row with the shift start and end. |
| **Reset** (arrow icon) | Puts the row back to the punches that were recorded. |

## How to enter a day

1. Pick the **Date**, and the **Branch** if you only want one.
2. Change the **Reason** to say why, for example *Fingerprint machine down*.
3. Type the **In** and **Out** times for each employee who came. Or click **Fill empty rows with shift times** and then clear the rows of people who were absent.
4. Click **Save changes** at the bottom.

A message tells you how many employees were updated. Their days are worked out again within a minute: lates, early outs and half days are judged on the times you entered, the same as machine punches. Every punch entered here gets the flag *Manual punch* and is listed in the **Manual entry audit** report (see [Other reports](/help/other-reports)).

**Night shifts:** if the out time is earlier than the in time (for example in 22:00, out 06:00), the out punch is saved on the next morning.

> **Tip:** Rows you did not touch are not saved, and nothing is deleted. You can open the sheet, add one missing out time, and save without risk to the other rows.

## Correcting a wrong time

This page only **adds** punches. If you change a time that was already there, the new time is added and the old punch stays. With *First in, last out*, an earlier in time or a later out time then wins, but a **later** in time or an **earlier** out time does not.

To correct a wrong punch, remove it first in the [Punch log](/help/punch-log) (**Remove**), then enter the right time here.

## Who can use it

- **Owner, admin, HR and branch managers** can enter any past date.
- **Receptionists** can enter only **today and yesterday**.

## Common problems

### "Some rows were not saved"

The message lists each row by employee code with the reason. Fix those rows and save again. The other rows were saved.

### "Receptionists can only enter punches for today or yesterday."

Your role cannot enter older days. Ask HR, or ask the employee to send a [correction](/help/corrections).

### "A reason is required for manual entries."

The **Reason** box at the top is empty. Write a short reason and save again.

### An employee is missing from the sheet

The sheet shows only employees who joined on or before this date and are not marked as left. Check the employee's **joining date** and status under [Employees](/help/employees), and the **Branch** filter.

### I changed the out time to an earlier time but the day still shows the old time

This page does not delete punches; the later punch still counts as the out time. Remove the wrong punch in the [Punch log](/help/punch-log). The day is then worked out again with your new time.

### I saved, but nothing changed for that date

The month may be locked. Days in a locked month do not change. See [Month lock](/help/month-lock).
