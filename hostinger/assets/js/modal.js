/**
 * modal.js - Drawer + modal engine
 */
(function () {
  'use strict';
  const W = window.WASEND;
  const root = document.getElementById('modal-root');

  function openDrawer(html, opts = {}) {
    closeAll();
    const backdrop = document.createElement('div');
    backdrop.className = 'drawer-backdrop';
    const panel = document.createElement('div');
    panel.className = 'drawer-panel';
    panel.innerHTML = html;
    root.appendChild(backdrop);
    root.appendChild(panel);
    requestAnimationFrame(() => {
      backdrop.classList.add('open');
      panel.classList.add('open');
    });
    backdrop.addEventListener('click', closeAll);
    panel.querySelectorAll('[data-close]').forEach(b => b.addEventListener('click', closeAll));
    if (typeof opts.onMount === 'function') opts.onMount(panel);
    return panel;
  }

  function openModal(html, opts = {}) {
    closeAll();
    const backdrop = document.createElement('div');
    backdrop.className = 'modal-backdrop';
    const card = document.createElement('div');
    card.className = 'modal-card fade-in-up';
    card.innerHTML = html;
    backdrop.appendChild(card);
    root.appendChild(backdrop);
    requestAnimationFrame(() => backdrop.classList.add('open'));
    backdrop.addEventListener('click', (e) => { if (e.target === backdrop) closeAll(); });
    card.querySelectorAll('[data-close]').forEach(b => b.addEventListener('click', closeAll));
    if (typeof opts.onMount === 'function') opts.onMount(card);
    return card;
  }

  function closeAll() {
    root.querySelectorAll('.drawer-backdrop, .drawer-panel, .modal-backdrop').forEach(el => {
      el.classList.remove('open');
      setTimeout(() => el.remove(), 220);
    });
  }

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') closeAll();
  });

  W.openDrawer = openDrawer;
  W.openModal  = openModal;
  W.closeModal = closeAll;
})();
