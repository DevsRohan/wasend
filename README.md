# Wasend — WhatsApp CRM + Cold Outreach OS

A production-grade WhatsApp CRM + cold outreach platform for local-business outreach.
Premium White + Green dashboard, AI-personalized first-touch messages, anti-ban pacing,
manual-after-reply workflow, and full realtime sync.

> **Architecture:** Hostinger (PHP 8.1+ / MySQL / Dashboard) + Hugging Face Spaces (Node 20 / whatsapp-web.js / Socket.io).

---

## Repository layout

```
wasend/
├── hostinger/                  # PHP dashboard, APIs, webhook receiver  (deploy to Hostinger)
│   ├── config/                 # app.php, db.php, env.php
│   ├── includes/               # repos, helpers, groq, node_client, partials
│   ├── api/                    # ~24 AJAX JSON endpoints
│   ├── scripts/                # cron CLI: campaign, import_csv, validate_numbers, retry, cleanup
│   ├── assets/                 # css, js, img, sounds
│   ├── uploads/                # CSV staging (deny-by-default)
│   ├── logs/                   # File logs (deny-by-default)
│   ├── dashboard.php / leads.php / campaigns.php / settings.php / logs.php
│   ├── login.php / logout.php / index.php
│   └── webhook.php             # Receives signed events from HF engine
│
├── huggingface-backend/        # Node engine (deploy as a Docker Space)
│   ├── server.js               # Express + Socket.io entrypoint
│   ├── Dockerfile              # Node 20 + Chromium
│   ├── package.json
│   ├── config/                 # env loader + constants
│   ├── services/               # whatsappClient, sessionManager, webhookDispatcher, heartbeat
│   ├── routes/                 # /health, /status, /qr, /send-message, /check-number, /session
│   ├── middleware/             # bearer auth, cors, rate-limit, error handler
│   ├── sockets/                # Socket.io setup + handlers
│   ├── utils/                  # logger, retry, delay, phone, hmac
│   └── README.md               # HF Space front-matter (sdk: docker)
│
└── sql/
    ├── schema.sql              # Full DB schema
    ├── seed.sql                # Default admin + settings
    └── migrations/
```

---

## Quick start

### 1) MySQL

In phpMyAdmin (or CLI):

```sql
SOURCE sql/schema.sql;
SOURCE sql/seed.sql;
```

Default admin: `admin / ChangeMe@123` — **change immediately after first login**
(Settings → Security or via the `users` table).

### 2) Hostinger (PHP dashboard)

1. Upload `hostinger/*` to your `public_html/`.
2. Copy `config/.env.example` → `config/.env` and fill DB creds + a strong `APP_KEY`.
3. Open `https://yourdomain.com/login.php`.
4. After login, go to **Settings → Connection** and set:
   - `node_api_url` (your HF Space URL)
   - `node_api_key`, `webhook_secret`, `socket_url`
   - **Settings → AI** → `groq_api_key`.
5. Add cron jobs:
   ```cron
   * * * * *      /usr/bin/php /home/USER/public_html/scripts/campaign.php          >> /home/USER/public_html/logs/campaign.log 2>&1
   */5 * * * *    /usr/bin/php /home/USER/public_html/scripts/validate_numbers.php  >> /home/USER/public_html/logs/campaign.log 2>&1
   */15 * * * *   /usr/bin/php /home/USER/public_html/scripts/retry_failed.php      >> /home/USER/public_html/logs/campaign.log 2>&1
   0 3 * * *      /usr/bin/php /home/USER/public_html/scripts/cleanup.php           >> /home/USER/public_html/logs/campaign.log 2>&1
   ```

### 3) Hugging Face Spaces (Node engine)

1. Create a new **Docker** Space named e.g. `wasend-engine`.
2. Push the contents of `huggingface-backend/` to that Space repo.
3. Add Space **Secrets** (matching the values you put into the PHP settings):
   - `NODE_API_KEY`
   - `WEBHOOK_SECRET`
   - `WEBHOOK_URL` = `https://yourdomain.com/webhook.php`
   - `ALLOWED_ORIGIN` = `https://yourdomain.com`
4. (Recommended) Enable **Persistent Storage** for `/data` so WhatsApp session survives restarts.
5. Open the Space → first boot will surface a QR via `/qr` and via the dashboard.
6. Scan the QR from your WhatsApp → Linked Devices → Link a Device.

---

## How outreach works (anti-ban by design)

```
CSV → Import → Validate WA → Generate AI message → Send 1 first-outreach
                                                            │
                                                            ▼ (random 120-300s pacing)
                                                Send to next valid lead
                                                            │
                                                            ▼ on lead reply
                                                automation BLOCKED on this lead
                                                            │
                                                            ▼
                                              Dashboard switches to manual mode
```

- **Only the first outreach is automated.** No follow-up loops, no chatbot.
- **Random delay 120–300s** between sends; **daily cap** (default 80).
- **Working-hours** window (default 10:00–20:00 IST).
- **Reply detected** → queue for that lead is permanently blocked, dashboard takes over.
- **Personalized per lead** via Groq: language adapts to state (Bihar→Hinglish, Gujarat→Gujarati-mix, Maharashtra→Marathi-mix, etc.), service mention adapts to website status + reviews + rating.

---

## Communication flow

| From            | To              | Channel              | Auth                 |
|-----------------|-----------------|----------------------|----------------------|
| Browser         | PHP             | AJAX `fetch`         | Session cookie + CSRF |
| Browser         | HF Node         | Socket.io (WSS)      | Short-lived token    |
| PHP             | HF Node         | REST (cURL)          | Bearer key           |
| HF Node         | PHP             | Webhook POST         | HMAC-SHA256          |

---

## Tech stack

- **PHP 8.1+** (PDO, OpenSSL, cURL) on Hostinger shared hosting
- **MySQL 5.7+ / MariaDB 10.4+**
- **Tailwind CDN + Vanilla JS + Socket.io client** (no React, no Bootstrap, no jQuery)
- **Node 20 + Express 4 + Socket.io 4 + whatsapp-web.js 1.30** on Hugging Face Spaces (Docker)
- **Groq AI** (`llama-3.3-70b-versatile` primary, `llama-3.1-8b-instant` fallback)

---

## Security

- Bcrypt password hashing for users.
- AES-256-GCM at rest for all DB-stored secrets (Groq key, Node key, webhook secret).
- HMAC-SHA256 signed webhooks (`X-Webhook-Signature: sha256=...`) with replay protection via `X-Webhook-Event-Id`.
- CSRF tokens on every state-changing AJAX call.
- `.htaccess` denies direct access to `config/`, `includes/`, `scripts/`, `uploads/`, `logs/`.
- HF engine: Bearer-key auth on all REST except `/health`, strict CORS allowlist, per-route rate limiting.

---

## License

Proprietary — all rights reserved by the owner.
