'use strict';
/**
 * phone.js - Normalize phone to E.164 digits + WhatsApp JID
 */
function normalize(raw, defaultCountry = '91') {
  if (!raw) return { e164: '', jid: '', valid: false };
  const hasPlus = String(raw).trim().startsWith('+');
  let digits = String(raw).replace(/\D+/g, '');
  if (!digits) return { e164: '', jid: '', valid: false };
  if (!hasPlus && digits.startsWith('0')) digits = digits.replace(/^0+/, '');
  if (!hasPlus && digits.length === 10)   digits = defaultCountry + digits;
  const valid = digits.length >= 8 && digits.length <= 15;
  return {
    e164: digits,
    jid:  valid ? `${digits}@c.us` : '',
    valid,
  };
}

function jidToPhone(jid) {
  if (!jid) return '';
  return String(jid).split('@')[0];
}

module.exports = { normalize, jidToPhone };
