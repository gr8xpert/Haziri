---
title: Ways to record attendance
group: Devices
order: 10
summary: The five ways punches can reach {app} (cloud machine, sync agent, USB, kiosk tablet, phone) and how to choose the right one.
menu: Devices
panel: 
screens: devices-overview-menu=/admin/devices
---

## In short

- A **punch** is one record of a person arriving or leaving. {app} works out each day's attendance from punches, wherever they come from.
- There are five ways to get punches in: a **fingerprint or face machine that talks to the cloud**, a machine read by the **sync agent** on an office PC, a **USB file** from the machine, a **kiosk tablet** at reception, and **punching from the phone**.
- You can mix them. For example: machines at the factory, a kiosk tablet at a small office, and phone punching for field staff.
- Every machine punch is matched to a person by the **Machine PIN** (the user ID on the machine). A PIN that no employee has goes to **Devices → Unmapped PINs**.
- Only the owner and admins can add or change machines. HR, branch managers and reception can upload USB files.

## The five ways, side by side

| Way | What you need | How punches arrive | Best for |
|---|---|---|---|
| **Machine (cloud)** | A ZKTeco machine with a *Cloud Server* or *ADMS* option in its menu, and internet at the machine | The machine sends each punch by itself, within seconds | Most offices and factories. Nothing to install. |
| **Machine (office PC agent)** | An older machine with only a network (LAN) cable, and one Windows PC that is always on | A small program on that PC reads the machine every minute and sends the punches | Machines with no cloud option |
| **USB import** | Any ZKTeco machine with a USB port | Someone copies the log to a USB stick and uploads the file | Sites with no internet, or as a backup |
| **Kiosk tablet** | A tablet or phone with a camera at the entrance | Staff tap their PIN, show a QR code or look at the camera | Small offices and shops without a machine |
| **Phone punching** | Each employee's own smartphone | Staff punch from the staff app with their location and a selfie | Field staff, sales teams, small offices |

![The Devices menu with Devices, Kiosks, Kiosk tablets, Import from USB and Unmapped PINs](devices-overview-menu.jpg)

## Which one should I choose?

1. **Do you already have a ZKTeco machine?** Look in its menu under **COMM.** (or **Comm.**). If you see **Cloud Server Setting** or **ADMS**, use the cloud way: see [Fingerprint and face machines](/help/zkteco-machines).
2. **No cloud option, but the machine has a network cable?** Use the sync agent: see [Sync agent](/help/sync-agent).
3. **No network at all at that site?** Use USB import once a week or once a month: see [Import from USB](/help/usb-import).
4. **No machine?** Use a kiosk tablet at the door ([Kiosk tablets](/help/kiosk)) or phone punching ([Phone punching](/help/phone-punching)).

> **Tip:** Even with the cloud or the agent, USB import is a good backup. If a machine was offline for a few days, upload its USB file; punches already received are recognised and skipped.

## What these pages are

| Menu item | What it is for |
|---|---|
| **Devices → Devices** | The list of machines. Add a machine, see if it is online, send it commands. |
| **Devices → Kiosks** | Kiosk tablet links: which branch, which ways to punch, photo rules. |
| **Devices → Kiosk tablets** | Approve or block the tablets that open a kiosk link. |
| **Devices → Import from USB** | Upload a log file copied from a machine. |
| **Devices → Unmapped PINs** | Punches from a PIN that no employee has yet. Match them to the right person. |

The **Kiosks** and **Kiosk tablets** pages appear only if your plan includes *Kiosk tablet*. Phone punching works only if your plan includes *Mobile punch (GPS)*.

## How a punch becomes attendance

1. The punch arrives (from a machine, the agent, a USB file, a kiosk or a phone).
2. {app} finds the employee. For machines and USB files this is by the **Machine PIN** on the employee's profile. Kiosk and phone punches already know who it is.
3. If the same person punches twice within the **Double-tap window** (60 seconds by default, under **System → Company settings**), the second punch is kept only as a duplicate.
4. The day is worked out again: present, late, early out and so on. See [How attendance works](/help/how-attendance-works).
5. Some punches need a check first, for example a phone punch from outside the office. They wait in **Attendance → Punch approvals**. See [Punch approvals](/help/punch-approvals).

All punches, from every source, are listed under **Attendance → Punch log**. See [Punch log](/help/punch-log).

## Common problems

### "A machine shows offline"

{app} marks a cloud or agent machine **offline** when it has not been heard from for 15 minutes, and the owner and admins get an alert. Check the power, the network cable or Wi-Fi, and the internet at that site. For agent machines, also check that the office PC is on.

### Punches come in but the person is not on the attendance sheet

The person's **Machine PIN** is missing or different on their employee profile. Open **Devices → Unmapped PINs** and map the PIN to the employee; their punches are attached and the days are worked out again. See [Unmapped PINs](/help/unmapped-pins).

### I can't see the Devices page

Only the owner and admins can manage machines. HR, branch managers and reception can still use **Import from USB**.

### I can't see Kiosks or phone punching

These are part of your plan. If *Kiosk tablet* or *Mobile punch (GPS)* is not included, the pages and the punch button do not appear. See [Billing](/help/billing).
