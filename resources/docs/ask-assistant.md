---
title: Ask {app}
group: Insights
order: 30
summary: Ask a question about attendance, leave or staff in plain words and get the answer as a table you can download.
menu: Reports → Ask {app}
panel: ask
screens: ask-assistant=/admin/ask
---

## In short

- Type a question in plain English, for example *Who in Sales was late more than 3 times in August?*, and click **Ask**. The answer comes back as a table.
- It can answer from employees, daily attendance, leave and punch times. It cannot see salaries or payroll.
- It only reads data. It can never change or delete anything.
- This page appears only if your plan includes **AI assistant & anomaly detection**. Each question uses part of a monthly AI budget.
- Always check important answers against the normal reports before you act on them.

## What you see on this page

![The Ask page with the question box](ask-assistant.jpg)

- **The question box** with the **Ask** button.
- Under it: *Answers are read-only queries over attendance, leave and employee data*, and **AI budget left this month**: how many tokens (units of AI use) are left.
- After you ask: the number of rows, the answer table, an **Excel** button (if you may download reports), thumbs up / thumbs down buttons, and **Show SQL**.

If your company uses {app} under its own brand, the menu item may be called **Ask AI** or another name your company chose. It is the same page.

## How to ask a question

1. Open **Reports → Ask {app}**.
2. Type your question (at least 5 and at most 500 characters).
3. Click **Ask**. You see *Reading your data...* for a few seconds.
4. Read the table. The screen shows up to 500 rows; the **Excel** download has them all (up to 5,000).

Good questions name **who**, **what** and **when**:

- *How many people were absent each day last week?*
- *List employees in the Lahore branch with more than 2 absences in September 2026.*
- *Who took sick leave this month, and for how many days?*
- *Average arrival time per department in the last 30 days.*
- *Which employees joined in 2026 and have no punches this week?*

> **Tip:** Use the names your company uses: branch, department and designation names, and leave type names or codes. Give dates as months or exact dates; "this month" and "last week" also work.

## What it can and cannot answer

It can answer from:

- **Employees**: code, name, branch, department, designation, employment type, status, gender, joining and leaving dates, work mode.
- **Attendance days**: status, late minutes, early-out minutes, worked minutes, overtime minutes, late and missing-out marks.
- **Leave**: type, paid or unpaid, dates, day part, days and status.
- **Punches**: how many punches a day, first and last punch, and where they came from.

It cannot answer about salaries, payroll, tax, loans, devices, visitors or settings. If a question cannot be answered from this data, it tells you so instead of guessing.

## Download, feedback and SQL

- **Excel** downloads the answer. Only users who may download reports see it (Owner, Admin, HR).
- **Thumbs up / thumbs down** tells us whether the answer was right. It helps improve the assistant; it does not change the answer.
- **Show SQL** shows the database query the assistant wrote. You do not need it, but someone technical can use it to check how the answer was worked out.

## Who can use it

- Your plan must include **AI assistant & anomaly detection**.
- Owner, Admin and HR. Users limited to some branches (for example Branch managers) do not get this page, because the assistant answers from the whole company's data. Managers do not get it either.

Every question is saved with your name, the question and the answer size, for the company's records.

## The AI budget

Each company has a monthly AI budget, counted in tokens. Every question uses some of it; long or complicated questions use more. The budget starts again on the 1st of each month. The same budget is used by the other AI features (reading leave certificates, the setup assistant and the anomaly wording).

## Common problems

### "AI features are not enabled for your company."

Your plan does not include the AI assistant. The owner can add it. See [Billing](/help/billing).

### "This month's AI budget is used up."

Your company has used all of this month's AI budget. It starts again on the 1st of next month. Until then, use the normal [reports](/help/reports-overview).

### "Sorry, I could not build a safe query for that question."

The assistant tried twice and could not write a safe query. Ask the question more simply, one thing at a time, with clear names and dates.

### "That question cannot be answered from attendance data."

The question is about something the assistant cannot see, such as salaries or devices. Use the matching report instead, for example [Payroll reports](/help/payroll-reports).

### "The question field must be at least 5 characters."

The question is too short. Write a full question.

### The answer looks wrong

The assistant can misunderstand a question. Click thumbs down, then check **Show SQL** or ask again in other words, for example name the month exactly. For anything that affects pay or discipline, use the normal reports.

### I can't see Ask {app} in the menu

Your plan does not include the AI assistant, your role does not have full report access, or your user is limited to some branches.

### There is no Excel button

You do not have permission to download reports. Ask the owner or HR to run the question and download it.
