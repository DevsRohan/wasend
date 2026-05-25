'use strict';
const express = require('express');
const engine = require('../services/whatsappClient');

const router = express.Router();

router.get('/', (_req, res) => {
  res.json({ ok: true, ...engine.getStatus() });
});

module.exports = router;
