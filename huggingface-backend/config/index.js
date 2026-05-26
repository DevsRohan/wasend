'use strict';
/**
 * config/index.js - Centralized environment loader.
 *
 * Auto-detects writable data directory at startup. Prefers $DATA_DIR (default
 * /data, which is the Hugging Face Spaces persistent storage mount). If that
 * path is not writable (e.g. persistent storage not enabled on the Space),
 * gracefully falls back to ./data inside the app folder so the engine still
 * boots — the user simply has to re-scan the QR after each cold start.
 */
const path = require('path');
const fs = require('fs');

// ---------------------------------------------------------------------
// Lightweight .env loader (no dotenv dependency).
// ---------------------------------------------------------------------
try {
  const p = path.join(__dirname, '..', '.env');
  if (fs.existsSync(p)) {
    fs.readFileSync(p, 'utf8').split(/\r?\n/).forEach((line) => {
      const m = line.match(/^\s*([A-Z0-9_]+)\s*=\s*(.*)\s*$/i);
      if (m && !process.env[m[1]]) {
        let v = m[2];
        if ((v.startsWith('"') && v.endsWith('"')) || (v.startsWith("'") && v.endsWith("'"))) {
          v = v.slice(1, -1);
        }
        process.env[m[1]] = v;
      }
    });
  }
} catch (_) { /* ignore */ }

const env = process.env;

const allowedOrigins = (env.ALLOWED_ORIGINS || env.ALLOWED_ORIGIN || '*')
  .split(',')
  .map((s) => s.trim())
  .filter(Boolean);

// ---------------------------------------------------------------------
// Data directory resolution with writable-fallback.
// ---------------------------------------------------------------------
function isWritable(dir) {
  try {
    if (!fs.existsSync(dir)) fs.mkdirSync(dir, { recursive: true });
    const probe = path.join(dir, '.write_probe_' + Date.now());
    fs.writeFileSync(probe, 'ok');
    fs.unlinkSync(probe);
    return true;
  } catch (_) {
    return false;
  }
}

function resolveDataDir() {
  const preferred = env.DATA_DIR || '/data';
  if (isWritable(preferred)) return preferred;
  const fallback = path.join(__dirname, '..', 'data');
  try { fs.mkdirSync(fallback, { recursive: true }); } catch (_) {}
  return fallback;
}

const dataDir = resolveDataDir();

const config = {
  port: parseInt(env.PORT || '7860', 10),

  apiKey:        env.NODE_API_KEY        || '',
  webhookSecret: env.WEBHOOK_SECRET      || '',
  webhookUrl:    env.WEBHOOK_URL         || '',
  cronUrl:       env.CRON_URL            ||
                 (env.WEBHOOK_URL ? env.WEBHOOK_URL.replace(/\/webhook\.php$/, '/cron.php') : ''),
  cronIntervalMs: parseInt(env.CRON_INTERVAL_MS || '60000', 10),

  // WhatsApp Web version pinning - prevents "No LID for user" by locking the
  // engine to a known-stable WA Web HTML. Override via WA_WEB_VERSION env if
  // a future WA forces a different version.
  waWebVersion:     env.WA_WEB_VERSION      || '2.2412.54',
  waWebVersionHtml: env.WA_WEB_VERSION_HTML || '',
  allowedOrigins,

  dataDir,
  sessionDir:    path.join(dataDir, 'wa_session'),
  logsDir:       path.join(dataDir, 'logs'),

  puppeteer: {
    headless: (env.PUPPETEER_HEADLESS || 'true') !== 'false',
    executablePath:
      env.PUPPETEER_EXECUTABLE_PATH ||
      env.CHROMIUM_PATH ||
      '/usr/bin/chromium',
    args: [
      '--no-sandbox',
      '--disable-setuid-sandbox',
      '--disable-dev-shm-usage',
      '--disable-accelerated-2d-canvas',
      '--no-first-run',
      '--no-zygote',
      '--disable-gpu',
      '--disable-extensions',
      '--disable-software-rasterizer',
    ],
  },

  webhook: {
    retryMax:    parseInt(env.WEBHOOK_RETRY_MAX || '5', 10),
    retryBaseMs: parseInt(env.WEBHOOK_RETRY_BASE_MS || '2000', 10),
    timeoutMs:   parseInt(env.WEBHOOK_TIMEOUT_MS || '15000', 10),
  },

  rateLimit: {
    sendPerMin:  parseInt(env.RATE_SEND_PER_MIN || '30', 10),
    checkPerMin: parseInt(env.RATE_CHECK_PER_MIN || '60', 10),
  },

  session: {
    backupIntervalMin: parseInt(env.SESSION_BACKUP_INTERVAL_MIN || '30', 10),
  },

  isConfigured() {
    return !!(this.apiKey && this.webhookSecret && this.webhookUrl);
  },
};

module.exports = config;
