'use strict';
const express = require('express');
const engine = require('../services/whatsappClient');
const logger = require('../utils/logger');

const router = express.Router();

router.post('/restart', async (_req, res, next) => {
  try {
    logger.warn('session_restart_requested');
    // run async; respond immediately so caller doesn't block
    engine.softRestart().catch((e) => logger.error('restart_async_failed', { err: e.message }));
    res.json({ ok: true, restarted: true });
  } catch (e) {
    next(e);
  }
});

module.exports = router;
