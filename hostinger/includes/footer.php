<?php
$assetVer = $assetVer ?? '1.0.0';
$socketUrl = (string) wasend_setting('socket_url', '');
?>
<!-- Socket.io client -->
<script src="https://cdn.socket.io/4.7.5/socket.io.min.js" crossorigin="anonymous"></script>

<script>
  window.WASEND = window.WASEND || {};
  window.WASEND.csrf      = "<?= e(csrf_token()) ?>";
  window.WASEND.socketUrl = "<?= e($socketUrl) ?>";
  window.WASEND.user      = <?= json_encode(['username' => (string)($user['username'] ?? '')], JSON_UNESCAPED_UNICODE) ?>;
</script>

<script src="assets/js/helpers.js?v=<?= e($assetVer) ?>"></script>
<script src="assets/js/notifications.js?v=<?= e($assetVer) ?>"></script>
<script src="assets/js/modal.js?v=<?= e($assetVer) ?>"></script>
<script src="assets/js/socket.js?v=<?= e($assetVer) ?>"></script>
<script src="assets/js/realtime.js?v=<?= e($assetVer) ?>"></script>
<script src="assets/js/kpi.js?v=<?= e($assetVer) ?>"></script>
<script src="assets/js/qr.js?v=<?= e($assetVer) ?>"></script>
<script src="assets/js/leads.js?v=<?= e($assetVer) ?>"></script>
<script src="assets/js/chat.js?v=<?= e($assetVer) ?>"></script>
<script src="assets/js/details.js?v=<?= e($assetVer) ?>"></script>
<script src="assets/js/campaign.js?v=<?= e($assetVer) ?>"></script>
<script src="assets/js/csv-upload.js?v=<?= e($assetVer) ?>"></script>
<script src="assets/js/settings.js?v=<?= e($assetVer) ?>"></script>
<script src="assets/js/app.js?v=<?= e($assetVer) ?>"></script>
</body>
</html>
