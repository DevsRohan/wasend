'use strict';
const express = require('express');
const { bearerAuth } = require('../middleware/auth');

const health  = require('./health');
const status  = require('./status');
const qr      = require('./qr');
const send    = require('./send');
const check   = require('./check');
const session = require('./session');

const router = express.Router();

// Public
router.use('/health', health);

// Authenticated
router.use(bearerAuth);
router.use('/status',         status);
router.use('/qr',             qr);
router.use('/send-message',   send);
router.use('/check-number',   check);
router.use('/session',        session);

module.exports = router;
