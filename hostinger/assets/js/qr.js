/**
 * qr.js - Shows WhatsApp QR (modal on dashboard, inline on campaigns page)
 */
(function () {
  'use strict';
  const W = window.WASEND;

  async function fetchQr() {
    try {
      const r = await W.api('api/get_qr.php');
      return r.data;
    } catch (e) {
      return { state: 'error', qr: null, error: e.message };
    }
  }

  function renderInline(target, info) {
    if (!target) return;
    if (!info) {
      target.innerHTML = '<span class="text-[12px] text-ink-500">Engine not configured</span>';
      return;
    }
    if (info.qr) {
      target.innerHTML = `<img src="${info.qr}" alt="WhatsApp QR" class="w-full h-full object-contain rounded-lg">`;
    } else if ((info.state || '').toLowerCase().includes('ready') || (info.state || '').toLowerCase().includes('connected')) {
      target.innerHTML = `
        <div class="text-center text-[12px]">
          <div class="w-10 h-10 mx-auto rounded-full bg-brand-100 text-brand-700 flex items-center justify-center mb-2">✓</div>
          <div class="font-medium text-ink-900">WhatsApp connected</div>
          <div class="text-ink-500 mt-1">Engine ready</div>
        </div>`;
    } else {
      target.innerHTML = `<span class="text-[12px] text-ink-500">${info.state || 'waiting for QR…'}</span>`;
    }
  }

  function openQrModal() {
    const html = `
      <div class="p-6">
        <div class="flex items-center justify-between mb-4">
          <h3 class="text-[15px] font-semibold tracking-tight">Scan WhatsApp QR</h3>
          <button data-close class="text-ink-500 hover:text-ink-900 text-lg leading-none">&times;</button>
        </div>
        <div id="qr-modal-canvas" class="w-[280px] h-[280px] mx-auto rounded-xl border border-surface-border bg-surface-soft flex items-center justify-center text-[12px] text-ink-500">
          Loading QR…
        </div>
        <p class="mt-4 text-[12px] text-ink-500 text-center leading-relaxed">
          Open WhatsApp on your phone → Settings → Linked Devices → Link a Device → scan this QR.
        </p>
        <div class="mt-3 flex justify-center gap-2">
          <button id="qr-modal-refresh" class="px-3 py-1.5 rounded-md border border-surface-border bg-white hover:bg-surface-soft text-[12px]">Refresh</button>
        </div>
      </div>`;
    W.openModal(html, {
      onMount: async (card) => {
        const canvas = card.querySelector('#qr-modal-canvas');
        const refresh = card.querySelector('#qr-modal-refresh');
        const update = async () => {
          canvas.innerHTML = '<span class="text-[12px] text-ink-500">Loading QR…</span>';
          renderInline(canvas, await fetchQr());
        };
        await update();
        refresh.addEventListener('click', update);
      }
    });
  }

  document.addEventListener('DOMContentLoaded', async () => {
    const showBtn = document.getElementById('btn-show-qr');
    if (showBtn) showBtn.addEventListener('click', (e) => { e.preventDefault(); openQrModal(); });

    const inline = document.getElementById('qr-canvas');
    if (inline) {
      const update = async () => renderInline(inline, await fetchQr());
      await update();
      const refresh = document.getElementById('btn-refresh-qr');
      if (refresh) refresh.addEventListener('click', update);
      // Auto-refresh every 25s while not connected
      setInterval(async () => {
        const info = await fetchQr();
        renderInline(inline, info);
      }, 25000);
    }

    // Realtime QR push
    if (W.rt) {
      W.rt.on('engine:qr', (data) => {
        const inlineEl = document.getElementById('qr-canvas');
        const modalEl  = document.getElementById('qr-modal-canvas');
        const info = { state: 'qr_required', qr: data && data.qr };
        if (inlineEl) renderInline(inlineEl, info);
        if (modalEl)  renderInline(modalEl, info);
      });
    }
  });

  W.openQrModal = openQrModal;
})();
