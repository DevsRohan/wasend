'use strict';
const logger = require('../utils/logger');
const engine = require('./whatsappClient');
const { SOCKET_EVENTS } = require('../config/constants');

let timer = null;

function start(io, intervalMs = 25_000) {
  stop();
  timer = setInterval(() => {
    const status = engine.getStatus();
    try {
      io.emit(SOCKET_EVENTS.ENGINE_STATUS, status);
    } catch (e) { logger.warn('heartbeat_emit_fail', { err: e.message }); }
  }, intervalMs);
}

function stop() {
  if (timer) { clearInterval(timer); timer = null; }
}

module.exports = { start, stop };
