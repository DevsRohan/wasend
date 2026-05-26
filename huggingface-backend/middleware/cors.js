'use strict';
const corsLib = require('cors');
const config = require('../config');

/**
 * Case-insensitive, trailing-slash-tolerant origin match.
 * Also accepts http <-> https swap so misconfiguration in PHP settings
 * doesn't silently break the dashboard's socket / fetch.
 */
function normalize(o) {
  if (!o) return '';
  return String(o).trim().replace(/\/+$/, '').toLowerCase();
}
function stripScheme(o) {
  return normalize(o).replace(/^https?:\/\//, '');
}

function isAllowed(origin) {
  if (!origin) return true;
  if (config.allowedOrigins.includes('*')) return true;
  const want = normalize(origin);
  const wantNoScheme = stripScheme(origin);
  return config.allowedOrigins.some((o) => {
    const n = normalize(o);
    if (n === want) return true;
    // tolerate http<->https mismatch
    if (stripScheme(o) === wantNoScheme) return true;
    return false;
  });
}

const cors = corsLib({
  origin(origin, cb) {
    if (isAllowed(origin)) return cb(null, true);
    cb(new Error('cors_not_allowed:' + origin));
  },
  credentials: false,
  methods: ['GET','POST','OPTIONS'],
  allowedHeaders: ['Content-Type','Authorization','X-Requested-With'],
  maxAge: 600,
});

module.exports = { cors, isAllowed };
