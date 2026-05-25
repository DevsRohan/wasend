'use strict';
const fs = require('fs');
const path = require('path');
const config = require('../config');

const LEVELS = ['debug', 'info', 'warn', 'error'];
const COLORS = {
  debug: '\x1b[90m',
  info:  '\x1b[36m',
  warn:  '\x1b[33m',
  error: '\x1b[31m',
};
const RESET = '\x1b[0m';

let logStream = null;
function getStream() {
  if (logStream) return logStream;
  try {
    if (!fs.existsSync(config.logsDir)) fs.mkdirSync(config.logsDir, { recursive: true });
    logStream = fs.createWriteStream(path.join(config.logsDir, 'engine.log'), { flags: 'a' });
  } catch (e) {
    logStream = null;
  }
  return logStream;
}

function fmt(level, msg, meta) {
  const ts = new Date().toISOString();
  const m  = meta ? ' ' + safeJson(meta) : '';
  return `[${ts}] [${level.toUpperCase()}] ${msg}${m}`;
}

function safeJson(o) {
  try { return JSON.stringify(o); } catch (_) { return '{"err":"unserializable"}'; }
}

function write(level, msg, meta) {
  const line = fmt(level, msg, meta);
  process.stdout.write(`${COLORS[level] || ''}${line}${RESET}\n`);
  const s = getStream();
  if (s) { try { s.write(line + '\n'); } catch (_) {} }
}

const logger = {};
LEVELS.forEach((l) => { logger[l] = (msg, meta) => write(l, msg, meta); });
module.exports = logger;
