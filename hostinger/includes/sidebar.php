<?php
$current   = $current ?? 'dashboard';
$brandName = (string) wasend_setting('brand_name', 'Wasend');
$tagline   = (string) wasend_setting('brand_tagline', 'WhatsApp CRM');
?>
<aside class="w-[280px] shrink-0 h-screen sticky top-0 bg-white border-r border-surface-border flex flex-col">
  <!-- Brand -->
  <div class="px-5 pt-5 pb-4 border-b border-surface-border">
    <div class="flex items-center gap-3">
      <div class="w-9 h-9 rounded-xl bg-brand-500 text-white flex items-center justify-center font-semibold shadow-soft">
        <svg viewBox="0 0 24 24" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7l9 6 9-6M3 17l9-6 9 6"/></svg>
      </div>
      <div>
        <div class="text-[15px] font-semibold tracking-tight"><?= e($brandName) ?></div>
        <div class="text-[11px] text-ink-500 -mt-0.5"><?= e($tagline) ?></div>
      </div>
    </div>
  </div>

  <!-- Engine status -->
  <div class="px-5 py-4 border-b border-surface-border">
    <div class="text-[11px] uppercase tracking-wider text-ink-500 mb-2">WhatsApp Engine</div>
    <div id="engine-status" class="flex items-center gap-2.5 px-3 py-2.5 rounded-lg bg-surface-soft border border-surface-border">
      <span class="relative flex h-2.5 w-2.5">
        <span id="engine-dot-pulse" class="animate-ping absolute inline-flex h-full w-full rounded-full bg-brand-400 opacity-60"></span>
        <span id="engine-dot" class="relative inline-flex rounded-full h-2.5 w-2.5 bg-ink-300"></span>
      </span>
      <span id="engine-status-text" class="text-[13px] font-medium text-ink-700">Connecting...</span>
    </div>
    <button id="btn-show-qr" class="mt-2 w-full text-[12px] text-brand-700 hover:text-brand-800 font-medium hidden">Show QR Code →</button>
  </div>

  <!-- Nav -->
  <nav class="px-3 py-4 space-y-1 flex-1 overflow-y-auto">
    <?php
      $items = [
        ['key'=>'dashboard',  'label'=>'Dashboard',  'href'=>'dashboard.php',  'icon'=>'home'],
        ['key'=>'leads',      'label'=>'Leads',      'href'=>'leads.php',      'icon'=>'users'],
        ['key'=>'campaigns',  'label'=>'Campaigns',  'href'=>'campaigns.php',  'icon'=>'send'],
        ['key'=>'logs',       'label'=>'Activity',   'href'=>'logs.php',       'icon'=>'activity'],
        ['key'=>'settings',   'label'=>'Settings',   'href'=>'settings.php',   'icon'=>'settings'],
      ];
      $icons = [
        'home'     => '<path d="M3 12l9-9 9 9"/><path d="M5 10v10h14V10"/>',
        'users'    => '<circle cx="9" cy="7" r="4"/><path d="M3 21v-1a6 6 0 0 1 6-6h0a6 6 0 0 1 6 6v1"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/><path d="M21 21v-1a6 6 0 0 0-4-5.65"/>',
        'send'     => '<path d="M22 2L11 13"/><path d="M22 2l-7 20-4-9-9-4 20-7z"/>',
        'activity' => '<path d="M22 12h-4l-3 9L9 3l-3 9H2"/>',
        'settings' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.6 1.65 1.65 0 0 0 10 3.09V3a2 2 0 1 1 4 0v.09A1.65 1.65 0 0 0 15 4.6a1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9c.18.4.27.84.27 1.29 0 .45-.09.89-.27 1.29z"/>',
      ];
      foreach ($items as $it):
        $active = $current === $it['key'];
        $cls    = $active
          ? 'bg-brand-50 text-brand-800 border-brand-100'
          : 'text-ink-700 hover:bg-surface-soft border-transparent';
    ?>
      <a href="<?= e($it['href']) ?>" class="group flex items-center gap-3 px-3 py-2.5 rounded-lg border <?= $cls ?> transition-colors text-[13.5px] font-medium">
        <span class="<?= $active ? 'text-brand-600' : 'text-ink-500 group-hover:text-ink-700' ?>">
          <svg viewBox="0 0 24 24" class="w-[18px] h-[18px]" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><?= $icons[$it['icon']] ?></svg>
        </span>
        <span><?= e($it['label']) ?></span>
        <?php if ($active): ?><span class="ml-auto w-1 h-5 rounded-full bg-brand-500"></span><?php endif; ?>
      </a>
    <?php endforeach; ?>
  </nav>

  <!-- KPIs -->
  <div class="px-5 py-4 border-t border-surface-border space-y-3">
    <div class="text-[11px] uppercase tracking-wider text-ink-500">Today</div>
    <div class="grid grid-cols-2 gap-2.5">
      <div class="rounded-lg border border-surface-border p-2.5 bg-surface-soft">
        <div class="text-[11px] text-ink-500">Sent</div>
        <div class="text-lg font-semibold text-ink-900" data-kpi="sent_today">0</div>
      </div>
      <div class="rounded-lg border border-surface-border p-2.5 bg-surface-soft">
        <div class="text-[11px] text-ink-500">Replied</div>
        <div class="text-lg font-semibold text-brand-700" data-kpi="replied">0</div>
      </div>
      <div class="rounded-lg border border-surface-border p-2.5 bg-surface-soft">
        <div class="text-[11px] text-ink-500">Pending</div>
        <div class="text-lg font-semibold text-ink-900" data-kpi="pending_outreach">0</div>
      </div>
      <div class="rounded-lg border border-surface-border p-2.5 bg-surface-soft">
        <div class="text-[11px] text-ink-500">Unread</div>
        <div class="text-lg font-semibold text-ink-900" data-kpi="unread_threads">0</div>
      </div>
    </div>
  </div>

  <!-- Quick actions -->
  <div class="px-5 py-4 border-t border-surface-border">
    <div class="grid grid-cols-2 gap-2">
      <button id="btn-start-campaign" class="px-3 py-2 rounded-lg bg-brand-500 hover:bg-brand-600 text-white text-[12.5px] font-medium shadow-soft">Start</button>
      <button id="btn-pause-campaign" class="px-3 py-2 rounded-lg border border-surface-border bg-white hover:bg-surface-soft text-ink-700 text-[12.5px] font-medium">Pause</button>
    </div>
    <div id="campaign-state" class="mt-2 text-center text-[11px] text-ink-500">Campaign idle</div>

    <!-- Manual triggers (no cron required) -->
    <div class="mt-3 pt-3 border-t border-surface-border space-y-1.5">
      <div class="text-[10px] uppercase tracking-wider text-ink-500 mb-1">Run Now</div>
      <button id="btn-validate-now"  class="w-full px-2.5 py-1.5 rounded-md border border-surface-border bg-white hover:bg-surface-soft text-ink-700 text-[11.5px] text-left flex items-center justify-between">
        <span>⚡ Validate Pending</span>
        <span class="text-[10px] text-ink-500" id="validate-pending-count">—</span>
      </button>
      <button id="btn-send-next"     class="w-full px-2.5 py-1.5 rounded-md border border-surface-border bg-white hover:bg-surface-soft text-ink-700 text-[11.5px] text-left">
        ⚡ Send Next Campaign Msg
      </button>
      <button id="btn-webhook-diag"  class="w-full px-2.5 py-1.5 rounded-md border border-surface-border bg-white hover:bg-surface-soft text-ink-700 text-[11.5px] text-left flex items-center justify-between">
        <span>🩺 Webhook Diagnostic</span>
        <span class="text-[10px]" id="webhook-health-dot">⚪</span>
      </button>
    </div>
  </div>

  <!-- User -->
  <div class="px-5 py-4 border-t border-surface-border flex items-center justify-between">
    <div class="flex items-center gap-2">
      <div class="w-8 h-8 rounded-full bg-brand-100 text-brand-700 flex items-center justify-center text-[12px] font-semibold">
        <?= e(strtoupper(substr((string)($user['username'] ?? 'U'), 0, 1))) ?>
      </div>
      <div class="text-[12.5px]">
        <div class="font-medium text-ink-900"><?= e((string)($user['username'] ?? 'admin')) ?></div>
        <div class="text-[11px] text-ink-500"><?= e((string)($user['role'] ?? 'admin')) ?></div>
      </div>
    </div>
    <a href="logout.php" class="text-[12px] text-ink-500 hover:text-ink-700">Logout</a>
  </div>
</aside>
