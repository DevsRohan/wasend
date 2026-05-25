/**
 * settings.js - Renders + saves settings panel
 */
(function () {
  'use strict';
  const W = window.WASEND;

  const tabs   = document.getElementById('settings-tabs');
  const fields = document.getElementById('settings-fields');
  const form   = document.getElementById('settings-form');
  const cancel = document.getElementById('btn-settings-cancel');

  if (!fields) return;

  let CACHE = null; // categories
  let CURRENT_CAT = 'connection';

  async function load() {
    fields.innerHTML = '<div class="text-center text-ink-500 text-[13px] py-8">Loading…</div>';
    try {
      const r = await W.api('api/settings.php');
      CACHE = (r.data && r.data.categories) || {};
      render();
    } catch (e) {
      fields.innerHTML = `<div class="text-center text-red-500 text-[13px] py-8">Failed: ${W.fmt.escape(e.message)}</div>`;
    }
  }

  function render() {
    const items = (CACHE[CURRENT_CAT] || []).slice().sort((a, b) => a.setting_key.localeCompare(b.setting_key));
    if (!items.length) { fields.innerHTML = `<div class="text-ink-500 text-[13px] py-8 text-center">No settings in this category.</div>`; return; }
    fields.innerHTML = items.map(rowHtml).join('');
  }

  function rowHtml(s) {
    const id = 'set_' + s.setting_key;
    const desc = s.description || '';
    let input = '';
    const val = s.setting_value == null ? '' : s.setting_value;
    if (s.value_type === 'bool') {
      input = `
        <label class="inline-flex items-center gap-2">
          <input type="checkbox" id="${id}" data-key="${s.setting_key}" data-type="bool" ${String(val) === '1' || val === true ? 'checked' : ''} class="w-4 h-4 rounded border-surface-border accent-emerald-500">
          <span class="text-[12.5px] text-ink-700">Enabled</span>
        </label>`;
    } else if (s.is_secret) {
      input = `<input id="${id}" data-key="${s.setting_key}" data-type="secret" type="password" autocomplete="off" placeholder="${val ? '••••• (saved, leave blank to keep)' : 'paste secret here'}" class="form-input">`;
    } else if (s.value_type === 'int' || s.value_type === 'float') {
      input = `<input id="${id}" data-key="${s.setting_key}" data-type="${s.value_type}" type="number" step="${s.value_type === 'float' ? '0.01' : '1'}" value="${W.fmt.escape(String(val))}" class="form-input">`;
    } else {
      const isLong = String(val).length > 80 || s.setting_key === 'owner_services';
      input = isLong
        ? `<textarea id="${id}" data-key="${s.setting_key}" data-type="string" class="form-input form-textarea">${W.fmt.escape(String(val))}</textarea>`
        : `<input id="${id}" data-key="${s.setting_key}" data-type="string" type="text" value="${W.fmt.escape(String(val))}" class="form-input">`;
    }
    return `
      <div class="form-row pb-3 border-b border-surface-border last:border-b-0 last:pb-0">
        <div>
          <label for="${id}" class="form-label">${W.fmt.escape(s.setting_key)}</label>
          ${desc ? `<div class="form-help">${W.fmt.escape(desc)}</div>` : ''}
        </div>
        <div>${input}</div>
      </div>`;
  }

  if (tabs) {
    tabs.addEventListener('click', (e) => {
      const b = e.target.closest('[data-cat]');
      if (!b) return;
      tabs.querySelectorAll('.cat-tab').forEach(t => { t.classList.remove('active-tab'); t.classList.add('text-ink-500'); t.classList.remove('text-ink-700'); });
      b.classList.add('active-tab'); b.classList.add('text-ink-700'); b.classList.remove('text-ink-500');
      CURRENT_CAT = b.getAttribute('data-cat');
      render();
    });
  }

  if (form) {
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      const payload = {};
      // Collect all categories present in CACHE
      Object.values(CACHE || {}).forEach(arr => arr.forEach(s => {
        const el = document.getElementById('set_' + s.setting_key);
        if (!el) return;
        if (s.value_type === 'bool') payload[s.setting_key] = el.checked ? '1' : '0';
        else if (s.is_secret) {
          const v = el.value.trim();
          if (v !== '') payload[s.setting_key] = v;
        } else {
          payload[s.setting_key] = el.value;
        }
      }));
      try {
        const r = await W.api('api/update_settings.php', { method: 'POST', body: { settings: payload } });
        W.toast && W.toast('Settings saved', 'success');
        await load();
      } catch (err) {
        W.toast && W.toast('Save failed: ' + err.message, 'error');
      }
    });
  }
  if (cancel) cancel.addEventListener('click', load);

  document.addEventListener('DOMContentLoaded', () => {
    if (fields) load();

    // Logs page also lives here for simplicity
    const logsBody = document.getElementById('logs-body');
    if (logsBody) bindLogsPage();
  });

  function bindLogsPage() {
    const lvl = document.getElementById('log-level');
    const cat = document.getElementById('log-category');
    const refresh = document.getElementById('log-refresh');
    const body = document.getElementById('logs-body');
    async function loadLogs() {
      body.innerHTML = '<tr><td colspan="5" class="px-4 py-10 text-center text-ink-500">Loading…</td></tr>';
      try {
        const r = await W.api(`api/get_logs.php?limit=200&level=${encodeURIComponent(lvl.value)}&category=${encodeURIComponent(cat.value)}`);
        const d = r.data || {};
        // Populate categories once
        if (cat.options.length <= 1 && (d.categories || []).length) {
          (d.categories || []).forEach(c => {
            const o = document.createElement('option'); o.value = c; o.textContent = c; cat.appendChild(o);
          });
        }
        const rows = d.rows || [];
        body.innerHTML = rows.length ? rows.map(rowToHtml).join('')
          : '<tr><td colspan="5" class="px-4 py-10 text-center text-ink-500">No logs.</td></tr>';
      } catch (e) {
        body.innerHTML = `<tr><td colspan="5" class="px-4 py-10 text-center text-red-500">${W.fmt.escape(e.message)}</td></tr>`;
      }
    }
    function rowToHtml(r) {
      const lvlClass = ({ info: 'green', warning: 'amber', error: 'red', critical: 'red', debug: 'slate' })[r.level] || 'slate';
      return `<tr class="hover:bg-surface-soft">
        <td class="px-4 py-2.5 font-mono text-[11.5px] text-ink-500">${W.fmt.escape(r.created_at)}</td>
        <td class="px-4 py-2.5"><span class="badge ${lvlClass}">${W.fmt.escape(r.level)}</span></td>
        <td class="px-4 py-2.5 text-[12.5px]">${W.fmt.escape(r.category)}</td>
        <td class="px-4 py-2.5 text-[12.5px] font-mono">${W.fmt.escape(r.action)}</td>
        <td class="px-4 py-2.5 text-[12.5px]">${W.fmt.escape(W.fmt.truncate(r.message || '', 200))}</td>
      </tr>`;
    }
    [lvl, cat].forEach(s => s && s.addEventListener('change', loadLogs));
    refresh && refresh.addEventListener('click', loadLogs);
    loadLogs();
  }
})();
