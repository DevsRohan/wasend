'use strict';
const express = require('express');
const engine = require('../services/whatsappClient');
const webhook = require('../services/webhookDispatcher');
const { WEBHOOK_EVENTS, SOCKET_EVENTS } = require('../config/constants');
const { checkLimiter } = require('../middleware/rateLimit');

const router = express.Router();

router.post('/', checkLimiter, async (req, res, next) => {
  try {
    const phone = (req.body && req.body.phone) ? String(req.body.phone) : '';
    if (!phone) return res.status(422).json({ ok: false, error: 'phone_required' });

    const result = await engine.checkNumber(phone);

    // Notify dashboard + PHP
    try {
      engine.emit && engine.emit(SOCKET_EVENTS.LEAD_VALID, result);
      webhook.dispatch(WEBHOOK_EVENTS.LEAD_VALIDATED, result).catch(() => {});
    } catch (_) {}

    res.json({ ok: true, ...result });
  } catch (e) {
    next(e);
  }
});

module.exports = router;
