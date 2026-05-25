'use strict';
/**
 * server.js - Wasend Engine entrypoint.
 *
 * Boots:
 *   - Express HTTP API
 *   - Socket.io realtime channel
 *   - WhatsApp engine (whatsapp-web.js + Puppeteer)
 *   - Heartbeat broadcaster
 */
const http = require('http');
const express = require('express');

const config         = require('./config');
const logger         = require('./utils/logger');
const sessionManager = require('./services/sessionManager');
const engine         = require('./services/whatsappClient');
const heartbeat      = require('./services/heartbeat');
const sockets        = require('./sockets');

const { cors }                = require('./middleware/cors');
const { notFound, errorHandler } = require('./middleware/errorHandler');
const routes                  = require('./routes');

// ---------------------------------------------------------------------
// Boot
// ---------------------------------------------------------------------
process.on('unhandledRejection', (e) => logger.error('unhandled_rejection', { err: e && e.message, stack: e && (e.stack || '').split('\n').slice(0, 3) }));
process.on('uncaughtException',  (e) => logger.error('uncaught_exception',  { err: e && e.message, stack: e && (e.stack || '').split('\n').slice(0, 3) }));

const app = express();

app.set('trust proxy', 1);
app.use(cors);
app.options('*', cors);
app.use(express.json({ limit: '2mb' }));
app.use(express.urlencoded({ extended: true, limit: '2mb' }));

// Tiny request log
app.use((req, _res, next) => {
  logger.debug('http_in', { m: req.method, p: req.path });
  next();
});

// Routes
app.use('/', routes);

// Friendly root
app.get('/', (_req, res) => {
  res.json({
    ok: true,
    service: 'wasend-engine',
    docs: '/health',
    state: engine.state,
  });
});

app.use(notFound);
app.use(errorHandler);

const httpServer = http.createServer(app);

// Socket.io
const io = sockets.setup(httpServer);
engine.attachIO(io);

// Boot order: ensure dirs -> start engine -> start http
sessionManager.ensureDirs();

httpServer.listen(config.port, '0.0.0.0', () => {
  logger.info('http_listening', {
    port: config.port,
    configured: config.isConfigured(),
    allowedOrigins: config.allowedOrigins,
    dataDir: config.dataDir,
  });
  if (!config.isConfigured()) {
    logger.warn('engine_started_without_config', {
      hint: 'Set NODE_API_KEY, WEBHOOK_SECRET, WEBHOOK_URL via Space Secrets',
    });
  }

  // Start engine after server listens (so QR push has socket.io)
  engine.start().catch((e) => logger.error('engine_start_failed', { err: e.message }));

  // Heartbeat
  heartbeat.start(io, 25_000);
});

// Graceful shutdown
function shutdown(signal) {
  logger.warn('shutdown_signal', { signal });
  heartbeat.stop();
  try { io.close(); } catch (_) {}
  try {
    if (engine.client) engine.client.destroy().catch(() => {});
  } catch (_) {}
  httpServer.close(() => process.exit(0));
  setTimeout(() => process.exit(0), 5000).unref();
}
['SIGINT', 'SIGTERM'].forEach((s) => process.on(s, () => shutdown(s)));
