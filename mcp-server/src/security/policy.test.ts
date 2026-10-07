import assert from 'node:assert/strict';
import test from 'node:test';
import { isBridgePathAllowed, resolveRegisteredSitePath } from './policy.js';

test('bridge path policy allows known routes and rejects arbitrary endpoints', () => {
  assert.equal(isBridgePathAllowed('site'), true);
  assert.equal(isBridgePathAllowed('content/123'), true);
  assert.equal(isBridgePathAllowed('content/123/meta'), true);
  assert.equal(isBridgePathAllowed('calculators/speeds-and-feeds/validate'), true);
  assert.equal(isBridgePathAllowed('wp-json/wp/v2/users'), false);
  assert.equal(isBridgePathAllowed('../wp-config.php'), false);
  assert.equal(isBridgePathAllowed('content/abc'), false);
});

test('browser path resolver stays on the registered site origin', () => {
  assert.equal(
    resolveRegisteredSitePath('https://mfg.martzine.com/', '/calculators/speeds-and-feeds/'),
    'https://mfg.martzine.com/calculators/speeds-and-feeds/',
  );
  assert.throws(() => resolveRegisteredSitePath('https://mfg.martzine.com/', 'https://evil.example/'), /relative site path/);
  assert.throws(() => resolveRegisteredSitePath('https://mfg.martzine.com/', '//evil.example/'), /relative site path/);
  assert.throws(() => resolveRegisteredSitePath('https://mfg.martzine.com/', 'not-rooted'), /relative site path/);
});
