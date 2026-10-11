---
title: Anomalies
group: Insights
order: 20
summary: Unusual punching patterns that {app} finds by itself every morning, such as two people punching for each other or punches from two places too far apart, for HR to look into.
menu: Attendance → Anomalies
panel: anomalies
screens: anomalies=/admin/anomalies
---

## In short

- {app} checks all punches every morning at 6:00 and lists anything unusual here. You can also click **Scan now**.
- There are five kinds: **Buddy punching**, **Ghost employee**, **Arrival time changed**, **Two branches too close in time** and **Impossible travel**.
- A finding is not proof. There may be an innocent reason. Look at the evidence before you act.
- **Investigate** shows the evidence and marks the finding *Investigating*. **Dismiss** closes it, and **Reopen** puts a dismissed finding back on the open list.
- Anomalies are part of the **AI assistant & anomaly detection** feature. When your plan does not include it, the page is hidden and no checks run.
- The number next to *Anomalies* in the menu is how many findings are still open.

## What you see on this page

![The Anomalies list with open findings](anomalies.jpg)

| Column | What it means |
|---|---|
| **Detected on** | The day {app} found it. |
| **Type** | What kind of pattern it is (see below). |
| **Employee** | The person it is about. For buddy punching, one of the two people. |
| **Explanation** | One sentence about what was seen. |
| **Status** | **Open** (yellow, not looked at yet), **Investigating** (blue) or **Dismissed** (grey). |

The list opens on **Open** findings, newest first. Use the **Status** filter to see findings you are investigating or have dismissed.

## The five kinds of finding

| Type | What {app} saw | What it may mean |
|---|---|---|
| **Buddy punching** | Two people punched within 5 seconds of each other on the same machine, on 3 or more days in the last 30 days. | One person may be punching for the other. Or they simply arrive together, for example by the same van. |
| **Ghost employee** | An active employee with a salary structure, who joined more than 30 days ago, has no punches at all in the last 30 days. | Someone may be on the payroll who does not work here. Or they are on long leave, work off-site, or should be marked as left. |
| **Arrival time changed** | The person's average arrival this week is more than 45 minutes earlier or later than in the four weeks before. | A shift change that was not entered in the roster, or a new habit worth a talk. |
| **Two branches too close in time** | The person punched on machines in two different branches within 20 minutes. | A shared PIN or card, or a machine set to the wrong branch. |
| **Impossible travel** | Two punches at least 5 km apart, closer in time than a car could travel (faster than 120 km/h). Uses the phone's location, or the branch location for machines. | Fake GPS on the phone, or a shared PIN. |

Each finding is reported once: buddy punching and ghost employee once a month for the same people, arrival time changes once a week.

> **AI wording:** {app} rewrites each new explanation as one neutral sentence with a suggested next step. When this month's AI budget is used up, you see the plain rule text instead. The checks themselves are the same either way.

## How to look into a finding

1. Click **Investigate** on an open finding.
2. A window shows the explanation, the facts (for example the days, the machines, the distance and minutes, or the average arrival before and this week) and the punches behind it: date, time, employee, machine or map location, and source.
3. If you want to follow it up, click **Mark as investigating**. The status changes to *Investigating* and your name is saved as the reviewer.
4. When you are done, click **Dismiss**. For an *Investigating* finding, click **Evidence** first if you want to see the punches again.

**Dismiss** asks you to confirm, then the finding leaves the **Open** list. Dismissed findings stay in the list under the **Dismissed** status. If you dismissed one by mistake, choose **Dismissed** in the **Status** filter and click **Reopen**: the finding is *Open* again.

> **Tip:** For buddy punching, compare the [punch photos](/help/punch-photos) if your machines or kiosks take them, and look at the [Punch log](/help/punch-log) for those days.

## Scan now

Click **Scan now** at the top right to check straight away instead of waiting for the next morning. A message tells you how many new findings were added, for example *2 new finding(s)*. Findings that were already listed are not added again.

## Who can use it

Users who can see everyone's attendance can see the page and each finding's **Evidence**: Owner, Admin, HR, Branch manager (their branches only) and Receptionist. Managers do not see this page.

Only users who may edit attendance (Owner, Admin, HR, Branch manager) see **Investigate**, **Dismiss**, **Reopen** and **Scan now**. A Receptionist can look but not change a finding.

## Common problems

### "0 new finding(s)" after Scan now

Nothing new was found, or the findings were already on the list. Change the **Status** filter to see findings you have already investigated or dismissed.

### A ghost employee is someone on long leave

{app} only sees that they have no punches. If they are on leave, apply the leave so their attendance is right, then dismiss the finding. If they have left, mark them as left on their profile. See [Employees](/help/employees).

### Buddy punching for two people who come together

Two people who always arrive together (for example in the same van) can punch within 5 seconds. Look at the punch photos and times; if it is innocent, dismiss it.

### Impossible travel for a phone user

The phone may have had a bad GPS reading, or a fake-location app. Check the two places in the evidence. Ask the employee, and look at [Phone punching](/help/phone-punching) for the location rules.

### The explanation is short and plain

This month's AI budget is used up. The finding is still correct; only the wording is simpler.

### I can't see Anomalies in the menu

Your plan does not include the **AI assistant & anomaly detection** feature, or your role cannot see everyone's attendance. Managers do not have this page.
