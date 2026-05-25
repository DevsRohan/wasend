/**
 * app.js - bootstraps the page (must be last)
 */
(function () {
  'use strict';
  const W = window.WASEND;
  document.addEventListener('DOMContentLoaded', () => {
    // Start realtime
    if (W.startRealtime) W.startRealtime();

    // Wire up "Show QR" sidebar button
    const showQrBtn = document.getElementById('btn-show-qr');
    if (showQrBtn && W.openQrModal) {
      showQrBtn.addEventListener('click', (e) => { e.preventDefault(); W.openQrModal(); });
    }
  });
})();
