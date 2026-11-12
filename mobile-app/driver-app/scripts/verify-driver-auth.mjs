/**
 * DRIVER-AUTH — D1 authentication / session / role / linkage / refresh foundations.
 * Run: npm run test:driver-auth
 */

import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const root = path.join(__dirname, '..');

let passed = 0;
let failed = 0;

function check(name, fn) {
  try {
    fn();
    passed += 1;
    console.log(`PASS  ${name}`);
  } catch (e) {
    failed += 1;
    console.error(`FAIL  ${name}`);
    console.error(e?.message || e);
  }
}

function read(rel) {
  return fs.readFileSync(path.join(root, rel), 'utf8');
}

console.log('=== DRIVER-AUTH (D1) ===\n');

const authCtx = read('src/lib/auth/AuthContext.tsx');
const authApi = read('src/lib/auth/authApi.ts');
const authRefresh = read('src/lib/auth/authRefresh.ts');
const client = read('src/lib/api/client.ts');
const login = read('src/app/login.tsx');
const work = read('src/app/(tabs)/index.tsx');

check('JWT refresh module uses existing POST /auth/refresh', () => {
  assert.match(authRefresh, /\/auth\/refresh/);
  assert.match(authRefresh, /refreshAccessTokenSingleFlight/);
  assert.match(authRefresh, /bumpAuthSessionEpoch/);
  assert.match(authRefresh, /isAuthRefreshExcludedUrl/);
});

check('API client: 401 refresh once, retry once, fail-closed', () => {
  assert.match(client, /refreshAccessTokenSingleFlight/);
  assert.match(client, /_authRetry/);
  assert.match(client, /skipAuthRefresh/);
  assert.match(client, /emitSessionInvalid/);
  assert.match(client, /failClosedSession/);
});

check('Login/logout skip refresh; clearLocalSession bumps epoch', () => {
  assert.match(authApi, /skipAuthRefresh:\s*true/);
  assert.match(authApi, /clearLocalSession/);
  assert.match(authApi, /bumpAuthSessionEpoch/);
});

check('AuthContext: session listener, canOperate, wrong-role fail-closed', () => {
  assert.match(authCtx, /setSessionInvalidListener/);
  assert.match(authCtx, /canOperate/);
  assert.match(authCtx, /AppConfig\.appRole/);
  assert.match(authCtx, /WRONG_ROLE_MESSAGE|drivers only/i);
  assert.match(authCtx, /clearLocalSession/);
  assert.match(authCtx, /retrySessionRestore/);
});

check('Work blocks unlinked / wrong role; empty assignment honest', () => {
  assert.match(work, /work-blocked-link|Driver account not linked/);
  assert.match(work, /No active assignment/);
  assert.doesNotMatch(work, /No active shipments yet/);
});

check('Production UI has no hardcoded driver credentials', () => {
  assert.doesNotMatch(login, /driver@fleetx\.com/);
  assert.doesNotMatch(login, /Driver@123/);
  assert.match(login, /EXPO_PUBLIC_DRIVER_DEV_PREFILL/);
  assert.match(login, /__DEV__/);
});

check('Refresh excluded URLs prevent loops', () => {
  assert.match(authRefresh, /\/auth\/login/);
  assert.match(authRefresh, /\/auth\/refresh/);
});

console.log('');
console.log(`DRIVER-AUTH: ${passed} passed, ${failed} failed`);
if (failed > 0) process.exit(1);
console.log('DRIVER-AUTH VERDICT: PASS');
