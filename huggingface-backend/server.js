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

// ---------------------------------------------------------------------
// Public endpoints (registered BEFORE the auth-protected router)
// so the Space root URL never returns "unauthorized".
// ---------------------------------------------------------------------
app.get('/', (_req, res) => {
  const s = engine.getStatus();
  const stateColor = s.ready ? '#10B981' : (String(s.state).includes('qr') ? '#F59E0B' : '#EF4444');
  res.set('Content-Type', 'text/html; charset=utf-8').send(`<!DOCTYPE html>
<html lang="en"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Wasend Engine</title>
<style>
  *{box-sizing:border-box;margin:0;padding:0}
  body{font-family:Inter,system-ui,-apple-system,sans-serif;background:linear-gradient(135deg,#fff 0%,#ECFDF5 100%);color:#0F172A;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px}
  .card{max-width:560px;width:100%;background:#fff;border:1px solid #E5E7EB;border-radius:16px;padding:32px;box-shadow:0 12px 30px -10px rgba(15,23,42,.12)}
  h1{font-size:22px;font-weight:600;letter-spacing:-.3px;margin-bottom:4px;display:flex;align-items:center;gap:10px}
  .badge{display:inline-flex;align-items:center;gap:6px;padding:3px 10px;border-radius:999px;background:#ECFDF5;color:#047857;border:1px solid #BBF7D0;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.5px}
  .dot{width:8px;height:8px;border-radius:999px;background:${stateColor};box-shadow:0 0 0 4px ${stateColor}22}
  p{color:#64748B;font-size:13px;line-height:1.6;margin:8px 0}
  .row{display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-bottom:1px solid #F1F5F9;font-size:13px}
  .row:last-child{border-bottom:none}
  .row span:first-child{color:#64748B}
  .row span:last-child{font-weight:500;font-family:ui-monospace,monospace;font-size:12px}
  code{background:#F1F5F9;padding:2px 6px;border-radius:4px;font-size:11.5px}
  .endpoints{margin-top:20px;padding-top:20px;border-top:1px solid #E5E7EB}
  .ep{display:flex;justify-content:space-between;padding:6px 0;font-size:12px;font-family:ui-monospace,monospace;color:#334155}
  .ep .m{color:#10B981;font-weight:600;width:55px}
  .ep .a{color:#94A3B8;font-size:10.5px}
  .footer{margin-top:24px;text-align:center;font-size:11px;color:#94A3B8}
</style></head>
<body>
<div class="card">
  <h1>
    <span style="display:inline-flex;width:32px;height:32px;border-radius:8px;background:#10B981;color:#fff;align-items:center;justify-content:center">
      <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7l9 6 9-6M3 17l9-6 9 6"/></svg>
    </span>
    Wasend Engine
  </h1>
  <p>WhatsApp engine for the Wasend CRM. Use Bearer-key auth for API endpoints from your dashboard.</p>

  <div style="margin-top:18px">
    <span class="badge"><span class="dot"></span> ${s.state}</span>
  </div>

  <div style="margin-top:18px">
    <div class="row"><span>State</span><span>${s.state}</span></div>
    <div class="row"><span>Ready</span><span>${s.ready ? 'yes' : 'no'}</span></div>
    <div class="row"><span>Configured</span><span>${config.isConfigured() ? 'yes' : 'no'}</span></div>
    <div class="row"><span>Allowed origin</span><span>${(config.allowedOrigins || []).join(', ') || 'any'}</span></div>
    <div class="row"><span>Persistent</span><span>${s.session && s.session.writable ? 'yes' : 'no (re-scan after restart)'}</span></div>
    <div class="row"><span>Last ready</span><span>${s.lastReady || '—'}</span></div>
  </div>

  <div class="endpoints">
    <div style="font-size:11px;font-weight:600;color:#64748B;text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px">Endpoints</div>
    <div class="ep"><span class="m">GET</span><span>/health</span><span class="a">public</span></div>
    <div class="ep"><span class="m">GET</span><span>/info</span><span class="a">public</span></div>
    <div class="ep"><span class="m">GET</span><span>/status</span><span class="a">bearer</span></div>
    <div class="ep"><span class="m">GET</span><span>/qr</span><span class="a">bearer</span></div>
    <div class="ep"><span class="m">POST</span><span>/send-message</span><span class="a">bearer</span></div>
    <div class="ep"><span class="m">POST</span><span>/check-number</span><span class="a">bearer</span></div>
    <div class="ep"><span class="m">POST</span><span>/session/restart</span><span class="a">bearer</span></div>
  </div>

  <p style="margin-top:18px">Send <code>Authorization: Bearer &lt;NODE_API_KEY&gt;</code> for protected endpoints.</p>

  <div class="footer">Wasend Engine v1.0 · Node ${process.version} · auto-refresh in 15s</div>
</div>
<script>setTimeout(()=>location.reload(),15000)</script>
</body></html>`);
});

app.get('/info', (_req, res) => {
  const s = engine.getStatus();
  res.json({
    ok: true,
    service: 'wasend-engine',
    version: '1.0.0',
    state: s.state,
    ready: s.ready,
    configured: config.isConfigured(),
    persistent: !!(s.session && s.session.writable),
    last_ready: s.lastReady,
    allowed_origins: config.allowedOrigins,
    now: new Date().toISOString(),
  });
});

// Routes (mostly bearer-auth protected)
app.use('/', routes);

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
