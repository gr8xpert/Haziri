---
title: Exemptions
group: Shifts & holidays
order: 60
summary: Excuse late arrivals, or the whole day, for everyone on a date when something outside staff control happened, such as a strike or heavy rain.
menu: Shifts & Holidays → Exemptions
panel: attendance-exemptions
screens: exemptions-list=/admin/attendance-exemptions | exemptions-new=/admin/attendance-exemptions >> New exemption
---

## In short

- An exemption excuses one date for a whole group, for example *Heavy rain, 15 Sep 2026, late arrivals excused*.
- **Late arrivals excused**: nobody is marked late that day.
- **Whole day excused (absent exempted)**: nobody is late or half day, and anyone who did not come shows **Exempted** instead of absent. An exempted day is paid like a present day.
- It can apply to the whole company, one branch or one department.
- It is not a holiday: people who came are still present as normal. Use [Holidays](/help/holidays) when the office is closed.
- Only the Owner, Admin and HR can use this page.

## What you see on this page

![The Exemptions list with date, type, reason and who it applies to](exemptions-list.jpg)

| Column | What it means |
|---|---|
| **Date** | The excused day. |
| **Type** | *Late arrivals excused* or *Whole day excused (absent exempted)*. |
| **Reason** | Why, for example *Transport strike*. |
| **Applies to** | *Whole company*, or the branch or department name. |

The newest dates are at the top. Use the **filters** button to show one type only.

## Add an exemption

1. Click **New exemption**.
2. Pick the **Date**. It can be in the past, or up to one month ahead.
3. Pick the **Type**.
4. Write the **Reason**. It is required and shows in reports.
5. **Applies to** — *Whole company*, *One branch* or *One department*. For a branch or department, choose **Which one**.
6. Click **Create**.

![The new exemption form](exemptions-new.jpg)

{app} works out attendance again for that date for everyone it covers. If the date is in a locked month, nothing changes until you unlock it; see [Month lock](/help/month-lock).

## What changes on the day

| | Late arrivals excused | Whole day excused |
|---|---|---|
| Came late | Present, not late | Present, not late |
| Came, worked only a few hours | Can still be a half day | Present (no half day for short hours) |
| Did not come | Absent | **Exempted** (code *EX*), paid |
| Left early | Still shows early out | Still shows early out |

The day gets an *Exempted* mark, so you can see it was excused. Exempted days are listed in the **Exemptions** report under [Other reports](/help/other-reports).

> **Tip:** An exemption only works on normal working days. On a weekly off, holiday or a full day of leave, nothing changes.

## Change or remove an exemption

Use **Edit** or **Delete** on the row. Attendance for that date is worked out again for everyone affected, including the old group if you changed **Applies to**.

## Common problems

### Staff are still marked late

Check the **Date**, and that **Applies to** includes their branch or department. Also check the month is not locked.

### The date I need is not allowed

The date can be at most one month ahead. Add it closer to the day.

### Someone who stayed home still shows absent

The type is *Late arrivals excused*, which only removes lates. Edit it and change the type to *Whole day excused (absent exempted)*.

### I wanted to close the office for the day

Use a holiday instead under [Holidays](/help/holidays). An exemption still expects people to work; it only stops them being punished for it.

### The reason field is required

Every exemption needs a reason, so that later anyone looking at the reports knows why the day was excused. Write a short one, for example *Heavy rain*.
