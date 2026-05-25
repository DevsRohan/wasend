<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/auth.php';

$pageTitle  = $pageTitle  ?? 'Dashboard';
$brandName  = (string) wasend_setting('brand_name', 'Wasend');
$tagline    = (string) wasend_setting('brand_tagline', 'WhatsApp CRM + Cold Outreach OS');
$socketUrl  = (string) wasend_setting('socket_url', '');
$user       = $user ?? auth_user();
$csrf       = csrf_token();
$assetVer   = '1.0.0';
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="<?= e($csrf) ?>">
<meta name="socket-url" content="<?= e($socketUrl) ?>">
<title><?= e($pageTitle) ?> · <?= e($brandName) ?></title>
<link rel="icon" type="image/svg+xml" href="assets/img/logo-mark.svg">
<script src="https://cdn.tailwindcss.com"></script>
<script>
  tailwind.config = {
    theme: {
      extend: {
        colors: {
          brand: {
            50:  '#ECFDF5',
            100: '#D1FAE5',
            200: '#A7F3D0',
            300: '#6EE7B7',
            400: '#34D399',
            500: '#10B981',
            600: '#059669',
            700: '#047857',
            800: '#065F46',
            900: '#064E3B'
          },
          ink: {
            900: '#0F172A',
            700: '#334155',
            500: '#64748B',
            300: '#CBD5E1',
            100: '#F1F5F9'
          },
          surface: {
            DEFAULT: '#FFFFFF',
            soft:    '#F8FAFB',
            border:  '#E5E7EB'
          }
        },
        fontFamily: {
          sans: ['Inter','ui-sans-serif','system-ui','sans-serif'],
          mono: ['ui-monospace','SFMono-Regular','Menlo','monospace']
        },
        boxShadow: {
          'soft':   '0 1px 2px rgba(15,23,42,0.04), 0 1px 3px rgba(15,23,42,0.06)',
          'pop':    '0 12px 30px -10px rgba(15,23,42,0.12)',
          'ring-brand': '0 0 0 4px rgba(16,185,129,0.12)'
        }
      }
    }
  };
</script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/main.css?v=<?= e($assetVer) ?>">
<link rel="stylesheet" href="assets/css/components.css?v=<?= e($assetVer) ?>">
<link rel="stylesheet" href="assets/css/chat.css?v=<?= e($assetVer) ?>">
<link rel="stylesheet" href="assets/css/animations.css?v=<?= e($assetVer) ?>">
<link rel="stylesheet" href="assets/css/skeleton.css?v=<?= e($assetVer) ?>">
</head>
<body class="bg-surface-soft text-ink-900 font-sans antialiased">
<div id="toast-root" class="fixed top-5 right-5 z-[100] flex flex-col gap-2 pointer-events-none"></div>
<div id="modal-root"></div>
