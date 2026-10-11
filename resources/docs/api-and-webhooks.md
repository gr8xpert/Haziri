---
title: API and webhooks
group: System
order: 20
summary: Let other software (payroll, ERP, your own tools) read data from {app} or send punches to it, and get told the moment something happens.
menu: System → API tokens / Webhooks
panel: api-tokens | webhooks
screens: api-and-webhooks-tokens=/admin/api-tokens | api-and-webhooks-token-new=/admin/api-tokens >> New token | api-and-webhooks-webhooks=/admin/webhooks
---

## In short

- These pages appear only if your plan includes **Public API & webhooks**, and only for the owner and admins.
- An **API token** is a secret key that lets another program read your data (*read*) or send punches (*punches:write*). It is shown **only once** when you make it.
- A **webhook** is a web address of your other program. {app} sends it a message when a punch arrives, a day changes, leave is decided, a month is locked or payroll is made.
- Revoke a token the moment you no longer need it, or if it may have leaked.
- This page is for the person who connects the software (usually your IT person or software vendor). Give them this page and the token.

## API tokens

![The API tokens list](api-and-webhooks-tokens.jpg)

| Column | What it means |
|---|---|
| **Name** | What the token is for, for example *Payroll software*. |
| **Abilities** | What it may do: *read* and/or *punches:write*. |
| **Last used at** | When it was last used, or *never*. |
| **Revoked at** | *active*, or the date it was switched off. |

### Make a token

1. Go to **System → API tokens** and click **New token**.
2. Type a **Name** that says who uses it.
3. Tick the **Abilities**:
   - *Read employees, attendance, leave, payroll input* (read)
   - *Send punches* (punches:write)
   Tick only what the program needs.
4. Submit. A message shows *Token created — copy it now, it is shown only once* with the token.
5. Copy the token and give it to your IT person in a safe way (not in a group chat).

![Making a new API token](api-and-webhooks-token-new.jpg)

If you lose the token, you cannot see it again. Make a new one and revoke the old one.

### Revoke a token

Click **Revoke** on the row and confirm. Any program using it stops working at once. A revoked token cannot be switched on again.

### For your IT person

- The address is your company's own {app} address followed by `/api/v1`, for example `https://yourcompany.…/api/v1/employees`.
- Send the token in the header `Authorization: Bearer <token>`.
- At most 60 requests per minute per token.
- With *read*: `GET employees` (filter `status`, `per_page` up to 200), `GET attendance?from=2026-09-01&to=2026-09-30` (at most 93 days, optional `employee` code), `GET leave/balances` (optional `year`), `GET periods/2026-09/payroll-input`.
- With *punches:write*: `POST punches` with `employee_code` (or `pin`), `timestamp`, and optional `direction` and `external_id`. A punch more than 5 minutes in the future is refused.
- Errors come back as JSON, for example `invalid_token`, `missing_ability`, `api_not_enabled` or `unknown_employee`.

## Webhooks

![The Webhooks list](api-and-webhooks-webhooks.jpg)

The list shows the **Url**, the **Events** it receives, whether it **Is active**, and how many **Deliveries** were made.

### Add a webhook

1. Go to **System → Webhooks** and click **New webhook endpoint**.
2. In **Url**, paste the address your IT person gives you. It must be a public `https://` (or `http://`) address.
3. Tick the **Events** to send:

| Event | Sent when |
|---|---|
| `punch.created` | A new punch arrives. |
| `attendance_day.updated` | An employee's day is worked out again (for example after a punch or a correction). |
| `leave.decided` | A leave request is approved or rejected. |
| `period.locked` | A month is locked. |
| `payroll.generated` | A payroll run is made. |

4. Keep **Is active** on. Click **Create**.

### Test and secure it

- **Send test** sends a small `ping` message to the address. You see *Test delivery queued*. Ask your IT person whether it arrived.
- **Show secret** (confirm first) shows the signing secret and how to check the signature. Your IT person uses it to make sure a message really came from {app}: every message carries the headers `X-Webhook-Timestamp` and `X-Webhook-Signature` (`sha256=` + HMAC-SHA256 of `{timestamp}.{raw body}` with the secret), plus `X-Webhook-Event` and `X-Webhook-Delivery`.
- **Edit** to change the address or events; **Delete** to stop sending.

If the other program does not answer, {app} tries again up to 5 times, over about 45 minutes, then gives up on that message.

## Common problems

### I can't find API tokens or Webhooks

Your plan does not include *Public API & webhooks*, or you are not the owner or an admin.

### The other program gets "invalid_token"

The token is wrong, was copied with a space, or was revoked. Make a new token and send it again.

### The other program gets "missing_ability"

The token does not have the needed ability. For example, sending punches needs *Send punches*. Make a new token with the right abilities; abilities cannot be added to an existing token.

### The other program gets "unknown_employee"

The punch was sent with an employee code or PIN that {app} does not know. Check the code on **People → Employees**.

### "The URL points to a private or reserved address."

Webhook addresses must be reachable on the internet. Addresses such as `localhost` or `192.168…` are refused. Ask your IT person for the public address.

### "The host name does not resolve."

The address does not exist on the internet, often a typing mistake. Check the address.

### The Deliveries number goes up but the other program received nothing

**Deliveries** counts every message {app} has prepared, including ones that failed. The panel does not show whether each one arrived. Check that the webhook **Is active** and the address is right, and ask your IT person to look at their program's log. Messages are retried for about 45 minutes before {app} gives up.
