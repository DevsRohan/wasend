'use strict';
/**
 * retry(fn, opts) - exponential backoff retry helper
 */
async function retry(fn, opts = {}) {
  const max     = opts.max     ?? 4;
  const baseMs  = opts.baseMs  ?? 500;
  const factor  = opts.factor  ?? 2;
  const onError = opts.onError;
  let attempt = 0;
  let lastErr;
  while (attempt < max) {
    try {
      return await fn(attempt);
    } catch (e) {
      lastErr = e;
      attempt++;
      if (typeof onError === 'function') onError(e, attempt);
      if (attempt >= max) break;
      const wait = baseMs * Math.pow(factor, attempt - 1) + Math.floor(Math.random() * 200);
      await new Promise((r) => setTimeout(r, wait));
    }
  }
  throw lastErr;
}

module.exports = retry;
