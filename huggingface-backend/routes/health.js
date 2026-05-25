'use strict';
const express = require('express');
const engine = require('../services/whatsappClient');

const router = express.Router();

router.get('/', (_req, res) => {
  res.json({
    ok: true,
    name: 'wasend-engine',
    version: '1.0.0',
    uptime_s: Math.floor(process.uptime()),
    engine_state: engine.state,
    now: new Date().toISOString(),
  });
});

module.exports = router;
