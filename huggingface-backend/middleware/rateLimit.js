'use strict';
const rateLimit = require('express-rate-limit');
const config = require('../config');

const sendLimiter = rateLimit({
  windowMs: 60 * 1000,
  max: config.rateLimit.sendPerMin,
  standardHeaders: true,
  legacyHeaders: false,
  message: { ok: false, error: 'rate_limited' },
});

const checkLimiter = rateLimit({
  windowMs: 60 * 1000,
  max: config.rateLimit.checkPerMin,
  standardHeaders: true,
  legacyHeaders: false,
  message: { ok: false, error: 'rate_limited' },
});

module.exports = { sendLimiter, checkLimiter };
