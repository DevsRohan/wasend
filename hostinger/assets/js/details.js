/**
 * details.js - "Get Details" side drawer
 */
(function () {
  'use strict';
  const W = window.WASEND;

  W.openLeadDetails = async function (leadId) {
    const html = `
      <header class="px-6 py-4 border-b border-surface-border flex items-center justify-between">
        <div>
          <h3 class="text-[15px] font-semibold tracking-tight">Lead Details</h3>
          <p class="text-[11.5px] text-ink-500 mt-0.5">Full intelligence + activity timeline</p>
        </div>
        <button data-close class="text-ink-500 hover:text-ink-900 text-lg leading-none">&times;</button>
      </header>
      <div id="drawer-content" class="flex-1 overflow-y-auto px-6 py-4">
        <div class="text-center text-ink-500 text-[13px] py-10">Loading…</div>
      </div>
      <footer class="px-6 py-3 border-t border-surface-border flex items-center justify-between bg-surface-soft">
        <button id="drawer-delete" class="px-3 py-2 rounded-lg border border-red-200 bg-white hover:bg-red-50 text-red-700 text-[12.5px]">Delete Lead</button>
        <div class="flex items-center gap-2">
          <button id="drawer-retry" class="px-3 py-2 rounded-lg border border-surface-border bg-white hover:bg-surface-soft text-ink-700 text-[12.5px]">Retry Outreach</button>
          <button id="drawer-save"  class="px-3 py-2 rounded-lg bg-brand-500 hover:bg-brand-600 text-white text-[12.5px]">Save Changes</button>
        </div>
      </footer>`;

    W.openDrawer(html, {
      onMount: async (panel) => {
        const content = panel.querySelector('#drawer-content');
        try {
          const r = await W.api(`api/get_lead_details.php?lead_id=${leadId}`);
          const d = r.data || {};
          content.innerHTML = renderBody(d);
          bindEvents(panel, d.lead);
        } catch (e) {
          content.innerHTML = `<div class="text-center text-red-500 text-[13px] py-10">Failed: ${W.fmt.escape(e.message)}</div>`;
        }

        panel.querySelector('#drawer-delete').addEventListener('click', async () => {
          if (!confirm('Delete this lead and all messages? This cannot be undone.')) return;
          try {
            await W.api('api/delete_lead.php', { method: 'POST', body: { lead_id: leadId } });
            W.toast && W.toast('Lead deleted', 'success');
            W.closeModal();
            W.reloadLeadsList && W.reloadLeadsList();
          } catch (e) {
            W.toast && W.toast('Delete failed: ' + e.message, 'error');
          }
        });
        panel.querySelector('#drawer-retry').addEventListener('click', async () => {
          try {
            await W.api('api/retry_lead.php', { method: 'POST', body: { lead_id: leadId } });
            W.toast && W.toast('Retry queued', 'success');
            W.reloadLeadsList && W.reloadLeadsList();
          } catch (e) {
            W.toast && W.toast(e.message, 'error');
          }
        });
        panel.querySelector('#drawer-save').addEventListener('click', async () => {
          const fields = {
            lead_id: leadId,
            business_name: panel.querySelector('[data-f="business_name"]').value,
            address:       panel.querySelector('[data-f="address"]').value,
            locality:      panel.querySelector('[data-f="locality"]').value,
            city:          panel.querySelector('[data-f="city"]').value,
            state:         panel.querySelector('[data-f="state"]').value,
            website_url:   panel.querySelector('[data-f="website_url"]').value,
            website_status:panel.querySelector('[data-f="website_status"]').value,
            language_preference: panel.querySelector('[data-f="language_preference"]').value,
            notes:         panel.querySelector('[data-f="notes"]').value,
            is_pinned:     panel.querySelector('[data-f="is_pinned"]').checked ? 1 : 0,
          };
          try {
            await W.api('api/update_lead.php', { method: 'POST', body: fields });
            W.toast && W.toast('Saved', 'success');
            W.reloadLeadsList && W.reloadLeadsList();
          } catch (e) { W.toast && W.toast('Save failed: ' + e.message, 'error'); }
        });
      }
    });
  };

  function field(label, html) {
    return `<div class="mb-4">
      <div class="text-[11px] uppercase tracking-wider text-ink-500 mb-1.5 font-medium">${label}</div>
      ${html}
    </div>`;
  }

  function renderBody(d) {
    const l = d.lead || {};
    const ctx = d.context || {};
    const tags = d.tags || [];
    const messages = d.messages || [];
    return `
      <!-- Hero -->
      <div class="rounded-xl border border-surface-border bg-gradient-to-br from-emerald-50 to-white p-4 mb-5">
        <div class="flex items-start gap-3">
          <div class="w-12 h-12 rounded-full bg-brand-500 text-white flex items-center justify-center font-semibold">${W.fmt.initials(l.business_name)}</div>
          <div class="flex-1">
            <input class="w-full bg-transparent text-[16px] font-semibold tracking-tight outline-none" data-f="business_name" value="${W.fmt.escape(l.business_name || '')}">
            <div class="text-[12px] text-ink-500 mt-0.5 font-mono">${W.fmt.escape(l.phone_number || '')}</div>
            <div class="mt-2 flex flex-wrap items-center gap-1.5">
              ${W.statusBadge(l.whatsapp_status, 'wa')}
              ${W.statusBadge(l.outreach_status, 'outreach')}
              <span class="badge slate">Pitch ${W.fmt.escape(ctx.pitch_type || l.pitch_type || '—')}</span>
              <span class="badge green">${W.fmt.escape(ctx.language || l.language_preference || 'auto')}</span>
            </div>
          </div>
          <label class="text-[11px] flex items-center gap-1.5 text-ink-700 cursor-pointer">
            <input type="checkbox" data-f="is_pinned" ${l.is_pinned ? 'checked' : ''}> Pin
          </label>
        </div>
      </div>

      <!-- AI insight -->
      <div class="rounded-xl border border-surface-border bg-white p-4 mb-5">
        <div class="text-[11px] uppercase tracking-wider text-ink-500 mb-2 font-medium">AI Outreach Insight</div>
        <div class="text-[12.5px] text-ink-700 space-y-1.5">
          <div><span class="text-ink-500">Pitch type:</span> ${ctx.pitch_type === 'A' ? 'Has Website (optimization angle)' : (ctx.pitch_type === 'B' ? 'No Website (presence angle)' : '—')}</div>
          <div><span class="text-ink-500">Language:</span> ${W.fmt.escape(ctx.language || '—')}</div>
          <div><span class="text-ink-500">Services chosen:</span> ${W.fmt.escape((ctx.services || []).join(', ') || '—')}</div>
          <div><span class="text-ink-500">Reasoning:</span> Based on ${l.review_count || 0} reviews, ${l.rating ? Number(l.rating).toFixed(1) + '★ rating' : 'no rating'}, and ${ctx.pitch_type === 'A' ? 'existing website' : 'no website'}.</div>
        </div>
      </div>

      <div class="grid grid-cols-2 gap-4">
        ${field('Locality', `<input class="form-input" data-f="locality" value="${W.fmt.escape(l.locality || '')}">`)}
        ${field('City',     `<input class="form-input" data-f="city"     value="${W.fmt.escape(l.city || '')}">`)}
        ${field('State',    `<input class="form-input" data-f="state"    value="${W.fmt.escape(l.state || '')}">`)}
        ${field('Language', `
          <select class="form-input" data-f="language_preference">
            ${['auto','hinglish','gujarati_mix','marathi_mix','en_in'].map(o => `<option value="${o}" ${l.language_preference === o ? 'selected' : ''}>${o}</option>`).join('')}
          </select>`)}
      </div>

      ${field('Address', `<textarea class="form-input form-textarea" data-f="address">${W.fmt.escape(l.address || '')}</textarea>`)}

      <div class="grid grid-cols-2 gap-4">
        ${field('Website URL', `<input class="form-input" data-f="website_url" value="${W.fmt.escape(l.website_url || '')}">`)}
        ${field('Website Status', `
          <select class="form-input" data-f="website_status">
            ${['has_website','no_website','unknown'].map(o => `<option value="${o}" ${l.website_status === o ? 'selected' : ''}>${o}</option>`).join('')}
          </select>`)}
      </div>

      <div class="grid grid-cols-2 gap-4">
        <div>
          <div class="text-[11px] uppercase tracking-wider text-ink-500 mb-1.5 font-medium">Rating</div>
          <div class="form-input bg-surface-soft">${l.rating ? Number(l.rating).toFixed(1) + ' ★' : '—'} (${l.review_count || 0} reviews)</div>
        </div>
        <div>
          <div class="text-[11px] uppercase tracking-wider text-ink-500 mb-1.5 font-medium">Last Contacted</div>
          <div class="form-input bg-surface-soft">${W.fmt.timeAgo(l.last_contacted_at)}</div>
        </div>
      </div>

      <!-- Tags -->
      <div class="mb-5">
        <div class="text-[11px] uppercase tracking-wider text-ink-500 mb-1.5 font-medium">Tags</div>
        <div id="tag-list" class="flex flex-wrap gap-2">
          ${tags.map(t => `<span class="tag-chip">${W.fmt.escape(t)}<button data-tag="${W.fmt.escape(t)}" data-action="remove-tag">&times;</button></span>`).join('')}
          <input id="tag-input" class="form-input" style="width:160px;" placeholder="Add tag, press Enter">
        </div>
      </div>

      ${field('Notes', `<textarea class="form-input form-textarea" data-f="notes" placeholder="Internal notes...">${W.fmt.escape(l.notes || '')}</textarea>`)}

      <!-- Conversation timeline -->
      <div class="mt-6">
        <div class="text-[11px] uppercase tracking-wider text-ink-500 mb-2 font-medium">Activity Timeline (latest)</div>
        <div class="rounded-xl border border-surface-border bg-white divide-y divide-surface-border">
          ${messages.length ? messages.slice(-10).reverse().map(m => `
            <div class="px-4 py-2.5 text-[12.5px]">
              <div class="flex items-center justify-between">
                <span class="font-medium ${m.direction === 'outbound' ? 'text-brand-700' : 'text-ink-900'}">${m.direction === 'outbound' ? 'You' : (l.business_name || 'Lead')}</span>
                <span class="text-[11px] text-ink-500">${W.fmt.timeShort(m.timestamp)}</span>
              </div>
              <div class="text-ink-700 mt-1">${W.fmt.escape(W.fmt.truncate(m.message_text, 200))}</div>
            </div>`).join('') : '<div class="px-4 py-6 text-center text-ink-500 text-[12.5px]">No messages yet.</div>'}
        </div>
      </div>
    `;
  }

  function bindEvents(panel, lead) {
    // Tag add/remove
    const tagInput = panel.querySelector('#tag-input');
    const tagList  = panel.querySelector('#tag-list');
    if (tagInput) {
      tagInput.addEventListener('keydown', async (e) => {
        if (e.key === 'Enter') {
          e.preventDefault();
          const t = tagInput.value.trim();
          if (!t) return;
          try {
            const r = await W.api('api/add_tag.php', { method: 'POST', body: { lead_id: lead.id, tag: t } });
            tagInput.value = '';
            renderTags(tagList, tagInput, r.data.tags || [], lead.id);
          } catch (err) { W.toast && W.toast(err.message, 'error'); }
        }
      });
    }
    tagList && tagList.addEventListener('click', async (e) => {
      const b = e.target.closest('[data-action="remove-tag"]');
      if (!b) return;
      const t = b.getAttribute('data-tag');
      try {
        const r = await W.api('api/remove_tag.php', { method: 'POST', body: { lead_id: lead.id, tag: t } });
        renderTags(tagList, tagInput, r.data.tags || [], lead.id);
      } catch (err) { W.toast && W.toast(err.message, 'error'); }
    });
  }

  function renderTags(tagList, tagInput, tags, leadId) {
    const html = tags.map(t => `<span class="tag-chip">${W.fmt.escape(t)}<button data-tag="${W.fmt.escape(t)}" data-action="remove-tag">&times;</button></span>`).join('');
    tagList.innerHTML = html;
    if (tagInput) tagList.appendChild(tagInput);
    if (tagInput) tagInput.value = '';
  }
})();
