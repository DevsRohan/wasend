<?php
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
$user = auth_check(false);

$pageTitle = 'Activity';
$current   = 'logs';
require __DIR__ . '/includes/header.php';
?>
<div class="flex min-h-screen">
  <?php require __DIR__ . '/includes/sidebar.php'; ?>

  <main class="flex-1 px-8 py-8 max-w-[1300px]">
    <header class="mb-6">
      <h1 class="text-2xl font-semibold tracking-tight">Activity Log</h1>
      <p class="text-[13px] text-ink-500 mt-1">System audit trail. Filter by level / category.</p>
    </header>

    <div class="rounded-xl border border-surface-border bg-white shadow-soft">
      <div class="px-4 py-3 border-b border-surface-border flex flex-wrap items-center gap-2">
        <select id="log-level" class="px-3 py-2 rounded-lg border border-surface-border bg-surface-soft text-[13px] outline-none">
          <option value="">All levels</option>
          <option value="info">Info</option>
          <option value="warning">Warning</option>
          <option value="error">Error</option>
          <option value="critical">Critical</option>
          <option value="debug">Debug</option>
        </select>
        <select id="log-category" class="px-3 py-2 rounded-lg border border-surface-border bg-surface-soft text-[13px] outline-none">
          <option value="">All categories</option>
        </select>
        <button id="log-refresh" class="ml-auto px-3 py-2 rounded-lg bg-brand-500 hover:bg-brand-600 text-white text-[12.5px] font-medium">Refresh</button>
      </div>
      <div class="overflow-x-auto">
        <table class="w-full text-[12.5px]">
          <thead class="bg-surface-soft text-ink-500 text-left text-[11.5px] uppercase tracking-wider">
            <tr>
              <th class="px-4 py-3 font-medium">When</th>
              <th class="px-4 py-3 font-medium">Level</th>
              <th class="px-4 py-3 font-medium">Category</th>
              <th class="px-4 py-3 font-medium">Action</th>
              <th class="px-4 py-3 font-medium">Message</th>
            </tr>
          </thead>
          <tbody id="logs-body" class="divide-y divide-surface-border">
            <tr><td colspan="5" class="px-4 py-10 text-center text-ink-500">Loading…</td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </main>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
