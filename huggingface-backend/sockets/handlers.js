'use strict';
const logger = require('../utils/logger');
const engine = require('../services/whatsappClient');
const { SOCKET_EVENTS } = require('../config/constants');

function bind(io, socket) {
  logger.info('socket_connected', { id: socket.id });

  // Send current state immediately
  try {
    socket.emit(SOCKET_EVENTS.ENGINE_STATUS, engine.getStatus());
    const qr = engine.getQr();
    if (qr && qr.qr) socket.emit(SOCKET_EVENTS.ENGINE_QR, { qr: qr.qr });
  } catch (e) {
    logger.warn('initial_emit_failed', { err: e.message });
  }

  socket.on('subscribe', (room) => {
    if (typeof room === 'string' && room.length < 64) {
      socket.join(room);
    }
  });

  socket.on('ping_check', () => {
    socket.emit('pong_check', { at: Date.now() });
  });

  socket.on('disconnect', (reason) => {
    logger.info('socket_disconnected', { id: socket.id, reason });
  });
}

module.exports = { bind };
