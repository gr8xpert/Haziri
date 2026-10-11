---
title: Punch log
group: Attendance
order: 20
summary: See every punch that reached {app} from machines, kiosks, phones and manual entry, and add or remove punches by hand.
menu: Attendance → Punch log
panel: punches
screens: punch-log=/admin/punches | punch-log-add=/admin/punches >> Add one punch
---

## In short

- The Punch log lists every punch: from fingerprint and face machines, the office PC agent, USB import, kiosk tablets, phones, manual entry and the API. Newest first.
- It opens on the **last 7 days**. Use the filters to see one employee, one source or one status.
- Only punches with status **Valid** count in attendance. Duplicates, unknown PINs, rejected punches and punches waiting for approval do not.
- **Add one punch** types in an in and/or out time for one employee. A reason is always required.
- **Remove** deletes a wrong punch. The day is worked out again straight away.

## What you see on this page

![Punch log showing recent punches with their source and status](punch-log.jpg)

Each row is one punch:

| Column | What it means |
|---|---|
| **Time** | Date and time of the punch, in your company's time zone. |
| **Employee** | Who punched. *— unmapped —* means the machine sent a PIN that no employee has yet. |
| **PIN** | The employee's number on the machine. |
| **Source** | Where the punch came from: *Machine (cloud)*, *Machine (office PC agent)*, *USB import*, *Kiosk*, *Mobile*, *Manual* or *API*. |
| **Direction** | *In*, *Out* or *Unknown*. Machine punches show *Unknown* unless your company trusts the machine's In/Out keys. This is normal: {app} takes the first punch as in and the last as out. |
| **Status** | See the table below. |
| **Manual** | A tick if someone typed the punch in by hand (manual entry or an approved correction). |
| **Reason** | The reason given for a manual punch. You can hide this column. |
| **Entered by** | Who typed in the manual punch. Hidden at first; switch it on with the columns button. |

### Punch statuses

| Status | Meaning | Counts in attendance? |
|---|---|---|
| **Valid** (green) | A normal punch. | Yes |
| **Duplicate** (grey) | A second tap within the double-tap window (60 seconds by default) of another punch. | No |
| **Unknown PIN** (yellow) | No employee has this machine PIN. Map it under [Unknown PINs](/help/unmapped-pins) and the punches become Valid. | No, until mapped |
| **Waiting for approval** (yellow) | A phone or kiosk punch that needs a check, for example made outside the office area. See [Punch approvals](/help/punch-approvals). | No, until approved |
| **Rejected** (red) | A punch that HR rejected in Punch approvals. | No |

Use the **filters** button for: **Date** (*From* / *To*), **Employee**, **Source** and **Status**. Search finds an employee by name or code, or a PIN.

> **Tip:** If a machine sends the same punch twice (for example after a network break), {app} keeps only one copy. It does not even show as a duplicate.

## Who can use it

- **Owner, admin, HR, branch manager and receptionist** can see the Punch log.
- All of them can use **Add one punch**. Receptionists can enter punches only for **today or yesterday**.
- **Bulk manual** and removing any punch are for **owner, admin, HR and branch manager**. A receptionist can remove only manual punches of today.

## Add one punch

Use this when the machine was down or the employee forgot to punch.

1. Click **Add one punch** at the top right.
2. Pick the **Employee** (search by name or code).
3. Pick the **Date**. Future dates are not allowed unless your company switched on *Allow future manual punch* in Company settings.
4. Fill **In time**, **Out time**, or both.
5. Write a **Reason**, for example *Machine down* or *Forgot to punch*. You can add a **Note** too.
6. Click **Submit**.

![The Add one punch form](punch-log-add.jpg)

The punches are saved with the source *Manual* and the day is worked out again within a minute. The day gets the flag *Manual punch*, and every manual punch is listed in the **Manual entry audit** report.

**Night shifts:** if the out time is earlier than the in time (for example in 22:00, out 06:00), {app} puts the out punch on the next morning.

For many employees on one date, use [Manual entry](/help/manual-entry) instead. The **Manual Entry** button at the top takes you there.

## Bulk manual: a date range for one employee

Under **Bulk manual** at the top there are two options (owner, admin, HR and branch manager only):

**Manual attendance — date range** puts the same in and out time on every day of a range. Useful for a long site visit or a broken machine.

1. Pick the **Employee**, **From** and **To** dates (at most 62 days, not in the future).
2. Fill **In time** and/or **Out time**, and a **Reason**.
3. Keep **Skip weekly offs, holidays and leave** on unless the employee really worked those days.
4. Click **Submit**.

Days that already have punches, days in a locked month and (with the switch on) off days, holidays and leave days are skipped. The message after saving lists each skipped day and why.

**Remove manual punches — date range** removes all **manual** punches of one employee between two dates. Machine, kiosk and phone punches are never touched. A **Reason** is required. Days in a locked month are left alone.

## Remove a wrong punch

1. Find the punch (filter by employee and date).
2. Click **Remove** on its row.
3. Write **Why?** and confirm.

The punch disappears from the list and the employee's day is worked out again. The reason and who removed it are kept in the record.

> **Tip:** An employee or manager who wants a punch removed can send a **Remove punch** [correction](/help/corrections) instead. Then the request goes through approval.

## Common problems

### "Receptionists can only enter punches for today or yesterday."

Your role can only enter recent punches. Ask HR to enter older ones, or to send a [correction](/help/corrections).

### "Manual punches cannot be entered for future dates."

You picked a date after today. Pick today or an earlier date. The owner can allow future dates with *Allow future manual punch* in **System → Company settings**.

### "Enter an in time, an out time, or both."

Both time boxes were empty. Fill at least one of them.

### "A reason is required for manual entries."

Every manual punch needs a reason, for the audit trail. Write a short one, for example *Machine down*.

### An employee says they punched, but I can't find the punch

Set the **Date** filter to the right days and search for their **PIN** instead of the name. If the row shows *— unmapped —*, the machine PIN is not on their profile yet: map it under [Unknown PINs](/help/unmapped-pins). If there is no row at all, the machine has not sent its data yet; see [Devices overview](/help/devices-overview).

### The punch is there but the day is still Absent

Check the **Status** column. Only *Valid* punches count. A punch waiting for approval must be approved first in [Punch approvals](/help/punch-approvals). Also check that the punch is near the employee's shift; see [How attendance works](/help/how-attendance-works).

### I added a punch in a locked month and nothing changed

Days in a locked month are frozen. Ask the owner to unlock the month under [Month lock](/help/month-lock), remove and add the punch again, then lock the month again.

### "At most 62 days at a time."

The bulk date range is too long. Split it into two or more shorter ranges.
