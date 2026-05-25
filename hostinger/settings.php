<?php
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
$user = auth_check(false);

$pageTitle = 'Settings';
$current   = 'settings';
require __DIR__ . '/includes/header.php';
?>
<div class="flex min-h-screen">
  <?php require __DIR__ . '/includes/sidebar.php'; ?>

  <main class="flex-1 px-8 py-8 max-w-[1100px]">
    <header class="mb-6">
      <h1 class="text-2xl font-semibold tracking-tight">Settings</h1>
      <p class="text-[13px] text-ink-500 mt-1">Configure infrastructure, AI, campaign pacing, security, branding.</p>
    </header>

    <div class="rounded-xl border border-surface-border bg-white shadow-soft">
      <div id="settings-tabs" class="flex items-center gap-1 px-4 pt-3 border-b border-surface-border overflow-x-auto">
        <button data-cat="connection" class="cat-tab active-tab px-3 py-2 text-[12.5px] font-medium text-ink-700">Connection</button>
        <button data-cat="ai"         class="cat-tab px-3 py-2 text-[12.5px] font-medium text-ink-500">AI</button>
        <button data-cat="campaign"   class="cat-tab px-3 py-2 text-[12.5px] font-medium text-ink-500">Campaign</button>
        <button data-cat="ui"         class="cat-tab px-3 py-2 text-[12.5px] font-medium text-ink-500">UI</button>
        <button data-cat="security"   class="cat-tab px-3 py-2 text-[12.5px] font-medium text-ink-500">Security</button>
        <button data-cat="branding"   class="cat-tab px-3 py-2 text-[12.5px] font-medium text-ink-500">Branding</button>
        <button data-cat="features"   class="cat-tab px-3 py-2 text-[12.5px] font-medium text-ink-500">Features</button>
      </div>

      <form id="settings-form" class="p-6">
        <div id="settings-fields" class="space-y-4">
          <div class="text-center text-ink-500 text-[13px] py-8">Loading settings…</div>
        </div>

        <div class="mt-8 pt-5 border-t border-surface-border flex items-center justify-between">
          <p class="text-[11.5px] text-ink-500">Secrets (API keys, webhook secret) are encrypted at rest with AES-256-GCM.</p>
          <div class="flex items-center gap-2">
            <button type="button" id="btn-settings-cancel" class="px-3.5 py-2 rounded-lg border border-surface-border bg-white hover:bg-surface-soft text-ink-700 text-[13px] font-medium">Reset</button>
            <button type="submit" class="px-4 py-2 rounded-lg bg-brand-500 hover:bg-brand-600 text-white text-[13px] font-medium shadow-soft">Save Changes</button>
          </div>
        </div>
      </form>
    </div>
  </main>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
