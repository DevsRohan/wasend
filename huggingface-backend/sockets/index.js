'use strict';
const { Server } = require('socket.io');
const config = require('../config');
const logger = require('../utils/logger');
const handlers = require('./handlers');

function setup(httpServer) {
  const io = new Server(httpServer, {
    cors: {
      origin(origin, cb) {
        if (!origin) return cb(null, true);
        if (config.allowedOrigins.includes('*')) return cb(null, true);
        if (config.allowedOrigins.includes(origin)) return cb(null, true);
        return cb(new Error('cors_blocked: ' + origin));
      },
      methods: ['GET', 'POST'],
      credentials: false,
    },
    transports: ['websocket', 'polling'],
    pingTimeout: 25_000,
    pingInterval: 20_000,
  });

  // Lightweight auth: token query/auth field. Tokens are issued by PHP and
  // expire in 5 minutes. PHP-side validation of the token's existence is
  // optional in the engine; we accept any non-empty token as a soft auth so
  // the engine remains stateless. Real security boundary is the bearer key
  // for REST and the HMAC for webhooks.
  io.use((socket, next) => {
    const tok = (socket.handshake.auth && socket.handshake.auth.token) ||
                (socket.handshake.query && socket.handshake.query.token);
    if (!tok) return next(new Error('unauthorized'));
    socket.data.token = String(tok);
    next();
  });

  io.on('connection', (sock) => handlers.bind(io, sock));
  logger.info('socket_io_ready');
  return io;
}

module.exports = { setup };
