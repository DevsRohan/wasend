/**
 * chat.js - Conversation pane (right column on dashboard.php)
 */
(function () {
  'use strict';
  const W = window.WASEND;

  const empty   = document.getElementById('chat-empty');
  const thread  = document.getElementById('chat-thread');
  const avatar  = document.getElementById('chat-avatar');
  const name    = document.getElementById('chat-name');
  const sub     = document.getElementById('chat-sub');
  const insight = document.getElementById('ai-insight');
  const area    = document.getElementById('messages-area');
  const input   = document.getElementById('composer-input');
  const sendBtn = document.getElementById('composer-send');
  const previewBtn = document.getElementById('btn-preview-msg');
  const checkBtn   = document.getElementById('btn-check-number');
  const detailsBtn = document.getElementById('btn-get-details');

  let currentLead = null;
  let currentLeadPhone = null;
  let currentLeadJid = null;

  function show(el)  { if (el) { el.classList.remove('hidden'); el.classList.add('flex'); } }
  function hide(el)  { if (el) { el.classList.add('hidden'); el.classList.remove('flex'); } }

  function renderMessages(messages) {
    if (!area) return;
    if (!messages || !messages.length) {
      area.innerHTML = `
        <div class="flex flex-col items-center justify-center text-center py-16 text-ink-500">
          <div class="text-3xl mb-2">💬</div>
          <div class="text-[13px]">No messages yet.</div>
          <div class="text-[11.5px] mt-1">Once campaign sends or lead replies, conversation will appear here.</div>
        </div>`;
      return;
    }
    let html = '';
    let lastDay = null;
    messages.forEach(m => {
      const day = W.fmt.dayLabel(m.timestamp);
      if (day !== lastDay) {
        html += `<div class="day-sep">${W.fmt.escape(day)}</div>`;
        lastDay = day;
      }
      const out = m.direction === 'outbound';
      let tickHtml = '';
      if (out) {
        const tickClass = ({ read: 'read', delivered: 'delivered', sent: 'delivered', failed: 'failed' })[m.status] || 'delivered';
        const tickGlyph = m.status === 'read' ? '✓✓' : (m.status === 'failed' ? '!' : (m.status === 'delivered' ? '✓✓' : '✓'));
        tickHtml = `<span class="tick ${tickClass}">${tickGlyph}</span>`;
      }
      html += `
        <div class="bubble-row ${out ? 'out' : 'in'} fade-in-up">
          <div class="bubble ${out ? 'bubble-out' : 'bubble-in'} ${m.is_first_outreach ? 'system' : ''}">
            <div>${W.fmt.escape(m.message_text)}</div>
            <div class="bubble-time">${W.fmt.timeShort(m.timestamp)}${tickHtml}</div>
          </div>
        </div>`;
    });
    area.innerHTML = html;
    area.scrollTop = area.scrollHeight;
  }

  function renderInsight(ctx) {
    if (!insight) return;
    insight.querySelector('[data-insight="pitch"]').textContent =
      ctx.pitch_type === 'A' ? 'Pitch: Has Website' : (ctx.pitch_type === 'B' ? 'Pitch: No Website' : 'Pitch: —');
    insight.querySelector('[data-insight="lang"]').textContent =
      'Language: ' + (ctx.language || '—');
    insight.querySelector('[data-insight="services"]').textContent =
      'Services: ' + ((ctx.services || []).join(', ') || '—');
  }

  function renderHeader(lead) {
    if (avatar) avatar.textContent = W.fmt.initials(lead.business_name);
    if (name)   name.textContent   = lead.business_name;
    if (sub) {
      const loc = [lead.locality, lead.city, lead.state].filter(Boolean).join(', ');
      sub.innerHTML = `${W.fmt.escape(loc || '—')} · <span class="font-mono">${W.fmt.escape(lead.phone_number || '')}</span>`;
    }
  }

  async function openChat(leadId) {
    if (!area) return;
    hide(empty); show(thread);
    currentLead = leadId;
    area.innerHTML = '<div class="text-center text-ink-500 text-[13px] py-10">Loading conversation…</div>';
    try {
      const r = await W.api(`api/get_lead_details.php?lead_id=${leadId}`);
      const d = r.data || {};
      currentLeadPhone = (d.lead && d.lead.phone_number) || null;
      currentLeadJid   = (d.lead && d.lead.whatsapp_jid)
        || (currentLeadPhone ? currentLeadPhone + '@c.us' : null);
      renderHeader(d.lead);
      renderInsight(d.context || {});
      renderMessages(d.messages || []);
      // Mark as read in BG
      W.api('api/mark_read.php', { method: 'POST', body: { lead_id: leadId } }).catch(() => {});
      if (W.reloadLeadsList) W.reloadLeadsList();
    } catch (e) {
      area.innerHTML = `<div class="text-center text-red-500 text-[13px] py-10">Failed: ${W.fmt.escape(e.message)}</div>`;
    }
  }
  W.openChat = openChat;

  // Composer
  if (input) {
    input.addEventListener('input', () => {
      input.style.height = 'auto';
      input.style.height = Math.min(160, input.scrollHeight) + 'px';
    });
    input.addEventListener('keydown', (e) => {
      if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendManual(); }
    });
  }
  if (sendBtn) sendBtn.addEventListener('click', sendManual);

  async function sendManual() {
    if (!currentLead) return;
    const text = (input.value || '').trim();
    if (!text) return;
    sendBtn.disabled = true;

    // Optimistic ghost
    const ghost = document.createElement('div');
    ghost.className = 'bubble-row out';
    ghost.innerHTML = `<div class="bubble bubble-out ghost">${W.fmt.escape(text)}<div class="bubble-time">sending…</div></div>`;
    area.appendChild(ghost);
    area.scrollTop = area.scrollHeight;

    try {
      const r = await W.api('api/send_manual.php', {
        method: 'POST', body: { lead_id: currentLead, text }
      });
      ghost.remove();
      input.value = ''; input.style.height = 'auto';
      // Reload thread to get authoritative version
      const det = await W.api(`api/get_lead_details.php?lead_id=${currentLead}`);
      renderMessages((det.data || {}).messages || []);
      W.toast && W.toast('Message sent', 'success', 2200);
    } catch (e) {
      ghost.querySelector('.bubble-time').textContent = 'failed: ' + e.message;
      ghost.classList.remove('ghost');
      ghost.querySelector('.bubble').classList.add('bubble-out');
      W.toast && W.toast('Send failed: ' + e.message, 'error');
    } finally {
      sendBtn.disabled = false;
    }
  }

  // AI preview
  if (previewBtn) {
    previewBtn.addEventListener('click', async () => {
      if (!currentLead) return;
      previewBtn.disabled = true; previewBtn.textContent = 'Generating…';
      try {
        const r = await W.api('api/preview_message.php', { method: 'POST', body: { lead_id: currentLead } });
        const d = r.data || {};
        showPreviewModal(d.message, d.source, d.context);
      } catch (e) {
        W.toast && W.toast('Preview failed: ' + e.message, 'error');
      } finally {
        previewBtn.disabled = false; previewBtn.textContent = 'AI Preview';
      }
    });
  }

  function showPreviewModal(message, source, ctx) {
    const html = `
      <div class="p-6">
        <div class="flex items-center justify-between mb-3">
          <div>
            <h3 class="text-[15px] font-semibold tracking-tight">AI Preview</h3>
            <div class="text-[11px] text-ink-500 mt-0.5">Source: ${W.fmt.escape(source || '—')} · ${W.fmt.escape(ctx && ctx.language || '—')} · Pitch ${W.fmt.escape(ctx && ctx.pitch_type || '—')}</div>
          </div>
          <button data-close class="text-ink-500 hover:text-ink-900 text-lg">&times;</button>
        </div>
        <textarea id="preview-text" class="form-input form-textarea" style="min-height:240px;">${W.fmt.escape(message || '')}</textarea>
        <div class="mt-4 flex items-center justify-end gap-2">
          <button data-close class="px-3 py-2 rounded-lg border border-surface-border bg-white hover:bg-surface-soft text-ink-700 text-[12.5px]">Cancel</button>
          <button id="preview-send" class="px-3 py-2 rounded-lg bg-brand-500 hover:bg-brand-600 text-white text-[12.5px]">Send Now</button>
        </div>
      </div>`;
    W.openModal(html, {
      onMount: (card) => {
        card.querySelector('#preview-send').addEventListener('click', async () => {
          const t = card.querySelector('#preview-text').value.trim();
          if (!t) return;
          try {
            await W.api('api/send_manual.php', { method: 'POST', body: { lead_id: currentLead, text: t } });
            W.closeModal();
            W.toast && W.toast('Sent', 'success');
            openChat(currentLead);
          } catch (e) {
            W.toast && W.toast('Send failed: ' + e.message, 'error');
          }
        });
      }
    });
  }

  if (checkBtn) {
    checkBtn.addEventListener('click', async () => {
      if (!currentLead) return;
      checkBtn.disabled = true; checkBtn.textContent = 'Checking…';
      try {
        const r = await W.api('api/check_number.php', { method: 'POST', body: { lead_id: currentLead } });
        const d = r.data || {};
        W.toast && W.toast(d.on_whatsapp ? 'On WhatsApp ✓' : 'Not on WhatsApp', d.on_whatsapp ? 'success' : 'warn');
        openChat(currentLead);
        W.reloadLeadsList && W.reloadLeadsList();
      } catch (e) {
        W.toast && W.toast('Check failed: ' + e.message, 'error');
      } finally {
        checkBtn.disabled = false; checkBtn.textContent = 'Check WA';
      }
    });
  }

  if (detailsBtn) detailsBtn.addEventListener('click', () => {
    if (currentLead && W.openLeadDetails) W.openLeadDetails(currentLead);
  });

  // Realtime - socket payloads use JID/phone (HF doesn't know lead_id),
  // so we match by phone/JID to decide whether to refresh the active chat.
  function jidMatchesCurrent(data) {
    if (!data) return false;
    const candidates = [data.from, data.to, data.jid].filter(Boolean);
    for (const c of candidates) {
      const phone = String(c).replace(/@.*/, '');
      if (currentLeadJid && c === currentLeadJid) return true;
      if (currentLeadPhone && phone === currentLeadPhone) return true;
    }
    return false;
  }

  document.addEventListener('DOMContentLoaded', () => {
    if (W.rt) {
      W.rt.on('msg:in', (data) => {
        if (currentLead && jidMatchesCurrent(data)) openChat(currentLead);
        W.reloadLeadsList && W.reloadLeadsList();
        W.refreshKpi && W.refreshKpi();
        W.playNotify && W.playNotify();
      });
      W.rt.on('msg:out', (data) => {
        if (currentLead && jidMatchesCurrent(data)) openChat(currentLead);
        W.reloadLeadsList && W.reloadLeadsList();
        W.refreshKpi && W.refreshKpi();
      });
      W.rt.on('msg:ack', (data) => {
        // ack events have wa_message_id but no jid; just refresh active chat
        // and lead list to update tick marks/badges.
        if (currentLead) openChat(currentLead);
        W.reloadLeadsList && W.reloadLeadsList();
      });
      W.rt.on('sync:tick', () => {
        // Polling fallback fires when socket is down. Refresh both views.
        if (currentLead) openChat(currentLead);
        W.reloadLeadsList && W.reloadLeadsList();
      });
    }
  });
})();
