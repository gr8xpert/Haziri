---
title: Branding and your own domain
group: Company setup
order: 40
summary: Show your own app name, logo, icon and colour to your staff, and open {app} on your own web address.
menu: System → Branding & domain
panel: branding
screens: branding=/admin/branding
---

## In short

- This page appears only if your plan includes **white label** (own domain, logo, colours, name). Only the owner and admins can open it.
- **App name**, **Logo**, **App icon** and **Theme colour** replace the product name and look everywhere your staff see it: admin panel, staff app, kiosk, PDFs and messages.
- Images must be PNG, JPEG or WebP, at most 150 KB each.
- **Custom domain**: add an address such as `attendance.yourcompany.pk`, then create a CNAME record at your domain provider. HTTPS is switched on by the support team within one working day.
- If your provider manages your branding, the page is locked and shows *Branding is managed by your provider*.

## What you see on this page

![The Branding & domain page: name, look and custom domain](branding.jpg)

### Name

| Field | What it does |
|---|---|
| **App name** | The product name your people see: page titles, the staff app, the kiosk, PDFs and messages. Up to 60 characters. Empty = your company name. |
| **AI assistant name** | The name of the AI assistant in the menu, if your plan includes it. Empty = *Ask AI*. |
| **Email sender name** | The "from" name on emails {app} sends. Empty = your company name. |

### Look

| Field | What it does |
|---|---|
| **Logo (PNG/JPEG/WebP, max 150 KB)** | Your logo, shown in the panel and on PDFs. |
| **App icon, square (PNG/JPEG/WebP, max 150 KB)** | The small picture in the browser tab and on the phone's home screen when staff install the staff app. Empty = the logo is used. |
| **Theme colour** | The main colour of buttons and links, for example `#1d4ed8`. Pick it or type it. |

## How to change the branding

1. Go to **System → Branding & domain**.
2. Fill in the names you want.
3. To change the logo or icon, drag the image onto the box, or click it and choose the file. The box stays empty when the page opens, even if a logo is already saved; leave it empty to keep the current one.
4. Pick a **Theme colour**.
5. Click **Save branding**. You see *Branding saved*.

Reload the page to see the new look. Staff who installed the staff app on their phone may need to open it again (or reinstall it) to see the new icon.

> **Tip:** Use a logo with a transparent background (PNG) and a square icon of about 512 × 512 pixels. Keep files small: under 150 KB.

## How to use your own domain

Your company always has its normal address. You can add your own address on top of it, for example `attendance.yourcompany.pk`.

1. Under **Custom domain**, type the address (without `https://`) and click **Add domain**.
2. A message shows the CNAME record to create: your address → the target shown on the page.
3. Log in to the company that manages your domain (for example PKNIC, GoDaddy or Cloudflare) and add that CNAME record. Ask your IT person if you are not sure.
4. The support team switches on HTTPS (the padlock) within one working day. After that, /admin and /me work on your own address.

To stop using an address, click **Remove** next to it and confirm.

> **Tip:** Use a sub-address like `attendance.` or `hr.` in front of your domain. Do not use your main website address, or your website will stop working.

## Common problems

### "Use a PNG, JPEG or WebP under 150 KB."

The image is too large or not one of these types. Make it smaller (for example with an online image compressor) or save it as PNG, and upload again.

### "Use a colour like #1d4ed8."

The colour must be a `#` followed by 6 letters or numbers. Use the colour picker to be sure.

### "Branding is managed by your provider."

Your provider has locked the branding. The fields are greyed out and there is no save button. Contact them to change the logo, colours or names.

### "Enter a domain you own, like attendance.yourcompany.pk."

The address is not valid, or it is part of {app}'s own domain. Type only the address, without `https://` or `/admin`, for example `attendance.yourcompany.pk`.

### "This domain is already in use."

Another company already added this address. Check for a typing mistake, or contact support if it is yours.

### My domain shows a security warning or does not open

The CNAME record is missing or wrong, or HTTPS is not switched on yet. Check the record with your domain company and wait up to one working day after adding it. Contact support if it still does not work.

### I can't find Branding & domain

Your plan does not include white label, or you are not the owner or an admin.
