---
title: Kiosk tablets
group: Devices
order: 50
summary: Turn a tablet at the entrance into an attendance point where staff punch with a PIN, a QR code or their face.
menu: Devices → Kiosks
panel: kiosks | kiosk-devices
screens: kiosk-list=/admin/kiosks | kiosk-new=/admin/kiosks >> New kiosk | kiosk-tablets=/admin/kiosk-devices
---

## In short

- A **kiosk** is a web page that turns any tablet or phone with a camera into an attendance point. No machine is needed.
- Create it under **Devices → Kiosks → New kiosk**, then open its **Kiosk link** on the tablet in Chrome.
- Staff punch with their **Machine PIN**, the **QR code** in their staff app, or (if your plan includes it) their **face**. A photo can be taken with every punch.
- With **Only approved tablets** on, the link works only on tablets you approve under **Devices → Kiosk tablets**, so it cannot be used from home.
- If the internet drops, the kiosk keeps the punches and sends them when it is back (up to 24 hours).

This page appears only if your plan includes *Kiosk tablet*. Face punching needs *Face recognition on kiosk* too. The owner, admins and reception can manage kiosks.

## What you see on this page

![The Kiosks list with the Kiosk link and New link buttons](kiosk-list.jpg)

| Column | What it means |
|---|---|
| **Name** | Your name for this kiosk, for example *Reception tablet*. |
| **Branch** | Only staff of this branch can punch here. *Any branch* = everyone. |
| **Allowed modes** | How staff can punch: *pin*, *qr*, *face*. |
| **Photo** | A tick means a selfie is taken with every punch. |
| **Locked** | A tick means it works only on approved tablets. |
| **Waiting tablets** | Tablets that opened the link and wait for your approval. |
| **Last punch** | When the last punch was made on this kiosk. |

Each kiosk also shows in **Devices → Devices** with type *Kiosk*, so its punches appear in device reports.

## Create a kiosk

1. Go to **Devices → Kiosks** and click **New kiosk**.
2. **Name**: for example *Main gate tablet*.
3. **Branch**: choose the branch. Employees of other branches are refused. Leave empty for all branches.
4. **Allowed modes**: tick one or more:
   - **Employee PIN (+ optional 4-digit kiosk PIN)**: staff type their **Machine PIN**. If an employee has a **Kiosk PIN (4 digits)** on their profile, they must type that too. To stop asking someone for it, turn on **Remove kiosk PIN** on their profile.
   - **QR badge from the phone**: staff show the QR code from their staff app to the camera. The code changes every day.
   - **Face recognition**: staff just look at the camera. Their face must be enrolled first; see [Face enrolment](/help/face-enrolment).
5. **Require photo**: on by default. A selfie is taken with every punch, so you can check who punched under **Attendance → Punch photos**.
6. **Only approved tablets**: on by default. Keep it on. It is required for face recognition.
7. **Only from these internet addresses** (optional): your office internet IP, for example 39.45.10.22, or a range such as 39.45.10.0/24. Then the kiosk works only on the office connection. Leave empty to allow any connection.
8. Click **Create**.

![The New kiosk form](kiosk-new.jpg)

## Set up the tablet

1. On the Kiosks list, click **Kiosk link** on the kiosk's row. Copy the address shown.
2. On the tablet, open the address in **Chrome**.
3. In Chrome's menu choose **Add to home screen**, so it opens like an app.
4. Allow the camera when the tablet asks.
5. If the kiosk is locked to approved tablets, the tablet now shows *"This tablet is waiting for approval. Ask your administrator to approve code AB12."* Write down the code.
6. In {app}, go to **Devices → Kiosk tablets**. Find the row with the **same code** and click **Approve**.
7. The tablet is ready. Mount it near the entrance and keep it on the charger.

## Approve or block tablets

![The Kiosk tablets list with waiting tablets and their codes](kiosk-tablets.jpg)

Every tablet or browser that opens a locked kiosk link shows up under **Devices → Kiosk tablets**. The number next to the menu item is how many are waiting.

| Column | What it means |
|---|---|
| **Code** | The 4-character code the tablet shows on its screen. |
| **Kiosk** | Which kiosk link it opened. |
| **Status** | *pending* (yellow), *approved* (green) or *blocked* (red). |
| **Browser** | The tablet's browser details. |
| **IP address** | The internet address it came from. |
| **First seen / Last seen** | When it first and last opened the kiosk. |

- **Approve**: only if the tablet in your office shows the same code.
- **Block**: for any code you do not recognise, for example someone who opened the link at home.

## What happens after a punch

- The kiosk shows the person's name, *IN* or *OUT* and the time. In or out is chosen by itself from the person's last punch.
- If the same person punches again within a minute, the kiosk shows *"Already punched IN at 09:02"* and nothing new is saved.
- Unread announcements for that person are shown after the punch, so staff without the app still see them.
- The punch goes into attendance straight away, like a machine punch.
- If **Face check on PIN, QR card and phone punches** is on (under **System → Company settings**) and the face in the photo does not match, the punch waits in **Attendance → Punch approvals**.

## A lost tablet or a shared link

Click **New link** on the kiosk's row and confirm. The old link stops working immediately. Open the new link on your tablets and approve them again.

## Common problems

### "This tablet is waiting for approval. Ask your administrator to approve code …"

The kiosk is locked to approved tablets and this tablet is new. Approve the row with that code under **Devices → Kiosk tablets**. This needs a user who can manage kiosks (owner, admin or reception).

### "This kiosk only works on the office internet connection."

The tablet is not on the office internet, or the office IP changed. Connect the tablet to the office Wi-Fi, or update **Only from these internet addresses** on the kiosk.

### "This tablet has been blocked for this kiosk."

Someone blocked this tablet. If it is your office tablet, click **Approve** on its row under **Devices → Kiosk tablets**.

### "Not recognised. Please try again."

The PIN typed is not any employee's **Machine PIN**, the 4-digit kiosk PIN is wrong, or the employee is marked as left. Check the employee's profile.

### "Please use the kiosk at your own branch."

The employee belongs to another branch than this kiosk. Change the employee's branch, or leave the kiosk's **Branch** empty.

### "Face not recognised. Please use your PIN."

The face did not match any enrolled face closely enough. Check the light, ask the person to look straight at the camera, or enrol the face again under **People → Face enrolment**.

### "Face recognition needs this on: face punches are only trusted from approved tablets."

You ticked face recognition with **Only approved tablets** off. Turn **Only approved tablets** on.

### "Camera is not available."

The tablet's camera is blocked for this page. Allow the camera in Chrome's site settings, then reload the page.
