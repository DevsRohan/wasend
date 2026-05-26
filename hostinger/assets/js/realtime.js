/**
 * realtime.js - Receives socket events and dispatches them to UI modules.
 *
 * Belt-and-suspenders sync model:
 *   1. Socket.io for sub-second event delivery (preferred)
 *   2. Polling fallback every 8s when socket disconnected
 *   3. Light polling every 25s even when socket is connected (catches the
 *      rare case where the engine emits an event but the socket frame is
 *      lost in transit - HF/CF can occasionally drop frames)
 *   4. Force-refresh on tab focus / visibility change so coming back from
 *      another tab gives instant freshness
 *   5. Manual "Sync Now" button (#btn-sync-now) for last-resort
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

  let pollTimer = null;
  let lightTimer = null;
  let lastSyncAt = 0;

  async function syncNow(reason) {
    if (Date.now() - lastSyncAt < 1500) return; // throttle bursts
    lastSyncAt = Date.now();
    try {
      const r = await W.api('api/refresh_sync.php');
      dispatch('sync:tick', r.data);
    } catch (e) { /* ignore */ }
  }
  W.syncNow = syncNow;

  function startPoll() {
    stopPoll();
    // Aggressive poll while socket is down - catches missed events fast.
    pollTimer = setInterval(() => {
      if (!W.socketReady) syncNow('poll_fast');
    }, 8000);
    // Light poll even while socket is connected. Catches the rare case
    // where the socket frame is lost or a webhook arrived too late for
    // the browser to know about.
    lightTimer = setInterval(() => syncNow('poll_light'), 25_000);
  }
  function stopPoll() {
    if (pollTimer)  { clearInterval(pollTimer);  pollTimer = null; }
    if (lightTimer) { clearInterval(lightTimer); lightTimer = null; }
  }

  // Refresh immediately when tab becomes visible / window gains focus
  document.addEventListener('visibilitychange', () => {
    if (!document.hidden) syncNow('visibility');
  });
  window.addEventListener('focus', () => syncNow('focus'));
  window.addEventListener('online', () => syncNow('online'));

  // Manual "Sync Now" button on dashboard
  document.addEventListener('DOMContentLoaded', () => {
    const btn = document.getElementById('btn-sync-now');
    if (btn) btn.addEventListener('click', () => {
      btn.disabled = true; const old = btn.textContent; btn.textContent = '⟳';
      syncNow('manual').finally(() => { btn.disabled = false; btn.textContent = old; });
    });
  });

  W.startRealtime = function () {
    bindSocket();
    if (W.socketConnect) W.socketConnect();
    startPoll();
    // Initial sync after small delay (let socket connect first)
    setTimeout(() => syncNow('boot'), 2500);
  };
})();
