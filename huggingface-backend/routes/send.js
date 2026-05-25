'use strict';
const express = require('express');
const engine = require('../services/whatsappClient');
const logger = require('../utils/logger');
const { sendLimiter } = require('../middleware/rateLimit');

const router = express.Router();

router.post('/', sendLimiter, async (req, res, next) => {
  try {
    const { jid, phone, text, source } = req.body || {};
    let target = jid;
    if (!target && phone) target = String(phone).replace(/\D+/g, '') + '@c.us';
    if (!target || !text) {
      return res.status(422).json({ ok: false, error: 'missing_fields' });
    }
    const result = await engine.sendMessage(target, String(text), { source });
    logger.info('send_ok', { jid: target, source: source || 'manual', wa_id: result.wa_message_id });
    res.json({ ok: true, ...result });
  } catch (e) {
    next(e);
  }
});

module.exports = router;
