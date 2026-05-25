'use strict';
const crypto = require('crypto');

function sign(payload, secret) {
  return crypto.createHmac('sha256', secret).update(payload).digest('hex');
}
function uuid() {
  return (crypto.randomUUID && crypto.randomUUID()) ||
         crypto.randomBytes(16).toString('hex');
}

module.exports = { sign, uuid };
