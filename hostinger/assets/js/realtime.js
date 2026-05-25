/**
 * realtime.js - Receives socket events and dispatches them to UI modules.
 *               Also polls /api/refresh_sync.php as fallback every 30s.
 */
(function () {
  'use strict';
  const W = window.WASEND;
  const bus = {};

  function on(evt, fn) {
    bus[evt] = bus[evt] || [];
    bus[evt].push(fn);
  }
  function dispatch(evt, payload) {
    (bus[evt] || []).forEach(fn => { try { fn(payload); } catch (e) { console.error(e); } });
  }
  W.rt = { on, dispatch };

  function bindSocket() {
    if (!W.socketOn) return;
    W.socketOn('socket:open',  () => dispatch('engine:state', { state: 'socket_connected' }));
    W.socketOn('socket:close', () => dispatch('engine:state', { state: 'socket_disconnected' }));
    W.socketOn('engine:status', (d) => dispatch('engine:state', d));
    W.socketOn('engine:qr',     (d) => dispatch('engine:qr', d));
    W.socketOn('message:inbound',  (d) => dispatch('msg:in', d));
    W.socketOn('message:outbound', (d) => dispatch('msg:out', d));
    W.socketOn('message:ack',      (d) => dispatch('msg:ack', d));
    W.socketOn('lead:validated',   (d) => dispatch('lead:validated', d));
    W.socketOn('campaign:state',   (d) => dispatch('campaign:state', d));
    W.socketOn('queue:tick',       (d) => dispatch('queue:tick', d));
  }

  // Polling fallback
  let pollTimer = null;
  function startPoll() {
    stopPoll();
    pollTimer = setInterval(async () => {
      try {
        const r = await W.api('api/refresh_sync.php');
        dispatch('sync:tick', r.data);
      } catch (e) { /* ignore */ }
    }, 30000);
  }
  function stopPoll() { if (pollTimer) { clearInterval(pollTimer); pollTimer = null; } }

  W.startRealtime = function () {
    bindSocket();
    if (W.socketConnect) W.socketConnect();
    startPoll();
  };
})();
