'use strict';
const logger = require('../utils/logger');

function notFound(req, res, _next) {
  res.status(404).json({ ok: false, error: 'not_found', path: req.path });
}

function errorHandler(err, _req, res, _next) {
  logger.error('http_error', { msg: err && err.message, stack: err && err.stack && err.stack.split('\n')[0] });
  if (res.headersSent) return;
  const status = err && err.status ? err.status : 500;
  res.status(status).json({ ok: false, error: (err && err.message) || 'internal_error' });
}

module.exports = { notFound, errorHandler };
