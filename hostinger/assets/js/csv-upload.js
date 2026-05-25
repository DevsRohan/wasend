/**
 * csv-upload.js - CSV upload modal
 */
(function () {
  'use strict';
  const W = window.WASEND;

  function openUploadModal() {
    const html = `
      <div class="p-6">
        <div class="flex items-center justify-between mb-4">
          <div>
            <h3 class="text-[15px] font-semibold tracking-tight">Import Leads CSV</h3>
            <p class="text-[11.5px] text-ink-500 mt-0.5">Columns: Business Name, Phone, Address, Website, Rating, Reviews, Status (any subset)</p>
          </div>
          <button data-close class="text-ink-500 hover:text-ink-900 text-lg leading-none">&times;</button>
        </div>

        <label class="block w-full rounded-xl border-2 border-dashed border-surface-border hover:border-brand-500 transition-colors cursor-pointer text-center py-10">
          <input id="csv-file" type="file" accept=".csv,text/csv" class="hidden">
          <div class="text-3xl">📄</div>
          <div class="mt-2 text-[13px] font-medium">Click to select CSV</div>
          <div class="text-[11.5px] text-ink-500 mt-0.5">or drag & drop here</div>
          <div id="csv-name" class="mt-2 text-[12px] text-brand-700 hidden"></div>
        </label>

        <div id="csv-progress" class="hidden mt-4">
          <div class="text-[12px] text-ink-500 mb-1">Uploading…</div>
          <div class="h-1.5 rounded-full bg-surface-soft overflow-hidden"><div id="csv-bar" class="h-full bg-brand-500 transition-all" style="width:0%"></div></div>
        </div>

        <div id="csv-result" class="hidden mt-4 p-3 rounded-lg bg-emerald-50 border border-emerald-100 text-[12.5px] text-brand-800"></div>

        <div class="mt-5 flex items-center justify-end gap-2">
          <button data-close class="px-3 py-2 rounded-lg border border-surface-border bg-white hover:bg-surface-soft text-ink-700 text-[12.5px]">Close</button>
          <button id="csv-upload-btn" class="px-3 py-2 rounded-lg bg-brand-500 hover:bg-brand-600 text-white text-[12.5px]" disabled>Upload</button>
        </div>
      </div>`;

    W.openModal(html, {
      onMount: (card) => {
        const file = card.querySelector('#csv-file');
        const name = card.querySelector('#csv-name');
        const btn  = card.querySelector('#csv-upload-btn');
        const prog = card.querySelector('#csv-progress');
        const bar  = card.querySelector('#csv-bar');
        const res  = card.querySelector('#csv-result');

        file.addEventListener('change', () => {
          if (!file.files[0]) return;
          name.classList.remove('hidden');
          name.textContent = file.files[0].name;
          btn.disabled = false;
        });

        // drag&drop
        const drop = card.querySelector('label');
        ['dragover', 'dragleave', 'drop'].forEach(evt => {
          drop.addEventListener(evt, (e) => {
            e.preventDefault();
            if (evt === 'drop' && e.dataTransfer.files[0]) {
              file.files = e.dataTransfer.files;
              file.dispatchEvent(new Event('change'));
            }
          });
        });

        btn.addEventListener('click', async () => {
          if (!file.files[0]) return;
          btn.disabled = true; prog.classList.remove('hidden'); bar.style.width = '15%';

          const fd = new FormData();
          fd.append('file', file.files[0]);

          try {
            const xhr = new XMLHttpRequest();
            xhr.open('POST', 'api/upload_csv.php');
            xhr.setRequestHeader('X-CSRF-Token', W.csrf || '');
            xhr.upload.onprogress = (e) => {
              if (e.lengthComputable) bar.style.width = (Math.min(95, (e.loaded / e.total) * 90) + 5) + '%';
            };
            xhr.onload = () => {
              bar.style.width = '100%';
              try {
                const data = JSON.parse(xhr.responseText);
                if (data && data.ok) {
                  const d = data.data || {};
                  res.classList.remove('hidden');
                  res.innerHTML = `Imported <strong>${d.imported}</strong> new leads · <strong>${d.duplicates}</strong> duplicates · <strong>${d.parsed}</strong> rows parsed`;
                  W.toast && W.toast(`Imported ${d.imported} new leads`, 'success');
                  W.reloadLeadsList && W.reloadLeadsList();
                  W.refreshKpi && W.refreshKpi();
                } else {
                  res.classList.remove('hidden');
                  res.classList.replace('bg-emerald-50','bg-red-50');
                  res.classList.replace('border-emerald-100','border-red-100');
                  res.classList.replace('text-brand-800','text-red-700');
                  res.textContent = (data && data.error) ? ('Import failed: ' + data.error) : 'Import failed.';
                }
              } catch (e) {
                res.classList.remove('hidden');
                res.textContent = 'Server error.';
              }
            };
            xhr.onerror = () => {
              res.classList.remove('hidden');
              res.textContent = 'Network error.';
            };
            xhr.send(fd);
          } catch (e) {
            W.toast && W.toast(e.message, 'error');
          }
        });
      }
    });
  }

  document.addEventListener('DOMContentLoaded', () => {
    ['btn-upload-csv', 'btn-upload-csv-page'].forEach(id => {
      const el = document.getElementById(id);
      if (el) el.addEventListener('click', openUploadModal);
    });
  });

  W.openCsvUpload = openUploadModal;
})();
