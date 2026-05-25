---
title: Wasend Engine
emoji: 🟢
colorFrom: green
colorTo: emerald
sdk: docker
app_port: 7860
pinned: false
---

# Wasend Engine

Production WhatsApp engine for the **Wasend CRM + Cold Outreach OS**.

This Hugging Face Space runs a Node.js + Express + Socket.io server with `whatsapp-web.js`
(Puppeteer + headless Chromium) and exchanges signed webhooks with the PHP dashboard
hosted on Hostinger.

---

## Endpoints

- `GET  /health`           — liveness check
- `GET  /status`           — engine state (`ready` / `qr_required` / `disconnected`)
- `GET  /qr`               — current QR (base64 data URL) when login required
- `POST /send-message`     — send a WhatsApp text message
- `POST /check-number`     — check if a phone number is on WhatsApp
- `POST /session/restart`  — soft restart the WhatsApp client

All non-`/health` endpoints require `Authorization: Bearer ${NODE_API_KEY}`.

## Webhooks (engine → PHP)

The engine signs every outbound webhook with HMAC-SHA256:

```
X-Webhook-Signature: sha256=<hex>
X-Webhook-Event-Id:  <uuid>
Content-Type:        application/json
```

Event types: `message_inbound`, `message_outbound_ack`, `engine_state`, `lead_validated`.

## Socket.io events (engine → browser)

`engine:status`, `engine:qr`, `message:inbound`, `message:outbound`, `message:ack`,
`lead:validated`, `campaign:state`, `queue:tick`.

## Persistence

Session files are stored under `${DATA_DIR}/wa_session` (default `/data/wa_session`).
On Hugging Face Spaces enable **Persistent Storage** for `/data` so WhatsApp login
survives container restarts.

If persistent storage is not available, the engine still works but the user will
need to re-scan the QR after each cold start.

## Required environment

See `.env.example`. Set these as **Space Secrets** in Hugging Face UI:

- `NODE_API_KEY`
- `WEBHOOK_SECRET`
- `WEBHOOK_URL`
- `ALLOWED_ORIGIN` (or `ALLOWED_ORIGINS` for multiple)

## Local dev

```bash
npm install
cp .env.example .env       # fill values
node server.js
```
