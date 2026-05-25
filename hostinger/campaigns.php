<?php
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
$user = auth_check(false);

$pageTitle = 'Campaigns';
$current   = 'campaigns';
require __DIR__ . '/includes/header.php';
?>
<div class="flex min-h-screen">
  <?php require __DIR__ . '/includes/sidebar.php'; ?>

  <main class="flex-1 px-8 py-8 max-w-[1200px]">
    <header class="mb-6">
      <h1 class="text-2xl font-semibold tracking-tight">Campaigns</h1>
      <p class="text-[13px] text-ink-500 mt-1">Control automated first-outreach. Lead replies stop the queue immediately.</p>
    </header>

    <div class="grid md:grid-cols-3 gap-4 mb-6">
      <div class="rounded-xl border border-surface-border bg-white p-4 shadow-soft">
        <div class="text-[11.5px] text-ink-500">Status</div>
        <div class="mt-1 flex items-center gap-2">
          <span id="campaign-status-dot" class="w-2 h-2 rounded-full bg-ink-300"></span>
          <span id="campaign-status-text" class="text-[15px] font-semibold">—</span>
        </div>
      </div>
      <div class="rounded-xl border border-surface-border bg-white p-4 shadow-soft">
        <div class="text-[11.5px] text-ink-500">Today's Send Quota</div>
        <div class="mt-1 text-[15px] font-semibold"><span data-kpi="sent_today">0</span> / <span id="campaign-daily-limit">—</span></div>
        <div class="mt-2 h-1.5 rounded-full bg-surface-soft overflow-hidden">
          <div id="campaign-progress" class="h-full bg-brand-500 transition-all" style="width:0%"></div>
        </div>
      </div>
      <div class="rounded-xl border border-surface-border bg-white p-4 shadow-soft">
        <div class="text-[11.5px] text-ink-500">Queue</div>
        <div class="mt-1 text-[15px] font-semibold"><span id="queue-pending">0</span> pending</div>
        <div class="text-[11.5px] text-ink-500 mt-1">
          Sent: <span id="queue-sent">0</span> · Failed: <span id="queue-failed">0</span> · Blocked: <span id="queue-blocked">0</span>
        </div>
      </div>
    </div>

    <!-- Controls -->
    <div class="rounded-xl border border-surface-border bg-white p-5 shadow-soft mb-6">
      <h2 class="text-[15px] font-semibold mb-4">Campaign Controls</h2>
      <div class="flex flex-wrap items-center gap-2">
        <button id="btn-campaign-start"  class="px-3.5 py-2 rounded-lg bg-brand-500 hover:bg-brand-600 text-white text-[13px] font-medium shadow-soft">Start</button>
        <button id="btn-campaign-pause"  class="px-3.5 py-2 rounded-lg border border-surface-border bg-white hover:bg-surface-soft text-ink-700 text-[13px] font-medium">Pause</button>
        <button id="btn-campaign-resume" class="px-3.5 py-2 rounded-lg border border-surface-border bg-white hover:bg-surface-soft text-ink-700 text-[13px] font-medium">Resume</button>
        <button id="btn-campaign-stop"   class="px-3.5 py-2 rounded-lg border border-red-200 bg-white hover:bg-red-50 text-red-700 text-[13px] font-medium">Stop</button>
      </div>
      <p class="mt-3 text-[12px] text-ink-500 leading-relaxed">
        Anti-ban pacing is enforced: random delay between <span id="campaign-delay-range">—</span> per send.
        Only the <strong>first outreach message</strong> is automated. Once a lead replies, the queue for that lead is blocked and only manual replies continue.
      </p>
    </div>

    <!-- WhatsApp Engine + QR -->
    <div class="rounded-xl border border-surface-border bg-white p-5 shadow-soft">
      <h2 class="text-[15px] font-semibold mb-1">WhatsApp Engine</h2>
      <p class="text-[12px] text-ink-500">Connect your WhatsApp Web by scanning the QR. Session is persisted on the engine.</p>

      <div class="mt-4 flex flex-col md:flex-row gap-6 items-start">
        <div id="qr-canvas" class="w-[220px] h-[220px] rounded-xl border border-surface-border bg-surface-soft flex items-center justify-center text-[12px] text-ink-500">
          Loading QR…
        </div>
        <div class="flex-1">
          <div class="text-[12.5px]">
            <div class="flex items-center gap-2"><span class="w-2 h-2 rounded-full" id="engine-page-dot"></span><span id="engine-page-state">unknown</span></div>
          </div>
          <div class="mt-3 flex flex-wrap gap-2">
            <button id="btn-refresh-qr"      class="px-3 py-1.5 rounded-md border border-surface-border bg-white hover:bg-surface-soft text-ink-700 text-[12px]">Refresh QR</button>
            <button id="btn-restart-engine"  class="px-3 py-1.5 rounded-md border border-red-200 bg-white hover:bg-red-50 text-red-700 text-[12px]">Restart Engine</button>
          </div>
        </div>
      </div>
    </div>
  </main>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
