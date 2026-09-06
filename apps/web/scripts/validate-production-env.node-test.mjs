import assert from 'node:assert/strict';
import test from 'node:test';
import { spawnSync } from 'node:child_process';
import { fileURLToPath, URL } from 'node:url';
import { validateProductionApiUrl } from './validate-production-env.mjs';

test('accepts the deployment API and an HTTPS /api origin', () => {
  for (const value of ['https://opfin-production.up.railway.app/api', 'https://example.com/api/']) {
    assert.equal(validateProductionApiUrl(value).protocol, 'https:');
  }
});

test('rejects missing, disconnected, local, numeric and credential-bearing endpoints', () => {
  for (const value of [undefined, '', 'https://example.com', 'http://example.com/api',
    'https://localhost./api', 'https://x.localhost/api', 'https://127.0.0.2/api',
    'https://10.0.0.1/api', 'https://192.168.1.1/api', 'https://169.254.169.254/api',
    'https://[::1]/api', 'https://[fc00::1]/api', 'https://2130706433/api',
    'https://0x7f000001/api', 'https://127.1/api', 'https://x.internal./api',
    'https://example.local/api', 'https://u:p@example.com/api',
    'https://example.com/api?q=1', 'https://example.com/api#x']) {
    assert.throws(() => validateProductionApiUrl(value), undefined, String(value));
  }
});

test('the build guard exits unsuccessfully and never echoes the supplied credential', () => {
  const result = spawnSync(process.execPath, [fileURLToPath(new URL('./validate-production-env.mjs', import.meta.url))], {
    env: { ...process.env, NEXT_PUBLIC_OPFIN_API_URL: 'https://user:private-sentinel@example.com/api' },
    encoding: 'utf8',
  });
  assert.equal(result.status, 1);
  assert.doesNotMatch(result.stdout + result.stderr, /private-sentinel/);
});
