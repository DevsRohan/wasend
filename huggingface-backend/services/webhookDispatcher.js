'use strict';
const axios = require('axios');
const config = require('../config');
const logger = require('../utils/logger');
const retry  = require('../utils/retry');
const hmac   = require('../utils/hmac');

/**
 * Dispatch webhook to Hostinger PHP with HMAC signature + retry + dedup.
 */
async function dispatch(eventType, payload) {
  if (!config.webhookUrl || !config.webhookSecret) {
    logger.warn('webhook_skipped_not_configured', { eventType });
    return false;
  }
  const body = JSON.stringify({ ...payload, type: eventType });
  const eventId = hmac.uuid();
  const sig = 'sha256=' + hmac.sign(body, config.webhookSecret);

  const headers = {
    'Content-Type':         'application/json',
    'X-Webhook-Signature':  sig,
    'X-Webhook-Event-Id':   eventId,
    'X-Webhook-Source':     'wasend-engine',
    'User-Agent':           'wasend-engine/1.0',
  };

  try {
    await retry(async (attempt) => {
      const res = await axios.post(config.webhookUrl, body, {
        headers, timeout: config.webhook.timeoutMs, validateStatus: () => true,
      });
      if (res.status >= 200 && res.status < 300) return res;
      const err = new Error(`webhook_http_${res.status}`);
      err.attempt = attempt;
      err.body = typeof res.data === 'string' ? res.data.slice(0, 200) : JSON.stringify(res.data || {}).slice(0, 200);
      throw err;
    }, {
      max: config.webhook.retryMax,
      baseMs: config.webhook.retryBaseMs,
      onError: (e, attempt) => {
        logger.warn('webhook_attempt_failed', { eventType, attempt, err: e.message, body: e.body });
      },
    });
    logger.info('webhook_dispatched', { eventType, eventId });
    return true;
  } catch (e) {
    logger.error('webhook_failed', { eventType, eventId, err: e.message });
    return false;
  }
}

module.exports = { dispatch };
