---
title: Notifications, WhatsApp and SMS
group: System
order: 10
summary: Which messages {app} sends and to whom, how to change their wording, how to connect WhatsApp and SMS, and how to check what was sent.
menu: System → Notification texts / Notification log / WhatsApp & SMS
panel: notification-templates | notification-logs | integrations
screens: notifications-texts=/admin/notification-templates | notifications-log=/admin/notification-logs | notifications-whatsapp-sms=/admin/integrations
---

## In short

- {app} sends messages for events such as a new leave request, a decision on leave, a forgotten punch-out, a machine going offline, or the daily summary.
- Messages go by **email**, **in-app** (the bell at the top right, and in the staff app), **WhatsApp** and **SMS**. Each event has its own default channels.
- **Notification texts**: change the wording of a message, in English or Urdu, or switch a message off.
- **WhatsApp & SMS**: connect your WhatsApp Business (Meta) account and your SMS gateway. Until then, those messages are only logged, not sent. WhatsApp also needs WhatsApp alerts in your plan.
- **Notification log**: every message, with its status (*sent*, *failed*, *skipped*, *queued*). Look here first when someone says "I didn't get the message".

## The messages {app} sends

| Event | Usually goes to | Default channels |
|---|---|---|
| Leave requested | The approver (manager or HR) | Email, in-app, WhatsApp |
| Leave decided | The employee | Email, in-app, WhatsApp |
| Correction requested | The approver | Email, in-app |
| Correction decided | The employee | Email, in-app |
| Device offline | Owner and admins | Email, in-app |
| Unmapped pins pending | Owner, admins and HR | In-app |
| Daily summary | Owner, admins and HR, at the time set in Company settings | Email |
| Employee absent no leave ("Not in yet") | The team's manager, at the time set in Company settings | In-app, WhatsApp |
| Employee late | The employee's manager (owner, admins and HR if they have no manager), once a day, only when *Tell managers about late arrivals* is on | In-app |
| Missing punch out | The employee | In-app, WhatsApp, SMS |
| Period locked | The owner | Email, in-app |
| Shift swap asked / answered / pending | The colleague, then the employee who asked, then owner, admins and HR | In-app, email (and WhatsApp for asked and answered) |
| Holiday unconfirmed | Owner, admins and HR | Email, in-app |
| Probation ending | Owner, admins, HR and the employee's manager | Email, in-app |
| Contract ending | Owner, admins and HR | Email, in-app |
| Birthday today | Owner, admins and HR | In-app |
| Payslip ready | The employee | Email, WhatsApp |
| Broadcast | Staff, for announcements | In-app, email |

Each person gets the message in their language (English or Urdu), set on their login under **Users & roles**. WhatsApp and SMS need a phone number on the login or the employee. Email needs an email address.

> **Note:** *Employee late* goes out the first time a day is counted as late, for today or yesterday only. Fixing older days, or a second late punch on the same day, does not send it again.

The timing of the daily summary, the "Not in yet" alert and the quiet hours (no WhatsApp/SMS at night) are set on [Company settings](/help/company-settings).

## Change the wording of a message

![Notification texts: your own versions of the messages](notifications-texts.jpg)

**System → Notification texts** lists only the texts you have changed. All others use the standard wording.

1. Click **Customise a text**.
2. Pick the **Event**. The standard **Subject** and **Body** fill in.
3. Pick the **Channel**: *Email / in-app*, *WhatsApp*, *SMS* or *In-app only*.
4. Pick the **Locale**: *English* or *اردو*. Pick the locale first, then the event, to get the Urdu standard text.
5. Change the **Subject** (for email) and the **Body**. Keep the words in double curly brackets, such as `{{ employee }}`, `{{ from }}` or `{{ date }}`; {app} fills them in.
6. For WhatsApp: fill in **WhatsApp approved template name**. Meta requires an approved template for messages a business starts.
7. Keep **Enabled** on. Click **Create**.

How {app} chooses the text: first your text for that exact channel, then your *Email / in-app* text, then the standard text. So one *Email / in-app* text also covers WhatsApp and SMS, unless you make a separate one for them.

- **Switch a message off:** customise it and switch **Enabled** off. That message is no longer sent on that channel. An *Email / in-app* text that is switched off also stops WhatsApp and SMS for that event, unless they have their own text.
- **Go back to the standard text:** click **Reset to default** on the row and confirm.

## Connect WhatsApp and SMS

Only the owner and admins can open **System → WhatsApp & SMS**.

![The WhatsApp & SMS page](notifications-whatsapp-sms.jpg)

### WhatsApp (Meta WhatsApp Cloud API)

This needs WhatsApp alerts in your plan, and a WhatsApp Business account at Meta.

1. In **Whatsapp provider**, pick *Meta WhatsApp Cloud API*.
2. Fill in **Phone number ID** and **Permanent access token** from your Meta Business account. Leave **Graph API version** as it is unless your IT person says otherwise.
3. Click **Save settings**.

### SMS (HTTP gateway)

Most Pakistani SMS companies give you a web address (URL) to send messages. Ask them for the details.

1. In **Sms provider**, pick *HTTP gateway*.
2. Fill in **Gateway URL**, choose **Sms http method** (*POST* or *GET*, as your SMS company says), **Sender ID / mask**, and **API key / password**.
3. In **Request parameters**, list the names your SMS company expects, with the values `{to}`, `{message}`, `{sender}` or `{api_key}`. For example: `to` = `{to}`, `message` = `{message}`, `api_key` = `{api_key}`.
4. **Success response contains**: a word your gateway sends back when a message is accepted, for example `OK`. Leave empty to treat any normal reply as success.
5. Click **Save settings**.

Saved passwords and tokens are kept secret: the field shows *•••• saved — leave blank to keep*. Leave it empty to keep the saved one; type a new one to replace it.

### Test it

Click **Send test message** at the top right, pick **Channel** (*WhatsApp* or *SMS*), type a **Phone** number, and submit. You see *Sent* or *Failed* with the reason.

> **Tip:** If the provider is still *Not set up (log only)*, the test says *Sent* with the word *log*, but no message leaves {app}. Choose a real provider first.

The sign-in code for the staff app (sign in with phone number) is sent by SMS, so set up SMS if staff will sign in that way.

## Check what was sent

![The Notification log](notifications-log.jpg)

**System → Notification log** lists every message, newest first:

| Column | What it means |
|---|---|
| **Created at** | When the message was made. |
| **Event** | Which message, for example *Leave Decided*. |
| **Channel** | Email, In-app, WhatsApp, SMS or Log. |
| **Recipient** | Who it was for (email or phone). |
| **Status** | *sent* (green), *failed* (red), *queued* (yellow, waiting, for example during quiet hours) or *skipped* (grey). |
| **Attempts** | How many times {app} tried. A failed message is tried up to 3 times. |
| **Error** | Why it failed or was skipped. |

Use the **filters** to show one **Status** or one **Channel**. Search by event or recipient.

## Common problems

### Status "skipped" with error "channel disabled"

WhatsApp is not in your plan. Ask support about WhatsApp alerts, or customise the message for another channel.

### Status "skipped" with error "template disabled"

Someone switched this message off under **Notification texts**. Edit it and switch **Enabled** on, or click **Reset to default**.

### Status "skipped" with error "recipient unreachable"

The person has no phone number (for WhatsApp or SMS) or no email address. Add it on their login under **Users & roles** or on their employee profile.

### WhatsApp shows "sent" but nobody received anything

The **Whatsapp provider** is still *Not set up (log only)*: messages are written to the log only. Set up Meta WhatsApp Cloud API on **WhatsApp & SMS**. If it is set up, check that the **WhatsApp approved template name** is filled in on the text.

### "This channel is disabled for your company."

You sent a test WhatsApp message, but WhatsApp alerts are not in your plan.

### "The URL points to a private or reserved address."

The **Gateway URL** is an internal address (for example `192.168…` or `localhost`). {app} can only send to a public internet address. Use the public URL from your SMS company.

### WhatsApp or SMS messages arrive hours late

They were sent in quiet hours and held until the morning. Change **WhatsApp/SMS quiet from** / **… until** on **Company settings**.

### I can't find these pages

They are for the owner and admins only.
