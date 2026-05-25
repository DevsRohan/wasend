'use strict';
const express = require('express');
const engine = require('../services/whatsappClient');

const router = express.Router();

router.get('/', (_req, res) => {
  const q = engine.getQr();
  res.json({ ok: true, ...q });
});

module.exports = router;
