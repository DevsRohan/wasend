'use strict';
const config = require('../config');

function bearerAuth(req, res, next) {
  // Allow /health and /qr without auth so HF Spaces' health check works.
  if (req.path === '/health') return next();

  const h = req.get('authorization') || '';
  const m = h.match(/^Bearer\s+(.+)$/i);
  const token = m ? m[1] : '';

  if (!config.apiKey) {
    return res.status(503).json({ ok: false, error: 'engine_not_configured' });
  }
  if (!token || token !== config.apiKey) {
    return res.status(401).json({ ok: false, error: 'unauthorized' });
  }
  next();
}

module.exports = { bearerAuth };
