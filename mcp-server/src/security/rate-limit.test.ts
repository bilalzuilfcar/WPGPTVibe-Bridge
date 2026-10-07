import assert from 'node:assert/strict';
import test from 'node:test';
import { checkRateLimit, requestIdentity } from './rate-limit.js';

test('rate limiter blocks requests after the configured limit', () => {
  const key='test-'+Date.now()+'-'+Math.random();
  assert.equal(checkRateLimit(key,2,60_000).ok,true);
  assert.equal(checkRateLimit(key,2,60_000).ok,true);
  const blocked=checkRateLimit(key,2,60_000);
  assert.equal(blocked.ok,false);
  assert.equal(blocked.remaining,0);
  assert.ok(blocked.retryAfterSeconds>=1);
});

test('request identity prefers forwarded client address', () => {
  assert.equal(requestIdentity({'x-forwarded-for':'203.0.113.10, 10.0.0.2'},'127.0.0.1'),'203.0.113.10');
  assert.equal(requestIdentity({},'127.0.0.1'),'127.0.0.1');
});
