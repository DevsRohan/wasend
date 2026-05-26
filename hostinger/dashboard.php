<?php
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
$user = auth_check(false);

$pageTitle = 'Dashboard';
$current   = 'dashboard';

require __DIR__ . '/includes/header.php';
?>
<div class="flex min-h-screen">

  <?php require __DIR__ . '/includes/sidebar.php'; ?>

  <!-- MIDDLE COLUMN -->
  <section class="w-[400px] shrink-0 h-screen sticky top-0 border-r border-surface-border bg-white flex flex-col">
    <div class="px-5 pt-5 pb-3 border-b border-surface-border">
      <div class="flex items-center justify-between mb-3">
        <h2 class="text-[15px] font-semibold tracking-tight">Conversations</h2>
        <div class="flex items-center gap-1.5">
          <button id="btn-upload-csv" class="text-[11px] px-2.5 py-1 rounded-md border border-surface-border bg-white hover:bg-surface-soft text-ink-700 font-medium" title="Import CSV">+ CSV</button>
          <button id="btn-sync-now" class="text-[11px] px-2.5 py-1 rounded-md border border-surface-border bg-white hover:bg-surface-soft text-ink-700" title="Force sync now">⟳</button>
          <button id="btn-refresh-leads" class="text-[11px] px-2.5 py-1 rounded-md border border-surface-border bg-white hover:bg-surface-soft text-ink-700" title="Reload list">↻</button>
        </div>
      </div>

      <div class="relative">
        <input id="leads-search" type="text" placeholder="Search business, phone, city..."
               class="w-full pl-9 pr-3 py-2 rounded-lg border border-surface-border bg-surface-soft focus:bg-white focus:border-brand-500 focus:ring-4 focus:ring-brand-500/10 outline-none text-[13px]">
        <span class="absolute left-3 top-2.5 text-ink-500">
          <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
        </span>
      </div>

      <div id="leads-tabs" class="mt-3 flex items-center gap-1 text-[12px] overflow-x-auto">
        <button data-tab="" class="active-tab tab px-2.5 py-1 rounded-md text-ink-700 font-medium">All</button>
        <button data-tab="pending"  class="tab px-2.5 py-1 rounded-md text-ink-500">Pending</button>
        <button data-tab="sent"     class="tab px-2.5 py-1 rounded-md text-ink-500">Sent</button>
        <button data-tab="replied"  class="tab px-2.5 py-1 rounded-md text-ink-500">Replied</button>
        <button data-tab="invalid"  class="tab px-2.5 py-1 rounded-md text-ink-500">Invalid</button>
      </div>
    </div>

    <div id="leads-list" class="flex-1 overflow-y-auto">
      <div class="p-6 text-center text-ink-500 text-sm">Loading leads...</div>
    </div>

    <div class="px-4 py-2 border-t border-surface-border text-[11px] text-ink-500 flex items-center justify-between">
      <span id="leads-count">—</span>
      <span class="flex items-center gap-1.5"><span class="w-1.5 h-1.5 rounded-full bg-brand-500 animate-pulse"></span> Live</span>
    </div>
  </section>

  <!-- RIGHT COLUMN: Chat -->
  <section class="flex-1 h-screen flex flex-col bg-surface-soft" id="chat-pane">
    <div id="chat-empty" class="flex-1 flex items-center justify-center">
      <div class="text-center max-w-sm px-6">
        <div class="w-16 h-16 rounded-2xl bg-white border border-surface-border mx-auto flex items-center justify-center shadow-soft">
          <svg viewBox="0 0 24 24" class="w-7 h-7 text-brand-500" fill="none" stroke="currentColor" stroke-width="1.6">
            <path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 1 1-3.6-7.2L21 3v6h-6"/>
          </svg>
        </div>
        <h3 class="mt-4 text-[15px] font-semibold">Pick a conversation</h3>
        <p class="text-[13px] text-ink-500 mt-1">Select a lead from the left to view chat history and continue manually.</p>
      </div>
    </div>

    <div id="chat-thread" class="hidden flex-col h-full">
      <!-- Header -->
      <header class="px-5 py-3 border-b border-surface-border bg-white flex items-center justify-between">
        <div class="flex items-center gap-3">
          <div id="chat-avatar" class="w-10 h-10 rounded-full bg-brand-100 text-brand-700 flex items-center justify-center font-semibold">B</div>
          <div>
            <div id="chat-name" class="text-[14px] font-semibold tracking-tight">Lead</div>
            <div id="chat-sub"  class="text-[11.5px] text-ink-500">—</div>
          </div>
        </div>
        <div class="flex items-center gap-1.5">
          <button id="btn-preview-msg"  class="text-[11.5px] px-2.5 py-1.5 rounded-md border border-surface-border bg-white hover:bg-surface-soft text-ink-700">AI Preview</button>
          <button id="btn-check-number" class="text-[11.5px] px-2.5 py-1.5 rounded-md border border-surface-border bg-white hover:bg-surface-soft text-ink-700">Check WA</button>
          <button id="btn-get-details"  class="text-[11.5px] px-2.5 py-1.5 rounded-md bg-brand-500 hover:bg-brand-600 text-white">Get Details</button>
        </div>
      </header>

      <!-- AI Insight strip -->
      <div id="ai-insight" class="px-5 py-2.5 bg-emerald-50/70 border-b border-emerald-100 text-[11.5px] text-brand-800 flex flex-wrap items-center gap-3">
        <span class="font-medium">AI Insight:</span>
        <span data-insight="pitch">—</span>
        <span class="text-emerald-300">·</span>
        <span data-insight="lang">—</span>
        <span class="text-emerald-300">·</span>
        <span data-insight="services">—</span>
      </div>

      <!-- Messages -->
      <div id="messages-area" class="flex-1 overflow-y-auto px-5 py-5 space-y-3"></div>

      <!-- Composer -->
      <div class="border-t border-surface-border bg-white px-4 py-3">
        <div class="flex items-end gap-3">
          <textarea id="composer-input" rows="1" placeholder="Type a manual reply..."
                    class="flex-1 resize-none px-3.5 py-2.5 rounded-lg border border-surface-border bg-surface-soft focus:bg-white focus:border-brand-500 focus:ring-4 focus:ring-brand-500/10 outline-none text-[13.5px] max-h-40"></textarea>
          <button id="composer-send"
                  class="px-4 py-2.5 rounded-lg bg-brand-500 hover:bg-brand-600 text-white text-[13px] font-medium shadow-soft">
            Send
          </button>
        </div>
        <div class="mt-1.5 text-[10.5px] text-ink-500">First-outreach is automated. After lead replies, you take over manually.</div>
      </div>
    </div>
  </section>

</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
