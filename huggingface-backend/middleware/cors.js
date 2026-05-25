'use strict';
const corsLib = require('cors');
const config = require('../config');

function isAllowed(origin) {
  if (!origin) return true;
  if (config.allowedOrigins.includes('*')) return true;
  return config.allowedOrigins.some((o) => o === origin);
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
