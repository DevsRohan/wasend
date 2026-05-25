/**
 * campaign.js - Campaign control buttons (sidebar + campaigns.php)
 */
(function () {
  'use strict';
  const W = window.WASEND;

  async function call(path, ok) {
    try {
      const r = await W.api(path, { method: 'POST' });
      W.toast && W.toast(ok || (r.data && r.data.status) || 'Done', 'success');
      W.refreshKpi && W.refreshKpi();
    } catch (e) {
      W.toast && W.toast(e.message, 'error');
    }
  }

  document.addEventListener('DOMContentLoaded', () => {
    const map = [
      ['btn-start-campaign',   'api/start_campaign.php', 'Campaign started'],
      ['btn-pause-campaign',   'api/pause_campaign.php', 'Campaign paused'],
      ['btn-campaign-start',   'api/start_campaign.php', 'Campaign started'],
      ['btn-campaign-pause',   'api/pause_campaign.php', 'Campaign paused'],
      ['btn-campaign-resume',  'api/resume_campaign.php','Campaign resumed'],
      ['btn-campaign-stop',    'api/stop_campaign.php',  'Campaign stopped'],
      ['btn-restart-engine',   'api/restart_engine.php', 'Engine restart requested'],
    ];
    map.forEach(([id, path, msg]) => {
      const el = document.getElementById(id);
      if (el) el.addEventListener('click', () => call(path, msg));
    });
  });
})();
