'use strict';
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
const randomBetween = (min, max) => Math.floor(min + Math.random() * (max - min + 1));
const randomDelay   = (minMs, maxMs) => sleep(randomBetween(minMs, maxMs));
module.exports = { sleep, randomDelay, randomBetween };
