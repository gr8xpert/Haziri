---
title: Import from USB
group: Devices
order: 40
summary: Copy the attendance log from a machine to a USB stick and upload it, for sites without internet or as a backup.
menu: Devices → Import from USB
panel: usb-import
screens: usb-import=/admin/usb-import
---

## In short

- On the machine, download the attendance log to a USB stick. You get a file ending in `attlog.dat`.
- In {app}, go to **Devices → Import from USB**, choose the **Device**, pick the file and click **Preview**.
- The preview shows how many rows there are, how many were already imported, which PINs have no employee, and the dates covered. Then click **Import**.
- Uploading the same file twice is safe: punches already in {app} are skipped.
- The owner, admins, HR, branch managers and reception can import.

## Get the file from the machine

Menu names differ a little between models. The usual ZKTeco steps:

1. Put a USB stick into the machine's USB port.
2. Press **M/OK** (or touch the screen) and log in as the machine's administrator.
3. Open **Menu → Data Mgt.** (on some models **USB Manager** or **Pen Drive Mgt.**).
4. Choose **USB → Download attendance** (or **Download → Attendance Data**).
5. Wait for *"Download completed"*, then take out the stick.

On the stick you will find a file such as `1_attlog.dat` or `BOCK190960032_attlog.dat`. That is the file to upload.

> **Tip:** The machine keeps its own copy of the log, so you can download it again at any time.

## Import the file

1. Go to **Devices → Import from USB**.
2. **Device**: choose the machine the file came from. If it is not in the list, leave *Unknown / other*.
3. **attlog.dat, CSV or XLSX**: click and choose the file (up to 20 MB).
4. Click **Preview**. Nothing is saved yet.
5. Check the four numbers:
   - **Rows**: punches found in the file.
   - **Already imported**: punches {app} already has (from an earlier upload, or from the machine itself). These are skipped.
   - **Unmapped PINs**: user IDs in the file that no employee has as **Machine PIN**.
   - **Dates**: the first and last date in the file.
6. Under **Map PINs to employees** you can pick an employee for each unknown PIN now. This is optional: you can leave it as *Leave unmapped* and do it later under **Devices → Unmapped PINs**.
7. Click **Import**. You see a message such as *"Import finished: 412 imported, 1,830 duplicates, 6 unmapped"*.

![The Import from USB page with a preview of the file](usb-import.jpg)

The days covered by the file are worked out again straight away, so the attendance sheet and reports update.

> **Tip:** Always choose the right **Device**. Then {app} can recognise punches that the same machine already sent over the internet or the agent, and skips them. With *Unknown / other* it cannot.

## Your own spreadsheet (CSV or Excel)

If your punches come from somewhere else, you can upload a CSV or Excel (XLSX) file with these columns in the first row:

| Column | Example | Notes |
|---|---|---|
| `employee_code_or_pin` | E-0042 | The employee's **Code**, or their **Machine PIN**. |
| `date` | 2026-09-15 | Year-month-day. |
| `time` | 09:05 | Hours and minutes, 24-hour clock. Seconds are optional. |
| `direction` | in | Optional: `in` or `out`. |

Rows that do not follow this are skipped and listed in the preview.

## Recent imports

At the bottom of the page you see the last 10 imports: when, which device, the file name, and how many rows were imported, duplicates and unmapped.

## Common problems

### "Line 5: not an attendance line"

Some lines in the file are not punches (for example an empty or damaged line). They are skipped and listed under *lines skipped* in the preview. The rest of the file still imports. If every line is skipped, you probably picked the wrong file: use the one ending in `attlog.dat`.

### "Row 3: need employee_code_or_pin, date (YYYY-MM-DD) and time (HH:MM)"

In a CSV or Excel file, that row has an empty code, or a date or time in another format. Fix the row (for example `2026-09-15` and `09:05`) and upload again.

### Everything shows as "Already imported"

These punches are already in {app}, from an earlier upload or because the machine sent them over the internet. Nothing more needs doing.

### Many punches are unmapped

The **Machine PIN** is missing on those employees' profiles. Map them in the preview, or later under **Devices → Unmapped PINs**. Their punches are kept and attached once mapped. See [Unmapped PINs](/help/unmapped-pins).

### The times are a few hours wrong

The file uses the machine's own clock. {app} reads it with the device's **Timezone** (or the company's, if none is set). Check the clock on the machine, and the **Device** you chose.

### An employee is missing from the Map PINs list

Only employees who do not have a Machine PIN yet are listed. If the employee already has a different PIN, change it on their profile first.

### In and out look wrong after a USB import

By default {app} ignores the In/Out keys pressed on machines and works out in and out by itself. This is set by **Trust In/Out keys on machines** under **System → Company settings**.
