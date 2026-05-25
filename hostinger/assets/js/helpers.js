/**
 * helpers.js - small utilities used across modules
 */
(function () {
  'use strict';

  const W = window.WASEND = window.WASEND || {};

  W.api = async function api(path, opts = {}) {
    const url = path.startsWith('http') ? path : path;
    const headers = Object.assign(
      { 'Accept': 'application/json' },
      opts.headers || {}
    );
    if (W.csrf) headers['X-CSRF-Token'] = W.csrf;
    let body = opts.body;
    if (body && typeof body === 'object' && !(body instanceof FormData)) {
      headers['Content-Type'] = 'application/json';
      body = JSON.stringify(body);
    }
    let res;
    try {
      res = await fetch(url, {
        method: opts.method || 'GET',
        credentials: 'same-origin',
        headers, body,
      });
    } catch (e) {
      throw new Error('network_error');
    }
    let data = null;
    try { data = await res.json(); } catch (_) {}
    if (!res.ok || (data && data.ok === false)) {
      const err = new Error((data && data.error) || ('http_' + res.status));
      err.status = res.status;
      err.detail = data;
      throw err;
    }
    return data;
  };

  W.qs  = (sel, el = document) => el.querySelector(sel);
  W.qsa = (sel, el = document) => Array.from(el.querySelectorAll(sel));

  W.fmt = {
    n: (n) => Number(n || 0).toLocaleString('en-IN'),
    timeAgo(ts) {
      if (!ts) return '—';
      const t = (typeof ts === 'string') ? new Date(ts.replace(' ', 'T') + (ts.includes('Z') ? '' : '+05:30')) : new Date(ts);
      const s = Math.max(1, Math.floor((Date.now() - t.getTime()) / 1000));
      if (s < 60)        return s + 's ago';
      if (s < 3600)      return Math.floor(s / 60) + 'm ago';
      if (s < 86400)     return Math.floor(s / 3600) + 'h ago';
      if (s < 604800)    return Math.floor(s / 86400) + 'd ago';
      return t.toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });
    },
    timeShort(ts) {
      if (!ts) return '';
      const t = (typeof ts === 'string') ? new Date(ts.replace(' ', 'T') + (ts.includes('Z') ? '' : '+05:30')) : new Date(ts);
      return t.toLocaleTimeString('en-IN', { hour: '2-digit', minute: '2-digit' });
    },
    dayLabel(ts) {
      if (!ts) return '';
      const t = (typeof ts === 'string') ? new Date(ts.replace(' ', 'T') + (ts.includes('Z') ? '' : '+05:30')) : new Date(ts);
      const today = new Date(); today.setHours(0,0,0,0);
      const day = new Date(t); day.setHours(0,0,0,0);
      const diff = Math.round((today - day) / 86400000);
      if (diff === 0) return 'Today';
      if (diff === 1) return 'Yesterday';
      return t.toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });
    },
    initials(name) {
      if (!name) return '?';
      const w = String(name).trim().split(/\s+/);
      return ((w[0] && w[0][0]) || '').toUpperCase() + ((w[1] && w[1][0]) || '').toUpperCase();
    },
    truncate(s, n = 80) {
      if (!s) return '';
      return s.length <= n ? s : s.slice(0, n - 1) + '…';
    },
    escape(s) {
      if (s == null) return '';
      return String(s)
        .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
        .replace(/"/g,'&quot;').replace(/'/g,'&#39;');
    }
  };

  W.debounce = function (fn, wait = 250) {
    let t; return function (...args) {
      clearTimeout(t);
      t = setTimeout(() => fn.apply(this, args), wait);
    };
  };

  W.statusBadge = function (status, kind /* 'wa' | 'outreach' */) {
    const map = {
      wa: {
        valid:           ['green', 'On WhatsApp'],
        invalid:         ['red',   'Invalid'],
        not_on_whatsapp: ['red',   'Not on WA'],
        pending:         ['amber', 'Checking'],
        failed:          ['red',   'Check failed'],
      },
      outreach: {
        pending:   ['slate', 'Pending'],
        queued:    ['amber', 'Queued'],
        sent:      ['green', 'Sent'],
        delivered: ['green', 'Delivered'],
        read:      ['green', 'Read'],
        replied:   ['violet','Replied'],
        failed:    ['red',   'Failed'],
        skipped:   ['slate', 'Skipped'],
        blocked:   ['slate', 'Blocked'],
      }
    };
    const m = (map[kind] && map[kind][status]) || ['slate', status || '-'];
    return `<span class="badge ${m[0]} dot">${m[1]}</span>`;
  };
})();
