---
title: Face enrolment
group: People
order: 30
summary: Record each employee's face with a webcam so they can punch on the kiosk tablet just by looking at it.
menu: People → Face enrolment
panel: face-enrolment
screens: face-enrolment=/admin/face-enrolment
---

## In short

- This page appears only if your plan includes **Face recognition on kiosk**. Owner, Admin and HR can use it.
- Each employee stands in front of a camera once. {app} takes three samples of their face.
- No photo is saved. Only face measurements (numbers) are stored, and they cannot be turned back into a picture.
- Enrolling the same person again replaces their old face data.
- After enrolment the person can choose **Face** on the [kiosk](/help/kiosk). If your company turns on the face check, their photo on PIN, QR and phone punches is also compared with this face.

## What you see on this page

![The Face enrolment page: employee list, camera buttons and the camera picture](face-enrolment.jpg)

- **Enrol a face**: the **Employee** list, the **Start camera** and **Capture face** buttons, a status line, and the live camera picture.
- **Enrolled employees**: everyone who has face data, with the number of samples, and a **Delete face data** link. If nobody is enrolled yet it says *Nobody is enrolled yet.*

Employees who have left are not in the **Employee** list.

## How to enrol a face

You need a computer or tablet with a camera (a laptop webcam is fine) and good light on the face.

1. Open **People → Face enrolment**.
2. Click **Start camera**. When the browser asks, allow the camera.
3. Wait for *Ready. Choose the employee and press Capture face.*
4. Choose the person in **Employee**.
5. Ask them to look straight at the camera, alone, close enough that the face fills a good part of the picture.
6. Click **Capture face**. Watch the status line: *Sample 1 of 3 — keep still*, then 2 and 3.
7. You see *Saved.* and the message "Ali can now punch with their face". The person now shows under **Enrolled employees**.

Choose the next employee and click **Capture face** again. The camera stays on.

While it is taking samples, the status line tells the person what to do:

| Message | What to do |
|---|---|
| *Look at the camera* | No face found. Face the camera. |
| *One person at a time, please* | Only one face may be in the picture. |
| *Come a little closer* | The face is too small. |
| *Look straight at the camera* | The head is turned. |
| *That does not look like a live face. Use the real person, not a photo.* | The camera sees a photo or screen. The real person must stand there. |

> **Tip:** Enrol in the same kind of light as the kiosk (for example at the gate, not next to a bright window). Remove sunglasses and caps. Glasses and a beard are fine.

## Delete someone's face data

In **Enrolled employees**, click **Delete face data** next to the person and confirm. They can no longer punch with their face; their PIN and QR badge still work. Deleting and enrolling are both written in the [Audit log](/help/audit-log).

Delete face data when an employee leaves, or when they ask for it.

## What happens after enrolment

- On a kiosk that allows **Face**, the person taps **Face**, looks at the camera and turns their head slowly to the left and right. {app} finds them and saves the punch. See [Using the kiosk](/help/using-the-kiosk).
- Face punching works only on kiosks locked to approved tablets. See [Kiosk](/help/kiosk).
- Under **System → Company settings** you can turn on **Face check on PIN, QR card and phone punches**. Then the photo taken with those punches is compared with the enrolled face. A wrong face, no face, or a person who is not enrolled sends the punch to [Punch approvals](/help/punch-approvals) instead of saving it straight away.

## Common problems

### I can't see Face enrolment in the menu

Your plan does not include **Face recognition on kiosk**, or your role cannot manage employees. Only Owner, Admin and HR see this page.

### "The camera could not start. Allow camera access in the browser."

The browser has blocked the camera, or another program is using it. Click the camera icon in the address bar, allow the camera, close other video apps, and click **Start camera** again.

### "Could not get a clear face. Try again with more light on the face."

{app} could not take three good samples in 15 seconds. Turn on a light in front of the person (not behind them), ask them to come closer and keep still, and click **Capture face** again.

### "Not enough good face samples. Try again in better light."

The samples were not clear enough to save. Nothing was changed. Try again in better light.

### The Capture face button is grey

Choose the employee first. The button works only when someone is picked in **Employee** and no capture is running.

### The kiosk says "Face not recognised. Please use your PIN."

The person is not enrolled, or their face looks different from the samples (very different light, cap, mask). Check they are under **Enrolled employees**. If they are, enrol them again in light like the kiosk's.
