---
title: Fingerprint and face machines
group: Devices
order: 20
summary: Add a ZKTeco fingerprint or face machine, connect it to {app} over the internet, and check that it is online.
menu: Devices → Devices
panel: devices | devices/create
screens: zkteco-machines-list=/admin/devices | zkteco-machines-form=/admin/devices/create
---

## In short

- First add the machine under **Devices → Devices → New device**, with its exact **Serial number**. A machine that is not on this list is refused.
- Then, on the machine's own screen, go to **Menu → COMM. → Cloud Server Setting** and enter the server address shown in **Setup instructions**, port **80**, with **Enable Domain Name** on and **Proxy** off.
- Within a minute the machine shows **online** and **Last seen** says *a few seconds ago*. After 15 minutes of silence it shows **offline**.
- Use **Push all employees** to put every employee's PIN and name on the machine. Fingerprints and faces are still enrolled on the machine itself.
- Each employee's **Machine PIN** (on their profile) must match their user ID on the machine.

## What you see on this page

![The Devices list with status, last seen and the actions menu](zkteco-machines-list.jpg)

| Column | What it means |
|---|---|
| **Name** | Your own name for the machine, for example *Reception K40*. |
| **Serial** | The machine's serial number. This is how {app} knows which company a machine belongs to. |
| **Type** | How punches arrive: *Machine (cloud)*, *Machine (office PC agent)* or *USB import*. Kiosk tablets are not listed here; they are under **Devices → Kiosks**. |
| **Branch** | The branch where the machine stands. |
| **Status** | *online* (green), *offline* (red), or blank if it has never connected. For a machine read by the sync agent, a red *Agent error: …* line under it says why the agent could not read the machine. |
| **Last seen at** | When the machine last talked to {app}, for example *2 minutes ago*, or *never*. |
| **Last sync** | When punches were last received from it. |
| **Unsynced** | Agent machines only: punches still on the machine that have not been sent yet. |

Only the **owner** and **admins** can see and change this page.

## Add a machine

1. Go to **Devices → Devices** and click **New device**.
2. **Name**: any name that helps you, for example *Factory gate*.
3. **Type**: choose **Machine (cloud)** for a machine with a cloud option. (For an older machine without one, see [Sync agent](/help/sync-agent).)
4. **Serial number**: on the machine, open **Menu → System Info → Device Info** and copy the **Serial Number** exactly, letter by letter.
5. **Model** (optional): for example K40, K50, MB20, SpeedFace.
6. **Branch**: where the machine is. **Push all employees** then sends only the staff of this branch (and staff with no branch).
7. **Timezone**: leave empty to use the company's timezone. Change it only for a machine in another country.
8. **Comm key** (optional): a number that works like a password between the machine and {app}. If you fill it in here, you must enter the same number on the machine (**COMM. → Comm Key**). If you leave it empty, the machine's comm key must also be empty or 0.
9. Open **Setup instructions** at the bottom of the form. It shows the exact server address for your machine.
10. Click **Create**.

![The New device form with the Setup instructions box](zkteco-machines-form.jpg)

## Connect the machine (on the machine's own screen)

Menu names differ a little between models and firmware. These are the usual ZKTeco steps:

1. On the machine, press **M/OK** (or touch the screen) and log in as the machine's administrator.
2. Open **Menu → COMM.** (on some models **Comm.** or **Communication**).
3. Open **Cloud Server Setting**. On older firmware it is called **ADMS**.
4. Set **Server Mode** to **ADMS**, if your machine shows this option.
5. Turn **Enable Domain Name** to **ON**.
6. **Server Address**: type the address shown in the **Setup instructions** box on the device form (it starts with `adms.`). Type only the address: no `http://` and no `/` at the end.
7. **Server Port**: **80**.
8. **HTTPS**: **off**, if your machine has this option.
9. **Enable Proxy Server**: **OFF**.
10. Save, go back to the main screen and restart the machine (switch it off and on).

The machine also needs internet: check **COMM. → Ethernet** (or **Wi-Fi**) and make sure it has an IP address, a gateway and a DNS server. If **DHCP** is available, turning it on is usually easiest.

> **Tip:** Make sure the date and time on the machine are about right before you start. After that, {app} corrects the machine's clock every night by itself.

## How to know it is online

1. Go back to **Devices → Devices** and refresh the page.
2. Within about a minute, **Status** shows **online** and **Last seen at** shows *a few seconds ago*.
3. Ask someone to punch. Within a minute the punch appears under **Attendance → Punch log**, and **Last sync** changes.

The machine keeps all punches in its memory while it is offline. When the connection comes back it sends them all, so nothing is lost.

If the machine stays silent for 15 minutes, its status changes to **offline** and the owner and admins get a *device offline* alert.

## Send commands to a machine

Click the **⋮** menu at the end of a machine's row. These work for cloud and agent machines. The machine picks up a command on its next check-in, usually within a minute; you see *"1 command(s) queued"*.

| Command | What it does |
|---|---|
| **Sync time** | Sets the machine's clock to the correct time. (This also happens every night by itself.) |
| **Push all employees** | Sends the PIN, name and card number of every active employee who has a **Machine PIN** (of this machine's branch, if it has one). Fingerprints and faces are not sent; enrol those on the machine. |
| **Reboot** | Restarts the machine. You are asked to confirm. |
| **Delete employee from device** | Removes one user from the machine. Type the **Machine PIN**. Use it for staff who have left. |
| **Edit** / **Delete** | Change the machine's details, or remove it from {app}. A deleted machine's punches are refused from then on. |

## Common problems

### The machine stays blank or never shows online

- The **Serial number** in {app} is not exactly the one on the machine. Compare them letter by letter (**Menu → System Info → Device Info**) and fix it with **Edit**.
- The server address, port or domain setting on the machine is wrong. Check each step under *Connect the machine* again: port **80**, **Enable Domain Name** ON, **Proxy** OFF, no `http://` in the address.
- The machine has no internet. Check the cable or Wi-Fi, and that **DNS** is filled in under **COMM. → Ethernet**.

### "This serial number is registered to another company. Contact support."

Another company in {app} already added this serial number. Check that you typed it correctly. If the machine really is yours (for example, bought second-hand), contact support to move it.

### The machine was online but now shows offline

It has not been heard from for 15 minutes. Usually the power, the network or the internet at that site is down. Punches are kept on the machine and arrive when it is back online.

### The machine connects but punches are refused after I set a comm key

The **Comm key** in {app} and on the machine (**COMM. → Comm Key**) must be the same number. Either set both to the same number, or clear it in {app} and set 0 on the machine.

### Punches arrive but go to Unmapped PINs

The user ID on the machine does not match any employee's **Machine PIN**. Fill in the Machine PIN on the employee's profile, or map it under **Devices → Unmapped PINs**. See [Unmapped PINs](/help/unmapped-pins).

### "This device type does not accept remote commands."

Commands work only for **Machine (cloud)** and **Machine (office PC agent)**. USB and kiosk entries cannot receive commands.

### Punch times are a few hours off

The machine's **Timezone** is wrong. Leave it empty to use the company timezone (Pakistan: Asia/Karachi), or set the right one with **Edit**, then use **Sync time**.
