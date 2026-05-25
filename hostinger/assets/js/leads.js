/**
 * leads.js - Manages lead list (dashboard middle column AND leads.php table view)
 */
(function () {
  'use strict';
  const W = window.WASEND;

  // ---------- Dashboard middle column ----------
  const list   = document.getElementById('leads-list');
  const search = document.getElementById('leads-search');
  const tabs   = document.getElementById('leads-tabs');
  const count  = document.getElementById('leads-count');

  // ---------- Leads page table ----------
  const tableBody  = document.getElementById('leads-table-body');
  const pageSearch = document.getElementById('leads-page-search');
  const pageCount  = document.getElementById('leads-page-count');
  const filterWs = document.getElementById('filter-ws');
  const filterOs = document.getElementById('filter-os');
  const filterPt = document.getElementById('filter-pt');
  const prevBtn  = document.getElementById('leads-prev');
  const nextBtn  = document.getElementById('leads-next');

  const state = {
    q: '', tab: '', ws: '', os: '', pt: '',
    page: 1, perPage: 50, total: 0,
    activeId: null,
  };

  W.leadsState = state;

  function badge(s, kind) { return W.statusBadge(s, kind); }

  // ---------------- DASHBOARD list ----------------
  function renderListSkeleton() {
    if (!list) return;
    list.innerHTML = Array.from({ length: 6 }).map(() => `
      <div class="sk-row">
        <div class="sk-avatar skeleton"></div>
        <div class="flex-1 space-y-2">
          <div class="sk-line short skeleton"></div>
          <div class="sk-line long  skeleton"></div>
        </div>
      </div>`).join('');
  }

  function renderList(rows) {
    if (!list) return;
    if (!rows.length) {
      list.innerHTML = `<div class="p-8 text-center text-[13px] text-ink-500">
        <div class="text-2xl mb-2">📭</div>
        <div>No leads found.</div>
        <div class="text-[11.5px] mt-1">Import a CSV to get started.</div>
      </div>`;
      return;
    }
    list.innerHTML = rows.map(r => leadCardHtml(r)).join('');
    list.querySelectorAll('.lead-card').forEach(el => {
      el.addEventListener('click', () => {
        const id = parseInt(el.getAttribute('data-id'), 10);
        selectLead(id);
      });
    });
    if (state.activeId) {
      const active = list.querySelector(`.lead-card[data-id="${state.activeId}"]`);
      if (active) active.classList.add('active');
    }
  }

  function leadCardHtml(r) {
    const initials = W.fmt.initials(r.business_name);
    const subtitle = [r.locality || r.city, r.state].filter(Boolean).join(', ') || (r.phone_number || '');
    const unread   = (r.unread_count || 0) > 0;
    const replied  = r.outreach_status === 'replied';
    return `
      <div class="lead-card" data-id="${r.id}">
        <div class="flex items-start gap-3">
          <div class="w-10 h-10 rounded-full bg-brand-50 text-brand-700 flex items-center justify-center text-[12.5px] font-semibold shrink-0">
            ${W.fmt.escape(initials)}
          </div>
          <div class="flex-1 min-w-0">
            <div class="flex items-center justify-between gap-2">
              <div class="text-[13.5px] font-medium text-ink-900 truncate">${W.fmt.escape(r.business_name)}</div>
              <div class="flex items-center gap-1 shrink-0">
                ${unread ? `<span class="text-[10px] bg-brand-500 text-white rounded-full px-1.5 py-0.5">${r.unread_count}</span>` : ''}
                <span class="text-[10.5px] text-ink-500">${W.fmt.timeAgo(r.last_reply_at || r.last_contacted_at || r.updated_at)}</span>
              </div>
            </div>
            <div class="text-[11.5px] text-ink-500 truncate mt-0.5">${W.fmt.escape(subtitle)} · ${W.fmt.escape(r.phone_number || '')}</div>
            <div class="mt-1.5 flex flex-wrap items-center gap-1.5">
              ${badge(r.whatsapp_status, 'wa')}
              ${badge(r.outreach_status, 'outreach')}
              ${r.pitch_type === 'A' ? '<span class="badge slate">Type A</span>' : (r.pitch_type === 'B' ? '<span class="badge slate">Type B</span>' : '')}
              ${replied ? '<span class="badge violet">Manual mode</span>' : ''}
            </div>
          </div>
        </div>
      </div>`;
  }

  function selectLead(id) {
    state.activeId = id;
    if (list) {
      list.querySelectorAll('.lead-card').forEach(el => el.classList.toggle('active', parseInt(el.dataset.id, 10) === id));
    }
    if (W.openChat) W.openChat(id);
  }

  W.selectLead = selectLead;

  async function loadList() {
    if (!list) return;
    renderListSkeleton();
    try {
      const r = await W.api(`api/get_leads.php?q=${encodeURIComponent(state.q)}&tab=${encodeURIComponent(state.tab)}&page=1&per_page=80`);
      const d = r.data || {};
      renderList(d.rows || []);
      if (count) count.textContent = `${d.rows.length} of ${W.fmt.n(d.total || 0)} leads`;
    } catch (e) {
      list.innerHTML = `<div class="p-6 text-center text-red-500 text-[13px]">Failed to load leads</div>`;
    }
  }

  if (search) {
    search.addEventListener('input', W.debounce(() => { state.q = search.value.trim(); loadList(); }, 250));
  }
  if (tabs) {
    tabs.addEventListener('click', (e) => {
      const btn = e.target.closest('[data-tab]');
      if (!btn) return;
      tabs.querySelectorAll('.tab').forEach(t => { t.classList.remove('active-tab'); t.classList.add('text-ink-500'); t.classList.remove('text-ink-700'); });
      btn.classList.add('active-tab'); btn.classList.add('text-ink-700'); btn.classList.remove('text-ink-500');
      state.tab = btn.getAttribute('data-tab') || '';
      loadList();
    });
  }
  const refreshBtn = document.getElementById('btn-refresh-leads');
  if (refreshBtn) refreshBtn.addEventListener('click', loadList);

  // ---------------- LEADS PAGE table ----------------
  function renderTableSkeleton() {
    if (!tableBody) return;
    tableBody.innerHTML = Array.from({ length: 6 }).map(() => `
      <tr>
        <td class="px-4 py-3"><div class="sk-line long skeleton"></div></td>
        <td class="px-4 py-3"><div class="sk-line skeleton"></div></td>
        <td class="px-4 py-3"><div class="sk-line skeleton"></div></td>
        <td class="px-4 py-3"><div class="sk-line short skeleton"></div></td>
        <td class="px-4 py-3"><div class="sk-line short skeleton"></div></td>
        <td class="px-4 py-3"><div class="sk-line short skeleton"></div></td>
        <td class="px-4 py-3"><div class="sk-line short skeleton"></div></td>
        <td class="px-4 py-3"><div class="sk-line short skeleton"></div></td>
      </tr>`).join('');
  }

  function tableRowHtml(r) {
    const loc = [r.locality, r.city, r.state].filter(Boolean).join(', ');
    const rating = r.rating ? `${Number(r.rating).toFixed(1)} ★` : '—';
    return `
      <tr class="hover:bg-surface-soft transition-colors">
        <td class="px-4 py-3">
          <div class="font-medium text-ink-900 truncate max-w-[260px]">${W.fmt.escape(r.business_name)}</div>
          <div class="text-[11.5px] text-ink-500 truncate max-w-[260px]">${W.fmt.escape(r.tags || '')}</div>
        </td>
        <td class="px-4 py-3 text-ink-700 text-[12.5px] truncate max-w-[220px]">${W.fmt.escape(loc)}</td>
        <td class="px-4 py-3 font-mono text-[12px]">${W.fmt.escape(r.phone_number || '')}</td>
        <td class="px-4 py-3 text-[12.5px]">${rating} <span class="text-ink-500">(${W.fmt.n(r.review_count || 0)})</span></td>
        <td class="px-4 py-3">${badge(r.whatsapp_status, 'wa')}</td>
        <td class="px-4 py-3">${badge(r.outreach_status, 'outreach')}</td>
        <td class="px-4 py-3 text-[12.5px]">${r.pitch_type === 'A' ? 'Has site' : (r.pitch_type === 'B' ? 'No site' : '—')}</td>
        <td class="px-4 py-3 text-right">
          <button data-action="open-chat" data-id="${r.id}" class="px-2.5 py-1 rounded-md border border-surface-border bg-white hover:bg-surface-soft text-ink-700 text-[11.5px]">Open</button>
          <button data-action="details"   data-id="${r.id}" class="px-2.5 py-1 rounded-md bg-brand-500 hover:bg-brand-600 text-white text-[11.5px]">Details</button>
        </td>
      </tr>`;
  }

  async function loadTable() {
    if (!tableBody) return;
    renderTableSkeleton();
    try {
      const params = new URLSearchParams({
        q: state.q, ws: state.ws, os: state.os, pt: state.pt,
        page: state.page, per_page: state.perPage
      });
      const r = await W.api('api/get_leads.php?' + params.toString());
      const d = r.data || {};
      state.total = d.total || 0;
      const rows = d.rows || [];
      tableBody.innerHTML = rows.length
        ? rows.map(tableRowHtml).join('')
        : '<tr><td colspan="8" class="px-4 py-10 text-center text-ink-500">No leads match.</td></tr>';
      tableBody.querySelectorAll('button[data-action]').forEach(b => {
        b.addEventListener('click', () => {
          const id = parseInt(b.dataset.id, 10);
          const a  = b.dataset.action;
          if (a === 'open-chat') location.href = `dashboard.php#lead=${id}`;
          else if (a === 'details') W.openLeadDetails && W.openLeadDetails(id);
        });
      });
      if (pageCount) {
        const start = (state.page - 1) * state.perPage + 1;
        const end   = Math.min(state.page * state.perPage, state.total);
        pageCount.textContent = state.total ? `Showing ${start}–${end} of ${W.fmt.n(state.total)}` : 'No leads';
      }
    } catch (e) {
      tableBody.innerHTML = '<tr><td colspan="8" class="px-4 py-10 text-center text-red-500">Failed to load.</td></tr>';
    }
  }

  if (pageSearch) pageSearch.addEventListener('input', W.debounce(() => { state.q = pageSearch.value.trim(); state.page = 1; loadTable(); }, 250));
  if (filterWs)   filterWs.addEventListener('change', () => { state.ws = filterWs.value; state.page = 1; loadTable(); });
  if (filterOs)   filterOs.addEventListener('change', () => { state.os = filterOs.value; state.page = 1; loadTable(); });
  if (filterPt)   filterPt.addEventListener('change', () => { state.pt = filterPt.value; state.page = 1; loadTable(); });
  if (prevBtn)    prevBtn.addEventListener('click', () => { if (state.page > 1) { state.page--; loadTable(); } });
  if (nextBtn)    nextBtn.addEventListener('click', () => { if (state.page * state.perPage < state.total) { state.page++; loadTable(); } });

  // Realtime updates
  document.addEventListener('DOMContentLoaded', () => {
    if (list)      loadList();
    if (tableBody) loadTable();
    if (W.rt) {
      const reload = () => { if (list) loadList(); if (tableBody) loadTable(); };
      W.rt.on('msg:in',  reload);
      W.rt.on('msg:out', reload);
      W.rt.on('lead:validated', reload);
      W.rt.on('campaign:state', reload);
    }
    // Open chat from URL hash
    const m = (location.hash || '').match(/lead=(\d+)/);
    if (m && list) setTimeout(() => selectLead(parseInt(m[1], 10)), 250);
  });

  W.reloadLeadsList = loadList;
})();
