'use strict';
/**
 * cronTrigger.js - HF Engine acts as cron driver for PHP.
 *
 * Hostinger shared hosting often has unreliable cron. The engine container is
 * always-on, so we use it as the cron source: every CRON_INTERVAL_MS we POST
 * an HMAC-signed `cron_tick` to the PHP /cron.php endpoint. PHP then runs:
 *   - validate_numbers batch (small)
 *   - one campaign tick
 *   - cleanup of expired tokens
 *
 * This makes the system fully self-driving without external cron.
 */
const axios  = require('axios');
const config = require('../config');
const logger = require('../utils/logger');
const hmac   = require('../utils/hmac');
const { WEBHOOK_EVENTS } = require('../config/constants');

let timer = null;
let busy = false;

async function tick() {
  if (busy) return;
  busy = true;
  try {
    if (!config.cronUrl || !config.webhookSecret) {
      return;
    }
    const body = JSON.stringify({
      type: WEBHOOK_EVENTS.CRON_TICK,
      at:   new Date().toISOString(),
    });
    const eventId = hmac.uuid();
    const sig = 'sha256=' + hmac.sign(body, config.webhookSecret);

    const res = await axios.post(config.cronUrl, body, {
      headers: {
        'Content-Type':        'application/json',
        'X-Webhook-Signature': sig,
        'X-Webhook-Event-Id':  eventId,
        'X-Webhook-Source':    'wasend-engine-cron',
      },
      timeout: 50_000,
      validateStatus: () => true,
    });

    if (res.status < 200 || res.status >= 300) {
      logger.warn('cron_tick_failed', {
        status: res.status,
        body: typeof res.data === 'string' ? res.data.slice(0, 200) : JSON.stringify(res.data || {}).slice(0, 200),
      });
      return;
    }
    if (res.data && res.data.data) {
      const d = res.data.data;
      if (d.validated || d.sent_lead_id || d.cleanup_purged) {
        logger.info('cron_tick_done', d);
      }
    }
  } catch (e) {
    logger.warn('cron_tick_error', { err: e.message });
  } finally {
    busy = false;
  }
}

function start(intervalMs) {
  stop();
  const ms = Math.max(15_000, intervalMs || config.cronIntervalMs || 60_000);
  setTimeout(tick, 8_000);
  timer = setInterval(tick, ms);
  logger.info('cron_trigger_started', { intervalMs: ms, url: config.cronUrl });
}

function stop() {
  if (timer) { clearInterval(timer); timer = null; }
}

module.exports = { start, stop, tick };
