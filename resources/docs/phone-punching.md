---
title: Phone punching
group: Devices
order: 60
summary: Let staff punch in and out from their own phone, with location, office network and selfie checks.
menu: System → Company settings
panel: company-settings | branches
screens: phone-punching-app=/me | phone-punching-branch=/admin/branches >> New branch | phone-punching-settings=/admin/company-settings
---

## In short

- Staff punch with the **Punch in / Punch out** button in the staff app (**/me**). Their location (GPS) and a selfie are saved with each punch.
- A punch made **inside the branch's geofence** counts straight away. A punch from **outside** is saved but waits for approval in **Attendance → Punch approvals**.
- **Geofence**: a circle around the branch's location (150 m by default). Set the location under **Organisation → Branches**.
- Optional extra rules: **Office internet addresses** on the branch, **Selfie required for mobile punch**, **Reject mobile GPS worse than**, and a face check.
- **Field** and **Remote** staff are never limited by the geofence or the office internet. Turn **Can punch from phone** off for staff who must use the machine.

This works only if your plan includes *Mobile punch (GPS)*. Without it, the punch button does not appear in the staff app.

## How a phone punch is checked

![The staff app with the Punch in button](phone-punching-app.jpg)

When an employee taps **Punch in** (or **Punch out**), the phone asks for the location and takes a selfie. Then {app} checks, in this order:

| Check | What happens if it fails |
|---|---|
| Your plan includes phone punching | *"Mobile punching is not enabled for your company."* No punch is saved. |
| **Can punch from phone** is on for the employee | *"Please punch on the machine or kiosk at your workplace."* No punch is saved. |
| GPS is accurate enough (only if **Reject mobile GPS worse than** is set) | *"The GPS signal is too weak (±250 m). Move near a window or outside and try again."* No punch is saved. |
| A selfie was taken (only if **Selfie required for mobile punch** is on) | *"A selfie is required to punch."* No punch is saved. |
| Not punched in the last 60 seconds | *"You just punched. Please wait a minute."* |
| Inside the branch geofence | Saved, but waits for approval. The employee sees *"Saved at 09:14, but you are 820 m from the office (allowed 150 m). HR will review it."* If the branch has no location yet: *"…but your branch has no office location set."* |
| On the office internet (only if the branch has **Office internet addresses**) | Saved, but waits for approval: *"Saved at 09:14, but not from the office internet. HR will review it."* |
| The selfie face matches the enrolled face (only if the face check is on) | Saved, but waits for approval: *"…your face could not be confirmed. HR will review it."* |
| No other punch within the double-tap window (60 seconds by default; from any machine, kiosk or phone) | *"You had already punched a moment ago, so this one was not counted again."* |

If more than one check fails, the message names each reason, separated by **;**.

If everything passes, the employee sees *"Punched in at 09:14."* and the punch counts straight away.

In or out is chosen by itself: the opposite of the employee's last punch in the past 16 hours. A night shift that crosses midnight works fine.

> **Tip:** A poor GPS signal gets a little benefit of the doubt. If the phone says it is accurate to ±60 m, a punch up to 60 m outside the circle still counts (at most 100 m extra).

## Set the branch location (geofence)

1. Go to **Organisation → Branches** and edit the branch (or click **New branch**).
2. Fill in **Latitude** and **Longitude** of the office. To find them: open Google Maps, press and hold on your office building, and copy the two numbers shown (for example 31.5204, 74.3587).
3. **Geofence radius**: how far from that point a punch still counts, in metres (20 to 5,000; default 150). For a big factory, use a larger number.
4. **Office internet addresses** (optional): the office's internet IP address, for example 39.45.10.22. Then a punch from any other connection (for example mobile data) waits for approval, even inside the geofence. Leave empty if you don't need this.
5. Save.

![The branch form with Latitude, Longitude, geofence radius and office internet addresses](phone-punching-branch.jpg)

> **Tip:** To find your office internet address, open a site such as "what is my IP" on a computer connected to the office internet. Only use this if your office has a fixed IP; ask your internet provider.

If a branch has no latitude and longitude, every phone punch from its staff waits for approval.

## Company-wide settings

Under **System → Company settings**, in the **Punches** section:

| Setting | What it does | Default |
|---|---|---|
| **Default geofence** | The radius used when a branch has none, in metres. | 150 |
| **Reject mobile GPS worse than (0 = off)** | Refuse punches when the phone's GPS is less accurate than this many metres. | 0 (off) |
| **Selfie required for mobile punch** | Refuse a punch without a selfie (for example when the camera is blocked). | Off |
| **Face check on PIN, QR card and phone punches** | Compare the selfie with the face enrolled under **People → Face enrolment**. Wrong face, no face or not enrolled → the punch waits for approval. Shown only if your plan includes face recognition. | Off |

![The Punches section of Company settings](phone-punching-settings.jpg)

## Settings for each employee

On the employee's profile, in the **Attendance** section:

- **Work mode**: *Office*, *Field* or *Remote*. **Field** staff (sales, drivers, technicians) and **Remote** staff (working from home) can punch from anywhere: no geofence and no office internet check. Only *Office* staff are checked against their branch.
- **Can punch from phone**: on by default. Turn it off for staff who must punch on the shop machine or kiosk.

See [Employees](/help/employees).

## Approving punches from outside

Punches that wait for approval are listed under **Attendance → Punch approvals**, with the distance from the office, the map location and the selfie. Approve the real ones; reject the others. Until approved, they do not count for attendance. See [Punch approvals](/help/punch-approvals). Selfies are also under **Attendance → Punch photos**.

## Common problems

### "Could not punch. Allow location access and try again."

The phone did not give the location. The employee must allow location for the browser (and for the staff app if installed on the home screen), switch on GPS, and try again.

### "The GPS signal is too weak (±… m). Move near a window or outside and try again."

The phone's location is less accurate than the limit in **Reject mobile GPS worse than**. Inside large buildings GPS is weak. Ask the employee to move near a window, or raise the limit (or set 0 to turn it off).

### Every phone punch goes to Punch approvals

Usually the branch has no **Latitude** and **Longitude**, or they are wrong. Check them under **Organisation → Branches**. If the branch has **Office internet addresses**, staff on mobile data also go to approval; check the IP is still correct (some providers change it).

### "Please punch on the machine or kiosk at your workplace."

**Can punch from phone** is off for this employee. Turn it on in their profile if they should punch from the phone.

### "A selfie is required to punch."

**Selfie required for mobile punch** is on, and the camera was blocked or not available. The employee must allow the camera for the browser and try again.

### The punch button is missing in the staff app

Either your plan does not include *Mobile punch (GPS)*, or **Can punch from phone** is off for this employee.
