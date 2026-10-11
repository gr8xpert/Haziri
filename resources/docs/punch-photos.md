---
title: Punch photos
group: Attendance
order: 40
summary: Look through the photos taken with kiosk and phone punches, to catch buddy punching and check suspicious punches.
menu: Attendance → Punch photos
panel: punch-photos
screens: punch-photos=/admin/punch-photos
---

## In short

- Every kiosk and phone punch with a photo shows here as a card: the photo, the name, the time and where it came from.
- It opens on **this week** (Monday to today). Change **From** and **To** to look at other days.
- Type a **PIN, code or name** in **Employee** to see one person, for example "all punches of PIN 47 this week".
- Tick **Suspicious only** to see just the photos that need a look: failed face checks and punches outside the office area.
- The page shows the newest 120 photos for your filters. Narrow the dates or pick one employee to see older ones.

## Why this page is useful

**Buddy punching** means one employee punches for another, for example with their PIN or their phone. A photo makes this easy to spot: the face on the card does not match the name under it.

Photos are taken at the [kiosk](/help/kiosk) and when staff punch from their phone ([Phone punching](/help/phone-punching)). Fingerprint and face machines do not appear here.

## What you see on this page

![Punch photos page with a grid of photo cards and the filters above it](punch-photos.jpg)

At the top:

| Filter | What it does |
|---|---|
| **Employee** | Type a machine PIN, an employee code, or part of a first name. |
| **From** / **To** | The dates to show. Starts on this week. |
| **Suspicious only** | Shows only photos with a failed face check or taken outside the office area. |

Each card shows:

- the **photo**; click it to open it full size in a new tab,
- the **name** (or *PIN …* if the PIN is not linked to anyone),
- the **day and time**, and the **source** (*Kiosk* or *Mobile*),
- a red badge when the face check failed, with the reason and the match score, for example *Face does not match · 41%*,
- a yellow badge **Outside geofence** when a phone punch was made outside the office area,
- a green line *Face matches · 87%* when the face check passed.

The face check and its score appear only if your company uses face checks (*Face check on PIN, QR card and phone punches* in Company settings, part of the face recognition plan option).

## Who can use it

Owner, admin, HR, branch managers and receptionists (everyone who can see all attendance).

Photos are private. The links on this page work only for a short time (30 minutes) and only for logged-in users. If a photo stops loading after a while, reload the page.

## How to check for buddy punching

1. Tick **Suspicious only** and look through the cards for this week.
2. For any card that looks wrong, type that employee's code in **Employee** and untick **Suspicious only**, to compare with their other photos.
3. If a punch was not theirs, remove it in the [Punch log](/help/punch-log) with a reason, or send a **Remove punch** [correction](/help/corrections).

> **Tip:** Punches that failed the face check or were outside the area are not counted until someone approves them. Decide them in [Punch approvals](/help/punch-approvals). This page is for looking; that page is for deciding.

## Turning photos on or off

The setting **Store punch photos** under **System → Company settings** decides whether {app} keeps punch photos. **Keep photos** (days) says how long: every night, photos older than that are deleted and their cards leave this page (the punch itself stays). Default 90 days; 0 or empty keeps photos forever. Turn on **Selfie required for mobile punch** if every phone punch must have a photo.

## Common problems

### "No punch photos for these filters"

There are no photos for these dates or this employee. Check the **From** and **To** dates, clear the **Employee** box, and untick **Suspicious only**. Remember that machine punches have no photos here.

### A card shows "PIN 47" instead of a name

That PIN is not linked to any employee yet. Link it under [Unknown PINs](/help/unmapped-pins).

### The photo does not load

The photo link has expired (links last 30 minutes) or the photo file is gone. Reload the page. If one photo still does not load, it was not saved with the punch.

### Older photos are gone

Photos older than **Keep photos** (days) in **Company settings** are deleted every night. Raise the number to keep them longer, or set 0 to keep them forever.

### I searched for a name but nothing shows

The **Employee** box matches the first name, the employee code or the PIN. Try the code instead, or only the first name.

### Phone punches have no photo

The employee punched without a selfie. Turn on **Selfie required for mobile punch** in **Company settings** so a photo is always taken.
