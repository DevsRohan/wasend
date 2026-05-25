<?php
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = clean_str($_POST['username'] ?? '', 64);
    $password = (string) ($_POST['password'] ?? '');
    $token    = $_POST['csrf_token'] ?? '';

    if (!csrf_verify($token)) {
        $error = 'Session expired. Please try again.';
    } elseif ($username === '' || $password === '') {
        $error = 'Please enter username and password.';
    } else {
        $u = auth_login($username, $password);
        if ($u) {
            header('Location: dashboard.php');
            exit;
        }
        $error = 'Invalid credentials.';
        wasend_log('warning', 'auth', 'login_failed', ['username' => $username]);
    }
}

$brandName = (string) wasend_setting('brand_name', 'Wasend');
$tagline   = (string) wasend_setting('brand_tagline', 'WhatsApp CRM + Cold Outreach OS');
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Sign in · <?= e($brandName) ?></title>
<script src="https://cdn.tailwindcss.com"></script>
<script>
  tailwind.config = { theme: { extend: { colors: {
    brand: {50:'#ECFDF5',100:'#D1FAE5',400:'#34D399',500:'#10B981',600:'#059669',700:'#047857',800:'#065F46'},
    ink:   {900:'#0F172A',700:'#334155',500:'#64748B',300:'#CBD5E1',100:'#F1F5F9'}
  }}}};
</script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style> body { font-family: Inter, ui-sans-serif, system-ui, sans-serif; } </style>
</head>
<body class="min-h-screen bg-gradient-to-br from-white via-emerald-50 to-white text-ink-900 antialiased">

<div class="min-h-screen flex">

  <!-- Left: form -->
  <div class="flex-1 flex flex-col items-center justify-center px-6 py-12">
    <div class="w-full max-w-md">

      <div class="flex items-center gap-3 mb-10">
        <div class="w-10 h-10 rounded-xl bg-brand-500 text-white flex items-center justify-center font-semibold">
          <svg viewBox="0 0 24 24" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3 7l9 6 9-6M3 17l9-6 9 6"/>
          </svg>
        </div>
        <div>
          <div class="text-lg font-semibold tracking-tight"><?= e($brandName) ?></div>
          <div class="text-xs text-ink-500 -mt-0.5"><?= e($tagline) ?></div>
        </div>
      </div>

      <h1 class="text-2xl font-semibold tracking-tight">Welcome back</h1>
      <p class="text-sm text-ink-500 mt-1">Sign in to your dashboard.</p>

      <?php if ($error): ?>
        <div class="mt-6 p-3 rounded-lg bg-red-50 border border-red-100 text-sm text-red-700">
          <?= e($error) ?>
        </div>
      <?php endif; ?>

      <form method="POST" class="mt-6 space-y-4">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

        <div>
          <label class="block text-xs font-medium text-ink-700 mb-1.5">Username</label>
          <input name="username" type="text" autocomplete="username" required
                 class="w-full px-3.5 py-2.5 rounded-lg border border-ink-300 focus:border-brand-500 focus:ring-4 focus:ring-brand-500/10 outline-none text-sm"
                 placeholder="admin">
        </div>

        <div>
          <label class="block text-xs font-medium text-ink-700 mb-1.5">Password</label>
          <input name="password" type="password" autocomplete="current-password" required
                 class="w-full px-3.5 py-2.5 rounded-lg border border-ink-300 focus:border-brand-500 focus:ring-4 focus:ring-brand-500/10 outline-none text-sm"
                 placeholder="••••••••">
        </div>

        <button type="submit"
                class="w-full px-4 py-2.5 rounded-lg bg-brand-500 hover:bg-brand-600 text-white text-sm font-medium shadow-sm transition-colors">
          Sign in
        </button>
      </form>

      <p class="mt-8 text-[11px] text-ink-500 leading-relaxed">
        Default admin: <span class="font-mono">admin / ChangeMe@123</span>. Change immediately after first login.
      </p>
    </div>
  </div>

  <!-- Right: brand panel -->
  <div class="hidden lg:flex flex-1 items-center justify-center bg-white border-l border-emerald-100">
    <div class="max-w-md p-10">
      <div class="rounded-2xl border border-emerald-100 bg-gradient-to-br from-emerald-50 to-white p-8 shadow-sm">
        <div class="flex items-center gap-2 text-brand-700 text-xs font-medium uppercase tracking-wider">
          <span class="w-1.5 h-1.5 rounded-full bg-brand-500"></span> Realtime
        </div>
        <h2 class="mt-3 text-2xl font-semibold tracking-tight text-ink-900">
          Premium WhatsApp CRM for serious local outreach.
        </h2>
        <p class="mt-3 text-sm text-ink-500 leading-relaxed">
          AI-personalized first messages. Anti-ban pacing. Manual takeover after reply.
          Built for safe, scalable outreach to local businesses.
        </p>
        <ul class="mt-6 space-y-2.5 text-sm text-ink-700">
          <li class="flex items-center gap-2.5"><span class="w-1.5 h-1.5 rounded-full bg-brand-500"></span> CSV import + WhatsApp validation</li>
          <li class="flex items-center gap-2.5"><span class="w-1.5 h-1.5 rounded-full bg-brand-500"></span> Groq AI handcrafted messages</li>
          <li class="flex items-center gap-2.5"><span class="w-1.5 h-1.5 rounded-full bg-brand-500"></span> Realtime dashboard, manual continuation</li>
        </ul>
      </div>
    </div>
  </div>

</div>
</body>
</html>
