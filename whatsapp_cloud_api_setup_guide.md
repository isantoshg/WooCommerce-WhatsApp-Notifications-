# WhatsApp Cloud API — Setup Guide for WP WhatsApp Notify

**Purpose:** Step-by-step guide to create and configure a Meta (Facebook) WhatsApp Cloud API app, get credentials, and integrate them into the **WP WhatsApp Notify** plugin. Copy this file into your plugin's `/docs/` folder or show it inside the plugin settings page.

---

## Quick overview (one-line)

1. Create a Meta Developer App (Business app) → 2. Add WhatsApp product → 3. Get Phone Number ID & Access Token → 4. Paste into plugin settings → 5. Test send.

---

## Table of contents

- Prerequisites
- Step 1 — Create Meta Developer App
- Step 2 — Add WhatsApp (Cloud API)
- Step 3 — Create / Link a Meta Business Account
- Step 4 — Collect Phone Number ID & WhatsApp Business Account ID
- Step 5 — Generate a Long-lived Access Token
- Step 6 — (Optional) Configure Webhooks
- Step 7 — Configure plugin settings (WP WhatsApp Notify)
- Step 8 — Test sending a message
- Troubleshooting & Tips
- HTML snippet (paste into plugin Settings page)

---

## Prerequisites

- A Facebook account with permission to create developer apps.
- A verified business (recommended) to go to production (Green Tick optional).
- A site with HTTPS (required for webhooks).
- WordPress + WooCommerce + the `WP WhatsApp Notify` plugin installed.

---

## Step 1 — Create Meta Developer App

1. Open: [https://developers.facebook.com/](https://developers.facebook.com/) and **log in** with your Facebook account.
2. Top-right — click **My Apps** → **Create App**.
3. Choose **Business** as the app type and click **Next**.
4. Enter an app name (e.g. `WP WhatsApp Notify`) and your business email. Click **Create App**.

---

## Step 2 — Add WhatsApp (Cloud API)

1. On your new app's dashboard, click **Add Product** in the left sidebar.
2. Find **WhatsApp** and click **Set Up**.
3. You will see a **Getting Started** page — this contains a temporary/test phone and example requests.

---

## Step 3 — Create / Link a Meta Business Account

1. In the WhatsApp setup flow you'll be asked to **link or create** a Meta Business Account (MBA). Follow prompts to create one if you don’t have.
2. Fill business details (name, email, address). The Business Account is needed to create System Users and generate production tokens.

---

## Step 4 — Collect Phone Number ID & WhatsApp Business Account ID

1. On the WhatsApp product page you’ll find a **Phone Number ID** (looks like a numeric id) and a **WhatsApp Business Account ID**.
2. Note both values — plugin settings require the **Phone Number ID** and optionally the Business Account ID for advanced features.

---

## Step 5 — Generate a Long-lived Access Token (recommended)

> Short-lived test tokens expire quickly. For production use a long-lived token.

**Generate token (simple method):**

1. In the app dashboard under **WhatsApp > Getting Started**, you’ll see a **Temporary Access Token** — use it only for quick tests.
2. For production: go to **Business Settings** → **System Users** (Meta Business Manager) and create a System User.
3. Assign your app to that system user and generate a token with these permissions: `whatsapp_business_messaging`, `whatsapp_business_management`.
4. Copy the generated token and store securely (do not publish). Use this token inside plugin settings.

---

## Step 6 — (Optional) Configure Webhooks — to receive incoming messages or delivery events

1. In your app's **WhatsApp** settings find **Webhooks**.
2. Click **Add Callback URL** and enter your plugin webhook endpoint (must be HTTPS).
   - Example: `https://your-site.com/wp-json/wwn/v1/webhook` (if your plugin exposes a REST endpoint)
3. Enter a Verify Token (any string) and save it. In your plugin webhook handler, respond to the verification handshake with the same token.
4. Subscribe to events: `messages` and `message_deliveries` (as needed).

---

## Step 7 — Configure plugin settings (WP WhatsApp Notify)

1. In WordPress admin go to **WhatsApp Notify** → Settings.
2. Fill these fields:
   - **App ID**: App ID from developers dashboard (optional for simple sends but recommended to store)
   - **Phone Number ID**: the ID you copied
   - **Access Token**: the long-lived access token you generated
   - **Admin Phone**: your admin phone number (with country code, no plus — e.g., `919876543210`)
   - **Brand Name**: (optional) `Ratinia` — this is prepended to messages until you have verified business name
   - **Abandoned Cart Delay**: minutes to wait before sending cart reminder
   - **Enable Logs**: ON while testing (requires `WP_DEBUG` enabled)
3. Save settings.

---

## Step 8 — Test sending a message

### 1) Quick test using the plugin (recommended)

- If plugin has a “Test send” button, use it to send a sample message to your own number.

### 2) Manual test via cURL (optional)

Replace `PHONE_NUMBER_ID` and `ACCESS_TOKEN` below:

```bash
curl -i -X POST \
  "https://graph.facebook.com/v19.0/PHONE_NUMBER_ID/messages" \
  -H 'Authorization: Bearer ACCESS_TOKEN' \
  -H 'Content-Type: application/json' \
  -d '{
    "messaging_product": "whatsapp",
    "to": "+919876543210",
    "type": "text",
    "text": {"body": "Hello from WP WhatsApp Notify!"}
  }'
```

If successful you will receive a JSON response with `messages` and `messages[0].id`.

---

## Troubleshooting & common errors

- **401 Unauthorized**: Access token invalid or expired. Regenerate a new token.
- **Invalid recipient**: Phone number not in correct format (use full E.164, e.g., `+919876543210`).
- **Permission error**: Ensure token has `whatsapp_business_messaging` permission.
- **Webhook verification fails**: Ensure your webhook responds with the verify token and your URL is HTTPS.

---

## Security & best practices

- Use a **long-lived token** for production. Keep it secret.
- Turn on `Enable Logs` only for debugging and on staging sites. Disable on production.
- Ask users for phone numbers with country code at checkout to ensure message deliverability.
- Respect WhatsApp template and policy rules: for template messages (notifications) you may need template approval.

---

## HTML snippet — Embed this on your plugin Settings page (paste inside settings render method)

```html
<div class="wwn-card">
  <h2>WhatsApp Cloud API — Quick Setup</h2>
  <ol>
    <li>Go to <a href="https://developers.facebook.com/" target="_blank">developers.facebook.com</a> and create a Business app.</li>
    <li>Add the WhatsApp product and get your <strong>Phone Number ID</strong>.</li>
    <li>Create a System User and generate a long-lived access token with <code>whatsapp_business_messaging</code> permission.</li>
    <li>Paste Phone Number ID + Access Token into this page and Save.</li>
    <li>Test send using the plugin test button or by placing a test order.</li>
  </ol>
  <p class="wwn-notice">Note: Webhook requires HTTPS. For production use your own verified phone number and long-lived tokens.</p>
</div>
```

---

## Want a PDF or in-plugin interactive guide?

If you want, I can convert this markdown into a downloadable PDF or produce an interactive setup checklist to embed in the plugin (with step-complete toggles). Tell me which format you prefer and I’ll generate it.

---

**End of guide** — paste this file to `docs/setup-guide.md` inside your plugin or use the HTML snippet in the settings page to make it visible to your users.

