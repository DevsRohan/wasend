'use strict';
const fs = require('fs');
const path = require('path');
const config = require('../config');
const logger = require('../utils/logger');

/**
 * sessionManager - ensures session directories exist + light health checks.
 *
 * whatsapp-web.js handles persistence via LocalAuth({ dataPath }). We just
 * make sure the underlying directory exists and is writable. If it isn't,
 * we log loudly so the operator can see persistence won't survive restarts.
 */
function ensureDirs() {
  const dirs = [config.dataDir, config.sessionDir, config.logsDir];
  for (const d of dirs) {
    try {
      if (!fs.existsSync(d)) fs.mkdirSync(d, { recursive: true });
    } catch (e) {
      logger.warn('session_dir_create_failed', { dir: d, err: e.message });
    }
  }
  if (!isWritable(config.dataDir)) {
    logger.warn('data_dir_not_writable', {
      dir: config.dataDir,
      hint: 'WhatsApp session will NOT persist across container restarts. ' +
            'Enable Hugging Face Persistent Storage on /data to fix.',
    });
  }
}

function isWritable(dir) {
  try {
    if (!fs.existsSync(dir)) fs.mkdirSync(dir, { recursive: true });
    const test = path.join(dir, '.write_test_' + process.pid);
    fs.writeFileSync(test, String(Date.now()));
    fs.unlinkSync(test);
    return true;
  } catch (_) {
    return false;
  }
}

function info() {
  return {
    dataDir:    config.dataDir,
    sessionDir: config.sessionDir,
    writable:   isWritable(config.dataDir),
  };
}

module.exports = { ensureDirs, isWritable, info };
