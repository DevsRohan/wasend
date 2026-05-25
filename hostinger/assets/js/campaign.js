/**
 * campaign.js - Campaign control buttons (sidebar + campaigns.php)
 *                + Manual trigger buttons (Validate Now, Send Next)
 *                + Webhook diagnostic modal
 */
(function () {
  'use strict';
  const W = window.WASEND;

  async function call(path, ok) {
    try {
      const r = await W.api(path, { method: 'POST' });
      W.toast && W.toast(ok || (r.data && r.data.status) || 'Done', 'success');
      W.refreshKpi && W.refreshKpi();
    } catch (e) {
      W.toast && W.toast(e.message, 'error');
    }
  }

  // ------------------------------------------------------------------
  // Manual trigger: Validate Now
  // ------------------------------------------------------------------
  async function validateNow(btn) {
    if (!btn) return;
    const orig = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span>⏳ Validating…</span>';
    try {
      const r = await W.api('api/trigger_validate_now.php', { method: 'POST', body: { batch: 10 } });
      const d = r.data || {};
      if (d.validated === 0) {
        W.toast && W.toast('No pending leads to validate.', 'info');
      } else {
        W.toast && W.toast(`Validated ${d.validated} → ${d.valid} valid, ${d.invalid} not on WA. ${d.remaining} remaining.`, 'success', 5000);
      }
      const c = document.getElementById('validate-pending-count');
      if (c) c.textContent = d.remaining != null ? d.remaining + ' left' : '';
      W.reloadLeadsList && W.reloadLeadsList();
      W.refreshKpi && W.refreshKpi();
    } catch (e) {
      W.toast && W.toast('Validate failed: ' + e.message, 'error');
    } finally {
      btn.disabled = false;
      btn.innerHTML = orig;
    }
  }

  // ------------------------------------------------------------------
  // Manual trigger: Send Next campaign message
  // ------------------------------------------------------------------
  async function sendNext(btn) {
    if (!btn) return;
    const orig = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '⏳ Sending…';
    try {
      const r = await W.api('api/trigger_campaign_now.php', { method: 'POST', body: {} });
      const d = r.data || {};
      W.toast && W.toast(`✓ Sent to "${d.business_name}". Next allowed in ${d.next_in_seconds}s.`, 'success', 6000);
      W.reloadLeadsList && W.reloadLeadsList();
      W.refreshKpi && W.refreshKpi();
    } catch (e) {
      const detail = (e.detail && e.detail.detail) || e.detail || {};
      let msg = e.message;
      if (e.message === 'pacing_active' && detail.next_in_seconds) {
        msg = `Pacing: next send allowed in ${detail.next_in_seconds}s`;
      } else if (e.message === 'no_eligible_leads') {
        msg = 'No eligible leads. Run "Validate Pending" first.';
      } else if (e.message === 'engine_not_ready') {
        msg = 'Engine not ready: ' + (detail.state || 'unknown');
      } else if (e.message === 'campaign_not_running') {
        msg = 'Campaign is paused. Click Start first.';
      } else if (e.message === 'outside_working_hours') {
        msg = `Outside working hours window (${detail.window || ''}).`;
      } else if (e.message === 'daily_limit_reached') {
        msg = `Daily limit reached: ${detail.sent_today}/${detail.limit}`;
      }
      W.toast && W.toast(msg, 'warn', 6000);
    } finally {
      btn.disabled = false;
      btn.innerHTML = orig;
    }
  }

  // ------------------------------------------------------------------
  // Webhook Diagnostic modal
  // ------------------------------------------------------------------
  async function showWebhookDiag() {
    W.openModal(`
      <div class="p-6">
        <div class="flex items-center justify-between mb-4">
          <div>
            <h3 class="text-[15px] font-semibold tracking-tight">Webhook Diagnostic</h3>
            <p class="text-[11.5px] text-ink-500 mt-0.5">Verifies HF Engine → PHP webhook signature health</p>
          </div>
          <button data-close class="text-ink-500 hover:text-ink-900 text-lg">&times;</button>
        </div>
        <div id="webhook-diag-body">
          <div class="text-center text-ink-500 text-[13px] py-6">Loading…</div>
        </div>
        <div class="mt-4 flex items-center justify-end gap-2">
          <button id="webhook-self-test" class="px-3 py-2 rounded-lg border border-surface-border bg-white hover:bg-surface-soft text-ink-700 text-[12.5px]">Run Self-Test</button>
          <button data-close class="px-3 py-2 rounded-lg bg-brand-500 hover:bg-brand-600 text-white text-[12.5px]">Close</button>
        </div>
      </div>
    `, {
      onMount: async (card) => {
        const body = card.querySelector('#webhook-diag-body');
        const refreshDiag = async () => {
          body.innerHTML = '<div class="text-center text-ink-500 text-[13px] py-6">Loading…</div>';
          try {
            const r = await W.api('api/get_webhook_status.php');
            const d = r.data || {};
            const last = d.last_24h || {};
            const verdictColor =
              !d.secret_loaded             ? 'red'
              : last.signature_bad > 0 && last.signature_ok === 0 ? 'red'
              : last.total === 0           ? 'amber'
              : last.signature_bad > 0     ? 'amber'
              : 'green';
            body.innerHTML = `
              <div class="rounded-xl border border-surface-border p-4 bg-${verdictColor === 'green' ? 'emerald-50' : verdictColor === 'amber' ? 'amber-50' : 'red-50'} mb-4">
                <div class="text-[11px] uppercase font-medium tracking-wider mb-1 text-ink-500">Verdict</div>
                <div class="text-[13px] font-medium ${verdictColor === 'green' ? 'text-brand-800' : verdictColor === 'amber' ? 'text-amber-800' : 'text-red-700'}">${W.fmt.escape(d.hint || '')}</div>
              </div>
              <div class="space-y-1.5 text-[12.5px]">
                <div class="flex justify-between"><span class="text-ink-500">Secret loaded</span><span class="font-medium ${d.secret_loaded ? 'text-brand-700' : 'text-red-600'}">${d.secret_loaded ? '✓ yes (' + d.secret_length + ' chars)' : '✗ no'}</span></div>
                <div class="flex justify-between"><span class="text-ink-500">APP_KEY set</span><span class="font-medium ${d.app_key_set ? 'text-brand-700' : 'text-amber-600'}">${d.app_key_set ? '✓ yes' : '⚠ default'}</span></div>
                <div class="flex justify-between"><span class="text-ink-500">Webhook URL</span><span class="font-mono text-[11px]">${W.fmt.escape(d.webhook_url || '')}</span></div>
                <div class="flex justify-between"><span class="text-ink-500">Last 24h total</span><span class="font-medium">${W.fmt.n(last.total)}</span></div>
                <div class="flex justify-between"><span class="text-ink-500">Signature OK</span><span class="font-medium text-brand-700">${W.fmt.n(last.signature_ok)}</span></div>
                <div class="flex justify-between"><span class="text-ink-500">Signature BAD</span><span class="font-medium ${last.signature_bad > 0 ? 'text-red-600' : 'text-ink-700'}">${W.fmt.n(last.signature_bad)}</span></div>
                <div class="flex justify-between"><span class="text-ink-500">Last OK at</span><span>${d.last_ok_at ? W.fmt.timeAgo(d.last_ok_at) : '—'}</span></div>
                <div class="flex justify-between"><span class="text-ink-500">Last BAD at</span><span>${d.last_bad_at ? W.fmt.timeAgo(d.last_bad_at) : '—'}</span></div>
              </div>
              ${(d.recent || []).length ? `
              <div class="mt-4">
                <div class="text-[11px] uppercase tracking-wider text-ink-500 font-medium mb-2">Recent webhooks</div>
                <div class="rounded-lg border border-surface-border overflow-hidden">
                  <table class="w-full text-[11.5px]">
                    <thead class="bg-surface-soft text-ink-500">
                      <tr><th class="px-2 py-1.5 text-left">When</th><th class="px-2 py-1.5 text-left">Type</th><th class="px-2 py-1.5">Sig</th><th class="px-2 py-1.5">Done</th></tr>
                    </thead>
                    <tbody>
                      ${d.recent.map(r => `<tr class="border-t border-surface-border">
                        <td class="px-2 py-1.5">${W.fmt.timeAgo(r.received_at)}</td>
                        <td class="px-2 py-1.5 font-mono">${W.fmt.escape(r.event_type)}</td>
                        <td class="px-2 py-1.5 text-center">${r.signature_ok == 1 ? '✓' : '<span class="text-red-600">✗</span>'}</td>
                        <td class="px-2 py-1.5 text-center">${r.processed == 1 ? '✓' : '—'}</td>
                      </tr>`).join('')}
                    </tbody>
                  </table>
                </div>
              </div>` : ''}
            `;
          } catch (e) {
            body.innerHTML = `<div class="text-center text-red-500 text-[13px] py-6">${W.fmt.escape(e.message)}</div>`;
          }
        };

        await refreshDiag();
        const sel = card.querySelector('#webhook-self-test');
        sel.addEventListener('click', async () => {
          sel.disabled = true; sel.textContent = 'Running…';
          try {
            const r = await W.api('api/test_webhook_local.php', { method: 'POST', body: {} });
            const d = r.data || {};
            const ok = d.http_status >= 200 && d.http_status < 300;
            W.toast && W.toast(ok ? 'Self-test PASSED ✓' : 'Self-test FAILED', ok ? 'success' : 'error', 6000);
            await refreshDiag();
          } catch (e) {
            W.toast && W.toast('Self-test error: ' + e.message, 'error');
          } finally {
            sel.disabled = false; sel.textContent = 'Run Self-Test';
          }
        });
      }
    });
  }

  // ------------------------------------------------------------------
  // Pending count badge auto-update
  // ------------------------------------------------------------------
  function paintPendingCount() {
    const c = document.getElementById('validate-pending-count');
    if (!c || !W.lastStats) return;
    const n = W.lastStats.wa_pending || 0;
    c.textContent = n > 0 ? n + ' left' : 'all done';
  }

  // ------------------------------------------------------------------
  // Webhook health dot in sidebar
  // ------------------------------------------------------------------
  async function refreshWebhookDot() {
    const dot = document.getElementById('webhook-health-dot');
    if (!dot) return;
    try {
      const r = await W.api('api/get_webhook_status.php');
      const d = r.data || {};
      const last = d.last_24h || {};
      if (!d.secret_loaded || (last.signature_bad > 0 && last.signature_ok === 0)) {
        dot.textContent = '🔴';
      } else if (last.signature_bad > 0 || last.total === 0) {
        dot.textContent = '🟡';
      } else {
        dot.textContent = '🟢';
      }
    } catch (_) {
      dot.textContent = '⚪';
    }
  }

  document.addEventListener('DOMContentLoaded', () => {
    const map = [
      ['btn-start-campaign',   'api/start_campaign.php', 'Campaign started'],
      ['btn-pause-campaign',   'api/pause_campaign.php', 'Campaign paused'],
      ['btn-campaign-start',   'api/start_campaign.php', 'Campaign started'],
      ['btn-campaign-pause',   'api/pause_campaign.php', 'Campaign paused'],
      ['btn-campaign-resume',  'api/resume_campaign.php','Campaign resumed'],
      ['btn-campaign-stop',    'api/stop_campaign.php',  'Campaign stopped'],
      ['btn-restart-engine',   'api/restart_engine.php', 'Engine restart requested'],
    ];
    map.forEach(([id, path, msg]) => {
      const el = document.getElementById(id);
      if (el) el.addEventListener('click', () => call(path, msg));
    });

    const vBtn = document.getElementById('btn-validate-now');
    if (vBtn) vBtn.addEventListener('click', () => validateNow(vBtn));

    const sBtn = document.getElementById('btn-send-next');
    if (sBtn) sBtn.addEventListener('click', () => sendNext(sBtn));

    const wBtn = document.getElementById('btn-webhook-diag');
    if (wBtn) wBtn.addEventListener('click', showWebhookDiag);

    refreshWebhookDot();
    setInterval(refreshWebhookDot, 60_000);
  });

  // Hook into KPI refresh to update "pending" count
  const origRefreshKpi = W.refreshKpi;
  W.refreshKpi = async function () {
    if (origRefreshKpi) await origRefreshKpi();
    paintPendingCount();
  };
})();
