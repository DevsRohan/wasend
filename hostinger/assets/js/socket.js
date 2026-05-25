/**
 * socket.js - Socket.io client connection management with auth + reconnect
 */
(function () {
  'use strict';
  const W = window.WASEND;
  W.socket = null;
  W.socketReady = false;
  const listeners = {};

  async function getToken() {
    try {
      const r = await W.api('api/socket_token.php');
      return r.data;
    } catch (e) {
      console.warn('socket_token failed', e);
      return null;
    }
  }

  async function connect() {
    if (typeof io === 'undefined') {
      console.warn('socket.io client not loaded');
      return;
    }
    const tok = await getToken();
    if (!tok || !tok.socket_url) {
      console.warn('socket url not configured');
      return;
    }
    if (W.socket) {
      try { W.socket.disconnect(); } catch (_) {}
    }
    const s = io(tok.socket_url, {
      transports: ['websocket', 'polling'],
      reconnection: true,
      reconnectionDelay: 1500,
      reconnectionDelayMax: 8000,
      reconnectionAttempts: Infinity,
      auth: { token: tok.token },
      query: { token: tok.token },
      withCredentials: false,
      timeout: 12000,
    });
    W.socket = s;

    s.on('connect', () => {
      W.socketReady = true;
      emit('socket:open');
    });
    s.on('disconnect', () => {
      W.socketReady = false;
      emit('socket:close');
    });
    s.on('connect_error', (err) => {
      console.warn('socket connect_error', err && err.message);
      emit('socket:error', err);
    });

    // Forward all engine/message events to listeners
    [
      'engine:status', 'engine:qr',
      'message:inbound', 'message:outbound', 'message:ack',
      'lead:validated', 'campaign:state', 'queue:tick'
    ].forEach(evt => s.on(evt, (data) => emit(evt, data)));

    return s;
  }

  function on(evt, fn) {
    listeners[evt] = listeners[evt] || [];
    listeners[evt].push(fn);
  }
  function emit(evt, payload) {
    (listeners[evt] || []).forEach(fn => { try { fn(payload); } catch (e) { console.error(e); } });
  }

  // Page visibility: refresh token+socket when becoming visible after long sleep
  document.addEventListener('visibilitychange', () => {
    if (!document.hidden && W.socket && !W.socket.connected) {
      connect();
    }
  });

  W.socketConnect = connect;
  W.socketOn = on;
})();
