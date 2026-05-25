/**
 * notifications.js - Toast notifications + sound
 */
(function () {
  'use strict';
  const W = window.WASEND;
  const root = document.getElementById('toast-root');

  function toast(message, kind = 'info', timeout = 4200) {
    if (!root) return;
    const el = document.createElement('div');
    el.className = `toast ${kind} fade-in-up`;
    el.innerHTML = `
      <div class="flex-1">${W.fmt.escape(message)}</div>
      <button class="text-ink-500 hover:text-ink-900" aria-label="dismiss">&times;</button>
    `;
    root.appendChild(el);
    el.querySelector('button').addEventListener('click', () => el.remove());
    setTimeout(() => { el.remove(); }, timeout);
  }

  let _audio = null;
  function getAudio() {
    if (_audio) return _audio;
    try { _audio = new Audio('assets/sounds/notify.mp3'); _audio.volume = 0.4; } catch (e) {}
    return _audio;
  }
  function playNotify() {
    try {
      const a = getAudio();
      if (a) { a.currentTime = 0; a.play().catch(() => {}); }
    } catch (e) {}
  }

  W.toast = toast;
  W.playNotify = playNotify;
})();
