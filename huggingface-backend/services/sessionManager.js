'use strict';
const fs = require('fs');
const path = require('path');
const config = require('../config');
const logger = require('../utils/logger');

/**
 * sessionManager - ensures session directories exist + light health checks.
 * whatsapp-web.js handles persistence via LocalAuth({ dataPath }).
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
}

function isWritable(dir) {
  try {
    const test = path.join(dir, '.write_test');
    fs.writeFileSync(test, String(Date.now()));
    fs.unlinkSync(test);
    return true;
  } catch (e) {
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
