/**
 * kpi.js - Updates [data-kpi] elements + engine status dot from /api/get_stats.php
 */
(function () {
  'use strict';
  const W = window.WASEND;

  async function refresh() {
    try {
      const r = await W.api('api/get_stats.php');
      const d = r.data || {};
      W.lastStats = d.stats || {};
      paintStats(d.stats || {});
      paintCampaign(d.campaign);
      paintEngine(d.engine);
      paintQueue(d.queue);
    } catch (e) { /* silent */ }
  }

  function paintStats(s) {
    document.querySelectorAll('[data-kpi]').forEach(el => {
      const k = el.getAttribute('data-kpi');
      if (s[k] != null) el.textContent = W.fmt.n(s[k]);
    });
  }

  function paintCampaign(c) {
    if (!c) return;
    const stateEl = document.getElementById('campaign-state');
    if (stateEl) {
      const human = ({
        running: 'Campaign running',
        paused:  'Campaign paused',
        stopped: 'Campaign stopped',
        completed: 'Campaign complete',
        draft: 'Campaign idle',
      })[c.status] || ('Campaign ' + c.status);
      stateEl.textContent = human + ` · ${c.sent_today}/${c.daily_limit} today`;
    }
    const dlEl = document.getElementById('campaign-daily-limit');
    if (dlEl) dlEl.textContent = c.daily_limit;
    const stEl = document.getElementById('campaign-status-text');
    if (stEl) stEl.textContent = c.status;
    const stDot = document.getElementById('campaign-status-dot');
    if (stDot) {
      stDot.className = 'w-2 h-2 rounded-full ' +
        (c.status === 'running' ? 'bg-brand-500 animate-pulse'
        : c.status === 'paused' ? 'bg-amber-400'
        : c.status === 'stopped' ? 'bg-red-500'
        : 'bg-ink-300');
    }
    const prog = document.getElementById('campaign-progress');
    if (prog) {
      const pct = Math.min(100, Math.round(((c.sent_today || 0) / (c.daily_limit || 1)) * 100));
      prog.style.width = pct + '%';
    }
    const range = document.getElementById('campaign-delay-range');
    if (range) range.textContent = `${c.min_delay_seconds}s – ${c.max_delay_seconds}s`;
  }

  function paintEngine(e) {
    if (!e) return;
    const dot   = document.getElementById('engine-dot');
    const pulse = document.getElementById('engine-dot-pulse');
    const text  = document.getElementById('engine-status-text');
    const showQrBtn = document.getElementById('btn-show-qr');
    const pageDot = document.getElementById('engine-page-dot');
    const pageState = document.getElementById('engine-page-state');

    let cls = 'engine-off', label = 'Disconnected', pulseCls = 'bg-red-300';
    const state = (e.state || 'unknown').toLowerCase();
    if (state.includes('ready') || state.includes('connected') || state === 'authenticated') {
      cls = 'engine-on'; label = 'Connected'; pulseCls = 'bg-brand-400';
    } else if (state.includes('qr')) {
      cls = 'engine-warn'; label = 'Awaiting QR'; pulseCls = 'bg-amber-300';
    } else if (state === 'unknown' || state === 'not_configured') {
      cls = 'engine-off'; label = state === 'not_configured' ? 'Not configured' : 'Connecting…'; pulseCls = 'bg-ink-300';
    }
    if (dot)   dot.className = 'relative inline-flex rounded-full h-2.5 w-2.5 ' + cls;
    if (pulse) pulse.className = 'animate-ping absolute inline-flex h-full w-full rounded-full opacity-60 ' + pulseCls;
    if (text)  text.textContent = label;
    if (showQrBtn) {
      showQrBtn.classList.toggle('hidden', !state.includes('qr') && state !== 'not_configured');
    }
    if (pageDot) pageDot.className = 'w-2 h-2 rounded-full ' + cls;
    if (pageState) pageState.textContent = state;
  }

  function paintQueue(q) {
    if (!q) return;
    const set = (id, v) => { const el = document.getElementById(id); if (el) el.textContent = W.fmt.n(v || 0); };
    set('queue-pending', q.pending);
    set('queue-sent',    q.sent);
    set('queue-failed',  q.failed);
    set('queue-blocked', q.blocked);
  }

  W.refreshKpi = refresh;

  // Auto-tick every 15s
  document.addEventListener('DOMContentLoaded', () => {
    refresh();
    setInterval(refresh, 15000);
    if (W.rt) {
      W.rt.on('engine:state',   refresh);
      W.rt.on('campaign:state', refresh);
      W.rt.on('queue:tick',     refresh);
      W.rt.on('msg:in',         refresh);
      W.rt.on('msg:out',        refresh);
      W.rt.on('sync:tick',      refresh);
    }
  });
})();
