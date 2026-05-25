<?php
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
$user = auth_check(false);

$pageTitle = 'Leads';
$current   = 'leads';
require __DIR__ . '/includes/header.php';
?>
<div class="flex min-h-screen">
  <?php require __DIR__ . '/includes/sidebar.php'; ?>

  <main class="flex-1 px-8 py-8 max-w-[1400px]">
    <header class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-2xl font-semibold tracking-tight">Leads</h1>
        <p class="text-[13px] text-ink-500 mt-1">All imported leads with WhatsApp + outreach state.</p>
      </div>
      <div class="flex items-center gap-2">
        <button id="btn-upload-csv-page" class="px-3.5 py-2 rounded-lg bg-brand-500 hover:bg-brand-600 text-white text-[13px] font-medium shadow-soft">+ Import CSV</button>
        <a href="api/export_leads.php" class="px-3.5 py-2 rounded-lg border border-surface-border bg-white hover:bg-surface-soft text-ink-700 text-[13px] font-medium">Export CSV</a>
      </div>
    </header>

    <!-- KPI cards -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
      <div class="rounded-xl border border-surface-border bg-white p-4 shadow-soft">
        <div class="text-[11.5px] text-ink-500">Total Leads</div>
        <div class="text-2xl font-semibold mt-1" data-kpi="total">—</div>
      </div>
      <div class="rounded-xl border border-surface-border bg-white p-4 shadow-soft">
        <div class="text-[11.5px] text-ink-500">Valid WhatsApp</div>
        <div class="text-2xl font-semibold mt-1 text-brand-700" data-kpi="wa_valid">—</div>
      </div>
      <div class="rounded-xl border border-surface-border bg-white p-4 shadow-soft">
        <div class="text-[11.5px] text-ink-500">Replied</div>
        <div class="text-2xl font-semibold mt-1 text-brand-700" data-kpi="replied">—</div>
      </div>
      <div class="rounded-xl border border-surface-border bg-white p-4 shadow-soft">
        <div class="text-[11.5px] text-ink-500">Pending Outreach</div>
        <div class="text-2xl font-semibold mt-1" data-kpi="pending_outreach">—</div>
      </div>
    </div>

    <!-- Filters -->
    <div class="rounded-xl border border-surface-border bg-white p-3 mb-4 flex flex-wrap items-center gap-2">
      <input id="leads-page-search" type="text" placeholder="Search business, phone, city..."
             class="flex-1 min-w-[240px] px-3 py-2 rounded-lg border border-surface-border bg-surface-soft text-[13px] outline-none focus:border-brand-500 focus:ring-4 focus:ring-brand-500/10">
      <select id="filter-ws" class="px-3 py-2 rounded-lg border border-surface-border bg-white text-[13px] outline-none">
        <option value="">All WhatsApp</option>
        <option value="valid">Valid</option>
        <option value="invalid">Invalid</option>
        <option value="not_on_whatsapp">Not on WA</option>
        <option value="pending">Pending</option>
      </select>
      <select id="filter-os" class="px-3 py-2 rounded-lg border border-surface-border bg-white text-[13px] outline-none">
        <option value="">All Outreach</option>
        <option value="pending">Pending</option>
        <option value="sent">Sent</option>
        <option value="replied">Replied</option>
        <option value="failed">Failed</option>
      </select>
      <select id="filter-pt" class="px-3 py-2 rounded-lg border border-surface-border bg-white text-[13px] outline-none">
        <option value="">All Pitch Types</option>
        <option value="A">Type A (Has Website)</option>
        <option value="B">Type B (No Website)</option>
      </select>
    </div>

    <!-- Table -->
    <div class="rounded-xl border border-surface-border bg-white overflow-hidden shadow-soft">
      <div class="overflow-x-auto">
        <table class="w-full text-[13px]">
          <thead class="bg-surface-soft border-b border-surface-border">
            <tr class="text-ink-500 text-left text-[11.5px] uppercase tracking-wider">
              <th class="px-4 py-3 font-medium">Business</th>
              <th class="px-4 py-3 font-medium">Location</th>
              <th class="px-4 py-3 font-medium">Phone</th>
              <th class="px-4 py-3 font-medium">Rating</th>
              <th class="px-4 py-3 font-medium">WhatsApp</th>
              <th class="px-4 py-3 font-medium">Outreach</th>
              <th class="px-4 py-3 font-medium">Pitch</th>
              <th class="px-4 py-3 font-medium text-right"></th>
            </tr>
          </thead>
          <tbody id="leads-table-body" class="divide-y divide-surface-border">
            <tr><td colspan="8" class="px-4 py-10 text-center text-ink-500">Loading...</td></tr>
          </tbody>
        </table>
      </div>
      <div class="px-4 py-3 border-t border-surface-border text-[12px] flex items-center justify-between bg-surface-soft">
        <span id="leads-page-count">—</span>
        <div class="flex items-center gap-2">
          <button id="leads-prev" class="px-2.5 py-1 rounded-md border border-surface-border bg-white hover:bg-white/80 text-ink-700 text-[12px]">Prev</button>
          <button id="leads-next" class="px-2.5 py-1 rounded-md border border-surface-border bg-white hover:bg-white/80 text-ink-700 text-[12px]">Next</button>
        </div>
      </div>
    </div>
  </main>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
