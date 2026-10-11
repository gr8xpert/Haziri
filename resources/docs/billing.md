---
title: Billing
group: Company setup
order: 50
summary: See your monthly invoices, how to pay them, and tell the support team you have paid.
menu: System → Billing
panel: billing
screens: billing=/admin/billing
---

## In short

- Only the **owner** can open Billing. Admins cannot.
- An invoice is made on the 1st of each month: active employees × the price per employee on your plan, plus any fixed plan fee.
- Each invoice is due 10 days later. Pay by bank transfer, JazzCash, Easypaisa or cash, as shown under **How to pay**.
- After paying, choose the **Method**, type the **Transaction ID** and click **I have paid**. The invoice shows *Awaiting confirmation* until the support team confirms it.
- If an invoice stays unpaid more than 15 days after its due date, the account is paused until payment is confirmed.

## What you see on this page

![The Billing page with payment instructions and the invoice list](billing.jpg)

**How to pay** at the top shows the bank account and mobile wallet numbers. Put the invoice number in the payment reference.

Below it, your last 24 invoices, newest first:

| Column | What it means |
|---|---|
| **Invoice** | The invoice number, for example `HZ-202609-0042`. Use it as the payment reference. |
| **Month** | The month the invoice is for. |
| **Employees** | How many active employees were counted (everyone not marked as left). |
| **Amount** | The total, for example Rs 12,500. |
| **Due** | The last day to pay. |
| **Status** | *Open* (yellow, not paid yet), *Awaiting confirmation* (blue, you reported a payment) or *Paid* (green). |

On an open invoice, the last column has the payment form.

## How the invoice is worked out

On the 1st of each month {app} counts your active employees, that is everyone whose status is not *Left*. The invoice is:

- employees × the price per employee on your plan, plus
- the plan's fixed fee, if your plan has one.

Example: 50 employees × Rs 200 = Rs 10,000, plus a plan fee of Rs 2,500 = Rs 12,500.

The invoice is due 10 days after the 1st. During a free trial there are no invoices; the first one comes at the start of the month after you start a plan.

> **Tip:** Mark staff who have left as *Left* (on **People → Employees**) before the 1st, so they are not counted on the next invoice.

## How to report a payment

1. Pay the **Amount** using the details under **How to pay**. Put the invoice number in the reference.
2. On the invoice row, choose the **Method**: *Bank transfer*, *JazzCash*, *Easypaisa* or *Cash*.
3. Type the **Transaction ID** (the reference from your bank or wallet receipt).
4. Click **I have paid**. You see *Thank you — we will confirm the payment shortly.*
5. The status changes to *Awaiting confirmation*. When the support team has checked the payment, it changes to *Paid*.

## If you don't pay

If an invoice is still open 15 days after its due date, the account is paused. Staff cannot use /me, punches from machines are not accepted, and every /admin page except **Billing** is closed: the owner can still sign in, and is taken straight to **Billing** to pay and click **I have paid**. Others see *"This account is suspended. Please contact support."* Your data is kept. Once the payment is confirmed, the account opens again.

## Employee limit

Some plans allow a maximum number of active employees. When you reach it, adding an employee shows *"Your plan allows N active employees. Mark someone as left or upgrade the plan."* Mark leavers as *Left*, or contact support to change the plan.

## Common problems

### I can't find Billing

Only the owner sees it. Admins and HR do not. In a company running on its own private server, there is no Billing page at all.

### "No invoices yet"

Your first invoice appears at the start of next month. During a free trial no invoices are made.

### "The payment method field is required" under the invoice

The **Method** or **Transaction ID** is empty. The message under the invoice says which. Choose a method, type the transaction ID, and click **I have paid** again.

### The invoice still says Awaiting confirmation

The support team has not checked the payment yet. This is done by hand and can take a working day. If it takes longer, contact support with the transaction ID.

### The employee count is higher than my staff

People who left are still counted until they are marked as *Left*. Mark them on **People → Employees** (bulk action **Mark as left**). The current invoice is not changed; contact support if it is wrong.

### "Your free trial has ended. Please contact support to continue."

The trial is over. Contact support to choose a plan; your data is kept.
