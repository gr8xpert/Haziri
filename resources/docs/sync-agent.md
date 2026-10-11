---
title: Sync agent
group: Devices
order: 30
summary: Connect older machines that have only a network cable (no cloud option) through a small program on one office PC.
menu: Devices → Devices → Sync agent
panel: devices
screens: sync-agent-window=/admin/devices >> Sync agent
---

## In short

- The **sync agent** is a small Windows program. It runs on one office PC, reads your machines over the office network every minute, and sends the punches to {app}.
- Use it for machines with no **Cloud Server Setting** / **ADMS** option. Nothing changes on your network or router.
- First add each machine under **Devices** with type **Machine (office PC agent)** and its **LAN IP**. Then click **Sync agent** and download the program and the setup file.
- Put both files in one folder, test once, then install it as a Windows service so it starts by itself.
- The machines show **online** on the Devices page while the agent can reach them.

## What the agent does

- Every 60 seconds it reads new punches from each machine and sends them. Punches already sent are not sent again.
- It never clears the machine's memory, so the machine keeps its own copy.
- Once a week it sends the list of users on the machine (PIN and name only). Users without a matching employee show up under **Devices → Unmapped PINs**, so they are easy to match.
- It carries out the commands you send from the Devices page (**Sync time**, **Push all employees**, **Reboot**, **Delete employee from device**).
- Fingerprints and face data are never read or sent.

## What you need

- A **Windows PC that is always on**, on the same network as the machines. The PC that runs your old attendance software is fine.
- The **LAN IP** of each machine: on the machine, **Menu → COMM. → Ethernet → IP Address** (for example 192.168.1.201).
- The **serial number** of each machine: **Menu → System Info → Device Info → Serial Number**.
- The machine's **Comm key**, if it has one (**COMM. → Comm Key**). Most machines have 0 (none).
- Internet on the PC.

## Step 1: Add the machines

1. Go to **Devices → Devices** and click **New device**.
2. Fill in **Name**, and choose **Type**: **Machine (office PC agent)**.
3. Type the **Serial number** exactly as the machine shows it.
4. Fill in **LAN IP (for the agent)**, and the **Comm key** if the machine has one.
5. Choose the **Branch** and click **Create**.
6. Repeat for every machine on this network.

## Step 2: Download the program and the setup file

1. On **Devices → Devices**, click **Sync agent** at the top.
2. Click **Download program**, then **Download setup file**.
3. Make a folder on the PC and put both files in it. Use the folder name shown in the window's step 3; then the commands in the window work without changes. The setup file must be called `agent.yaml`.

![The Sync agent window with the steps and the download buttons](sync-agent-window.jpg)

The setup file is ready to use. It already contains the server address, a new private key for your company, and every machine of type *Machine (office PC agent)* with its LAN IP and comm key. You do not need to type anything in it.

> **Tip:** If a machine has no LAN IP in {app}, the file shows `0.0.0.0` for it. Add the IP on the machine's page and download the setup file again, or open `agent.yaml` in Notepad and replace `0.0.0.0` with the IP.

## Step 3: Test it

1. Open the folder, click in the address bar, type `cmd` and press Enter. A Command Prompt opens in that folder.
2. Type the test command shown in step 4 of the **Sync agent** window and press Enter.
3. Go back to **Devices → Devices** and refresh. Within a minute the machines show **online**.

## Step 4: Make it start with Windows

So that it keeps running after a restart, install it as a Windows service with the free tool **NSSM**:

1. Download NSSM from nssm.cc (the link is in the **Sync agent** window) and unzip it.
2. Open **Command Prompt as administrator** (right-click Command Prompt → *Run as administrator*).
3. Copy the four lines shown in step 5 of the **Sync agent** window and run them. If you used a different folder, change the folder in those lines.
4. That's it: the agent now runs in the background and starts by itself when the PC starts.

## Agent keys

Each setup file you download contains its own new **agent key**: a password that lets the agent send punches for your company.

- **New agent key** creates a key by hand (only needed if you edit `agent.yaml` yourself). The key is shown **once**; copy it straight away.
- **Agent keys** lists the active keys with when each was last used. Tick a key and click **Revoke selected** to stop it working, for example after a PC is replaced or stolen. An agent using a revoked key can no longer send punches.

> **Tip:** Keep `agent.yaml` private. Anyone with this file can send punches for your company.

## Common problems

### The machines do not show online after the test

- The PC cannot reach the machine. From Command Prompt on the PC, type `ping` and the machine's IP (for example `ping 192.168.1.201`). If there is no reply, check the IP, the cable and that both are on the same network.
- The IP in `agent.yaml` is `0.0.0.0` or wrong. Fix the **LAN IP** on the device and download the setup file again.
- The PC has no internet.

### "Unknown device serial for this company. Add it under Devices first (type: agent)."

The agent read a machine whose serial number is not in {app}, or the machine was added with type *Machine (cloud)*. Add it under **Devices** with type **Machine (office PC agent)** and the exact serial number, then download a new setup file.

### "The program is not on this server yet."

The **Download program** button is missing because the program has not been uploaded to the server. Ask support to send you the program file.

### Machines go offline every night

The PC goes to sleep or is switched off. Set the PC's power plan so it never sleeps, and keep it on. The machines keep their punches; they arrive when the agent runs again.

### It worked in the test but stops after I close the window or restart the PC

The service is not installed. Follow **Step 4** to install it with NSSM.

### The wrong comm key

If the machine has a comm key, the same number must be in {app} (**Comm key** on the device) before you download the setup file. Otherwise the agent cannot read the machine.
