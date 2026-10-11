---
title: Unmapped PINs
group: Devices
order: 70
summary: Find punches from machine user IDs that no employee has yet, and attach them to the right person.
menu: Devices → Unmapped PINs
panel: unmapped-pins
screens: unmapped-pins=/admin/unmapped-pins
---

## In short

- Every person on a fingerprint or face machine has a user ID, called the **Machine PIN** in {app}. A punch is matched to an employee by this PIN.
- When a machine sends a PIN that no employee has, the punch is kept but not counted. The PIN is listed here.
- Click **Map to employee** and pick the person. The employee gets that Machine PIN, all their waiting punches are attached, and the days are worked out again.
- Setting or changing the **Machine PIN** on an employee's profile does the same: their waiting punches with that PIN are attached.
- The number next to *Unmapped PINs* in the menu is how many are still waiting.
- The owner, admins and HR can map PINs.

## What you see on this page

![The Unmapped PINs list with the Map to employee button on each row](unmapped-pins.jpg)

| Column | What it means |
|---|---|
| **PIN** | The user ID that punched on the machine. |
| **Name on device** | The name saved on the machine for that user, if {app} knows it. This helps you find the right person. |
| **Device** | Which machine it came from. *Manual / USB* means it came from a USB file without a device chosen. |
| **Punches** | How many punches are waiting with this PIN. *0* means the person is on the machine's user list but has not punched yet. |
| **First seen at** | When this PIN was first seen. |
| **Mapped to** | After mapping: the employee it now belongs to. |

The list opens on PINs that are **not yet mapped**. Use the **filters** button and change **Resolved** to see the ones already done.

## Why PINs end up here

- A new employee was enrolled on the machine, but their **Machine PIN** was not filled in on their profile.
- The PIN on the profile is different from the user ID on the machine (for example *0042* on the machine but *42* in {app}).
- Someone who is not an employee (a visitor, a contractor's worker, a test user) is enrolled on the machine.
- The sync agent sent the machine's user list, and some users on it have no employee yet.

## Map a PIN to an employee

1. Go to **Devices → Unmapped PINs**.
2. Look at **Name on device** and **Device** to decide who it is.
3. Click **Map to employee** on the row.
4. Choose the **Employee**. Only employees without a Machine PIN are listed.
5. Click **Submit**.

You see *"12 punches re-attached"*. From now on, every punch with this PIN goes to that employee straight away.

What happens:

- The employee's **Machine PIN** is set to this PIN.
- All the waiting punches with this PIN are attached to the employee and become normal punches.
- The attendance for those days is worked out again, so late, absent and present update in the sheet and in reports.

> **Tip:** The best way to avoid this page is to fill in the **Machine PIN** when you add an employee (see [Employees](/help/employees)), then use **Push all employees** on the Devices page so the machine has the same PINs (see [Fingerprint and face machines](/help/zkteco-machines)).

You can also map PINs while importing a USB file, in the preview. See [Import from USB](/help/usb-import).

## PINs that are not employees

If a PIN belongs to someone who should not be in attendance (a test user, a visitor), you can leave it unmapped. Its punches are kept but never counted. To stop it appearing, remove the user from the machine with **Delete employee from device** on the Devices page.

## Common problems

### "… already uses PIN 42. Change it on the employee first."

The employee you picked already has a different Machine PIN. Decide which one is right. If the machine's PIN is right, open the employee's profile, change **Machine PIN** to the new one, and save. Their waiting punches with the new PIN are attached straight away and the row here shows as mapped.

### "PIN 42 already belongs to …"

Another employee already has this PIN. Two people cannot share a PIN. Check both profiles and fix the wrong one first.

### The employee I want is not in the list

Only employees without a Machine PIN are listed. If the employee already has a PIN, it is different from this one; see the first problem above.

### The days still look wrong after mapping

The days are worked out again in the background, usually within a minute. Refresh the page.

If some punches fall in a month that is locked under **Attendance → Months (lock)**, the message after mapping is yellow and lists those dates: *"The punches are attached, but these days are in a locked month, so they were not recalculated: …"*. The locked month keeps its attendance as it was. See [Month lock](/help/month-lock).

### The same PIN shows twice

It came from two different machines. Map one row; the other is resolved with it, because a PIN belongs to one employee across all machines.
