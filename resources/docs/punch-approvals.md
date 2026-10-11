---
title: Punch approvals
group: Attendance
order: 30
summary: Check and approve or reject phone and kiosk punches that {app} held back, for example punches made outside the office area.
menu: Attendance → Punch approvals
panel: punch-approvals
screens: punch-approvals=/admin/punch-approvals
---

## In short

- Some punches are not counted straight away. They wait here for a person to look at them.
- A punch waits when it was made **outside the office area**, **not on the office internet**, or when the **face check** on the photo failed.
- **Approve** makes the punch count; the employee's day is worked out again. **Reject** throws it out; it stays in the Punch log as *Rejected*.
- The number next to *Punch approvals* in the menu is how many punches are waiting.
- Owner, admin, HR and branch managers can decide.

## Why a punch waits here

| Reason shown in **Why** | What happened |
|---|---|
| **Outside the office area** | A phone punch was made outside the geofence: the allowed circle around the office. See [Phone punching](/help/phone-punching). |
| **Not on the office internet** | The company requires phone punches from the office Wi-Fi, and this phone was on another network. |
| **Face does not match** | The photo taken with the punch does not look like the face enrolled for this employee. |
| **No face in the photo** | The photo had no face in it (covered camera, too dark, face out of view). |
| **Face not enrolled** | The company checks faces, but this employee has no enrolled face yet. See [Face enrolment](/help/face-enrolment). |
| **Kiosk not locked to approved tablets** | The kiosk punch came from a tablet that is not on the approved list. See [Kiosk](/help/kiosk). |

The face reasons appear only if your company switched on *Face check on PIN, QR card and phone punches* in **Company settings**. That setting appears only if your plan includes face recognition on the kiosk.

## What you see on this page

![Punch approvals list with photo, reason, distance and Approve / Reject buttons](punch-approvals.jpg)

| Column | What it means |
|---|---|
| **Photo** | The selfie or kiosk photo taken with the punch, if any. Click to see it bigger. |
| **Employee** | Who punched. |
| **Punched at** | Date and time of the punch. |
| **Direction** | In or Out, as chosen in the app. |
| **Why** | The reason it is waiting (see the table above). There can be more than one. |
| **Distance** | How far from the office the phone was, in metres. |
| **GPS ±** | How accurate the phone's location was, in metres. A big number (for example ±300 m) means the location is a rough guess. |
| **Map** | Click **open** to see the spot on Google Maps. |

## Approve or reject

1. Look at the photo, the distance and the map.
2. Click **Approve** or **Reject** on the row.
3. Add a **Note** if you want (for example *Client visit, confirmed by manager*). It is optional for both.
4. Click **Submit**.

After **Approve**, the punch becomes *Valid*. The employee's day is worked out again within a minute, and the day gets the flag *Outside office area* if the punch was outside the geofence. After **Reject**, the punch is kept in the [Punch log](/help/punch-log) with status *Rejected* and is not counted.

> **Tip:** A punch that waits is not counted at all. If you do not decide, the employee may show as Absent or with a missing punch for that day. Check this page every day.

> **Tip:** Many punches from one person just outside the area? The office circle may be too small, or the GPS on their phone weak. Check the geofence radius on the branch, or the company's *Default geofence* in Company settings.

## Common problems

### "This punch is not waiting for approval."

Someone else already approved or rejected it, so the list you see is out of date. Reload the page.

### "You cannot approve punches."

Your role cannot decide punches. Only owner, admin, HR and branch managers can. Ask one of them.

### I approved a punch but the day did not change

The day may be in a locked month; locked days never change. See [Month lock](/help/month-lock). Otherwise, wait a minute and reload: the day is worked out again in the background.

### Good employees keep landing here with "Face does not match"

Their enrolled face may be old or a poor photo. Enrol the face again under [Face enrolment](/help/face-enrolment), in good light and without glasses or a cap.

### The distance is large but the employee says they were in the office

Look at **GPS ±**. Indoors, phones often give a rough location that can be hundreds of metres off. If the photo and the time look right, approve it. To stop very rough locations at the phone, set *Reject mobile GPS worse than* in **Company settings**; the employee is then asked to move near a window and try again.
