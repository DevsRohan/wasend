'use strict';
/**
 * whatsappClient.js - whatsapp-web.js wrapper with realtime events,
 *                     reconnect handling, and webhook + socket emission.
 */
const path = require('path');
const QRCode = require('qrcode');
const { Client, LocalAuth } = require('whatsapp-web.js');

const config         = require('../config');
const logger         = require('../utils/logger');
const phoneUtil      = require('../utils/phone');
const sessionManager = require('./sessionManager');
const webhook        = require('./webhookDispatcher');
const { ENGINE_STATES, WEBHOOK_EVENTS, SOCKET_EVENTS } = require('../config/constants');

class WhatsAppEngine {
  constructor() {
    this.client     = null;
    this.state      = ENGINE_STATES.BOOT;
    this.qrDataUrl  = null;
    this.qrRaw      = null;
    this.lastReady  = null;
    this.io         = null;     // injected from server.js after socket setup
    this._initializing = false;
    this._restartTimer = null;
  }

  attachIO(io) { this.io = io; }

  emit(event, payload) {
    if (this.io) {
      try { this.io.emit(event, payload); } catch (e) { logger.warn('socket_emit_fail', { event, err: e.message }); }
    }
  }

  setState(state, extra = {}) {
    this.state = state;
    const payload = { state, ...extra, at: new Date().toISOString() };
    logger.info('engine_state', payload);
    this.emit(SOCKET_EVENTS.ENGINE_STATUS, payload);
    webhook.dispatch(WEBHOOK_EVENTS.ENGINE_STATE, payload).catch(() => {});
  }

  async start() {
    if (this._initializing || this.client) {
      logger.info('engine_already_initialized');
      return;
    }
    this._initializing = true;
    sessionManager.ensureDirs();

    logger.info('engine_starting', sessionManager.info());

    try {
      this.client = new Client({
        authStrategy: new LocalAuth({
          clientId: 'wasend-main',
          dataPath: config.sessionDir,
        }),
        puppeteer: {
          headless: config.puppeteer.headless,
          executablePath: config.puppeteer.executablePath,
          args: config.puppeteer.args,
        },
        webVersionCache: { type: 'none' },
        takeoverOnConflict: true,
        takeoverTimeoutMs: 10_000,
      });
      this._wireEvents();
      this.setState(ENGINE_STATES.BOOT);
      await this.client.initialize();
    } catch (e) {
      logger.error('engine_init_failed', { err: e.message, stack: (e.stack || '').split('\n').slice(0, 3) });
      this.setState(ENGINE_STATES.ERROR, { error: e.message });
      this._scheduleRestart(15_000);
    } finally {
      this._initializing = false;
    }
  }

  _wireEvents() {
    const c = this.client;

    c.on('qr', async (qr) => {
      this.qrRaw = qr;
      try {
        this.qrDataUrl = await QRCode.toDataURL(qr, { margin: 1, scale: 6 });
      } catch (e) {
        this.qrDataUrl = null;
      }
      this.setState(ENGINE_STATES.QR);
      this.emit(SOCKET_EVENTS.ENGINE_QR, { qr: this.qrDataUrl, raw: qr, at: new Date().toISOString() });
      logger.info('qr_received');
    });

    c.on('authenticated', () => {
      this.setState(ENGINE_STATES.AUTH);
      logger.info('authenticated');
    });

    c.on('auth_failure', (msg) => {
      logger.error('auth_failure', { msg });
      this.setState(ENGINE_STATES.ERROR, { error: 'auth_failure' });
      this._scheduleRestart(20_000);
    });

    c.on('ready', () => {
      this.qrDataUrl = null;
      this.qrRaw = null;
      this.lastReady = new Date().toISOString();
      this.setState(ENGINE_STATES.READY, { since: this.lastReady });
      logger.info('engine_ready');
    });

    c.on('change_state', (state) => {
      logger.info('client_change_state', { state });
    });

    c.on('disconnected', (reason) => {
      logger.warn('engine_disconnected', { reason });
      this.setState(ENGINE_STATES.DISCO, { reason: String(reason) });
      this._scheduleRestart(8_000);
    });

    c.on('message', (msg) => this._onIncoming(msg).catch((e) => logger.error('on_message_failed', { err: e.message })));

    c.on('message_create', (msg) => {
      // Broadcast outgoing messages we sent (so dashboard shows immediately)
      if (msg.fromMe) {
        this.emit(SOCKET_EVENTS.MSG_OUT, this._serializeMessage(msg));
      }
    });

    c.on('message_ack', (msg, ack) => {
      // ack: 0=clock 1=sent 2=delivered 3=read 4=played
      const ackMap = { 1: 'sent', 2: 'delivered', 3: 'read', 4: 'read' };
      const status = ackMap[ack] || 'sent';
      const payload = {
        wa_message_id: msg.id && msg.id._serialized,
        status,
        at: new Date().toISOString(),
      };
      this.emit(SOCKET_EVENTS.MSG_ACK, payload);
      webhook.dispatch(WEBHOOK_EVENTS.OUTBOUND_ACK, payload).catch(() => {});
    });
  }

  _scheduleRestart(ms = 10_000) {
    if (this._restartTimer) return;
    this._restartTimer = setTimeout(async () => {
      this._restartTimer = null;
      try {
        await this.softRestart();
      } catch (e) {
        logger.error('auto_restart_failed', { err: e.message });
        this._scheduleRestart(ms * 2);
      }
    }, ms);
  }

  async softRestart() {
    logger.warn('soft_restart_initiated');
    try {
      if (this.client) {
        try { await this.client.destroy(); } catch (_) {}
      }
    } catch (_) {}
    this.client = null;
    this._initializing = false;
    await this.start();
  }

  async _onIncoming(msg) {
    if (msg.fromMe) return;
    if (msg.from === 'status@broadcast') return;
    if (msg.isStatus) return;
    // Ignore group messages (only direct chats for outreach use-case)
    if (msg.from && msg.from.endsWith('@g.us')) return;

    const payload = this._serializeMessage(msg);
    this.emit(SOCKET_EVENTS.MSG_IN, payload);
    await webhook.dispatch(WEBHOOK_EVENTS.INBOUND, payload);
  }

  _serializeMessage(msg) {
    return {
      wa_message_id: msg.id && msg.id._serialized,
      from:          msg.from,
      to:            msg.to,
      jid:           msg.fromMe ? msg.to : msg.from,
      from_me:       !!msg.fromMe,
      text:          msg.body || '',
      message_type:  String(msg.type || 'text'),
      timestamp:     msg.timestamp ? new Date(msg.timestamp * 1000).toISOString() : new Date().toISOString(),
    };
  }

  isReady() {
    return this.state === ENGINE_STATES.READY;
  }

  getStatus() {
    return {
      state:    this.state,
      ready:    this.isReady(),
      qr:       this.qrDataUrl,
      lastReady: this.lastReady,
      session:  sessionManager.info(),
    };
  }

  getQr() {
    return { state: this.state, qr: this.qrDataUrl };
  }

  /**
   * Send a text message.
   */
  async sendMessage(jid, text, options = {}) {
    if (!this.isReady()) throw Object.assign(new Error('engine_not_ready'), { status: 503 });
    if (!jid)  throw Object.assign(new Error('jid_required'), { status: 422 });
    if (!text || !text.trim()) throw Object.assign(new Error('text_required'), { status: 422 });

    // Validate jid format (digits@c.us). Accept raw phone too.
    if (!/^\d+@c\.us$/.test(jid)) {
      const norm = phoneUtil.normalize(jid);
      if (!norm.valid) throw Object.assign(new Error('invalid_jid'), { status: 422 });
      jid = norm.jid;
    }

    try {
      const message = await this.client.sendMessage(jid, text);
      const wa_message_id = message && message.id && message.id._serialized;
      return { wa_message_id, jid, status: 'sent' };
    } catch (e) {
      logger.error('send_message_failed', { jid, err: e.message });
      throw Object.assign(new Error('send_failed: ' + e.message), { status: 502 });
    }
  }

  async checkNumber(phoneRaw) {
    if (!this.isReady()) throw Object.assign(new Error('engine_not_ready'), { status: 503 });
    const norm = phoneUtil.normalize(phoneRaw);
    if (!norm.valid) return { on_whatsapp: false, jid: null, phone: phoneRaw };
    try {
      // whatsapp-web.js method: getNumberId
      const result = await this.client.getNumberId(norm.e164);
      if (!result || !result._serialized) {
        return { on_whatsapp: false, jid: null, phone: norm.e164 };
      }
      return { on_whatsapp: true, jid: result._serialized, phone: norm.e164 };
    } catch (e) {
      logger.warn('check_number_failed', { phone: norm.e164, err: e.message });
      throw Object.assign(new Error('check_failed: ' + e.message), { status: 502 });
    }
  }
}

module.exports = new WhatsAppEngine();
