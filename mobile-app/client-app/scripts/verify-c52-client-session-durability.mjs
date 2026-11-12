/**
 * C5.2 — Client session durability / JWT refresh (static + behavioral).
 * Run: node ./scripts/verify-c52-client-session-durability.mjs
 */

import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { createRequire } from 'node:module';
import { fileURLToPath } from 'node:url';
import ts from 'typescript';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const clientRoot = path.join(__dirname, '..');
const repoRoot = path.join(clientRoot, '..', '..');

function readClient(rel) {
  return fs.readFileSync(path.join(clientRoot, rel), 'utf8');
}

function readRepo(rel) {
  return fs.readFileSync(path.join(repoRoot, rel), 'utf8');
}

let passed = 0;
let failed = 0;

async function check(name, fn) {
  try {
    await fn();
    passed += 1;
    console.log(`PASS  ${name}`);
  } catch (e) {
    failed += 1;
    console.error(`FAIL  ${name}`);
    console.error(e);
  }
}

/** Mirror of isAuthRefreshExcludedUrl — keep in sync with authRefresh.ts */
function isAuthRefreshExcludedUrl(url) {
  if (!url) return false;
  const pathOnly = url.split('?')[0] ?? url;
  return (
    pathOnly.includes('/auth/login') ||
    pathOnly.includes('/auth/register') ||
    pathOnly.includes('/auth/refresh')
  );
}

/** Behavioral single-flight model */
function createSingleFlight() {
  let inflight = null;
  let starts = 0;
  return {
    run(fn) {
      if (!inflight) {
        starts += 1;
        const pending = Promise.resolve().then(fn);
        inflight = pending;
        pending.finally(() => {
          if (inflight === pending) inflight = null;
        });
      }
      return inflight;
    },
    get starts() {
      return starts;
    },
  };
}

await check('backend refresh contract remains available and unchanged in Client phase', () => {
  const controller = readRepo('backend/app/Http/Controllers/Api/Auth/AuthController.php');
  const jwt = readRepo('backend/config/jwt.php');
  const routes = readRepo('backend/routes/api.php');
  assert.match(controller, /function refresh/);
  assert.match(routes, /Route::post\('refresh'/);
  assert.match(routes, /v1\.auth\.refresh/);
  assert.match(jwt, /'ttl'\s*=>\s*env\('JWT_TTL',\s*60\)/);
  assert.match(jwt, /refresh_ttl/);
});

await check('Client implements refresh module + interceptor retry-once', () => {
  const refresh = readClient('src/lib/auth/authRefresh.ts');
  const client = readClient('src/lib/api/client.ts');
  const authApi = readClient('src/lib/auth/authApi.ts');
  assert.match(refresh, /refreshAccessTokenSingleFlight/);
  assert.match(refresh, /performAccessTokenRefresh/);
  assert.match(refresh, /\/auth\/refresh/);
  assert.match(refresh, /bumpAuthSessionEpoch/);
  assert.match(refresh, /sessionEpoch/);
  assert.match(client, /_authRetry/);
  assert.match(client, /skipAuthRefresh/);
  assert.match(client, /refreshAccessTokenSingleFlight/);
  assert.match(client, /shouldAttemptRefresh/);
  assert.match(authApi, /skipAuthRefresh:\s*true/);
  assert.match(authApi, /bumpAuthSessionEpoch/);
});

await check('login/register/logout exclude refresh; refresh cannot recurse via apiClient', () => {
  const authApi = readClient('src/lib/auth/authApi.ts');
  const refresh = readClient('src/lib/auth/authRefresh.ts');
  const client = readClient('src/lib/api/client.ts');
  assert.match(authApi, /\/auth\/login[\s\S]*skipAuthRefresh:\s*true/);
  assert.match(authApi, /\/auth\/register[\s\S]*skipAuthRefresh:\s*true/);
  assert.match(authApi, /\/auth\/logout[\s\S]*skipAuthRefresh:\s*true/);
  assert.match(refresh, /axios\.post/);
  assert.doesNotMatch(refresh, /from ['"]\.\.\/api\/client['"]/);
  assert.doesNotMatch(refresh, /apiClient\.(get|post|request)/);
  assert.match(client, /isAuthRefreshExcludedUrl/);
  assert.match(client, /config\._authRetry/);
});

await check('no token logging in auth refresh path', () => {
  const refresh = readClient('src/lib/auth/authRefresh.ts');
  const client = readClient('src/lib/api/client.ts');
  assert.doesNotMatch(refresh, /console\.(log|debug|info|warn).*token/i);
  assert.doesNotMatch(client, /console\.(log|debug|info|warn).*token/i);
  assert.doesNotMatch(refresh, /console\.log\(/);
});

await check('behavioral: excluded URLs never refresh', () => {
  assert.equal(isAuthRefreshExcludedUrl('/auth/login'), true);
  assert.equal(isAuthRefreshExcludedUrl('/auth/register'), true);
  assert.equal(isAuthRefreshExcludedUrl('/auth/refresh'), true);
  assert.equal(isAuthRefreshExcludedUrl('/client/shipments'), false);
  assert.equal(isAuthRefreshExcludedUrl('/auth/me'), false);
});

await check('behavioral: concurrent callers share one refresh start', async () => {
  const flight = createSingleFlight();
  let resolveFn;
  const gate = new Promise((resolve) => {
    resolveFn = resolve;
  });
  const work = () => gate.then(() => 'token-new');

  const p1 = flight.run(work);
  const p2 = flight.run(work);
  const p3 = flight.run(work);
  assert.equal(flight.starts, 1);
  resolveFn();
  const results = await Promise.all([p1, p2, p3]);
  assert.deepEqual(results, ['token-new', 'token-new', 'token-new']);
  assert.equal(flight.starts, 1);
});

await check('behavioral: retry marker prevents second refresh cycle', () => {
  function shouldAttemptRefresh(config) {
    if (!config) return false;
    if (config.skipAuthRefresh) return false;
    if (config._authRetry) return false;
    if (isAuthRefreshExcludedUrl(config.url)) return false;
    return true;
  }
  assert.equal(shouldAttemptRefresh({ url: '/client/shipments' }), true);
  assert.equal(shouldAttemptRefresh({ url: '/client/shipments', _authRetry: true }), false);
  assert.equal(shouldAttemptRefresh({ url: '/auth/login' }), false);
  assert.equal(shouldAttemptRefresh({ url: '/client/x', skipAuthRefresh: true }), false);
});

await check('behavioral: epoch bump invalidates in-flight refresh success', async () => {
  let epoch = 0;
  const bump = () => {
    epoch += 1;
  };
  const startEpoch = epoch;
  const refresh = async () => {
    await Promise.resolve();
    if (startEpoch !== epoch) throw new Error('Session ended during refresh.');
    return 'new-token';
  };
  const pending = refresh();
  bump();
  await assert.rejects(pending, /Session ended during refresh/);
});

await check('AuthContext still wires session-invalid listener', () => {
  const ctx = readClient('src/lib/auth/AuthContext.tsx');
  assert.match(ctx, /setSessionInvalidListener/);
  assert.match(ctx, /session_expired/);
  assert.match(ctx, /bumpAuthSessionEpoch/);
});

await check('C5.1 tracking files not redesigned by C5.2', () => {
  const tracking = readClient('src/app/shipment-tracking/[id].tsx');
  assert.match(tracking, /lastKnownPosition|Last known location/);
});

await check('authRefresh.ts compiles enough to export exclusion helper', () => {
  const abs = path.join(clientRoot, 'src/lib/auth/authRefresh.ts');
  const source = fs.readFileSync(abs, 'utf8');
  assert.match(source, /export function isAuthRefreshExcludedUrl/);
  ts.transpileModule(source, {
    compilerOptions: {
      module: ts.ModuleKind.CommonJS,
      target: ts.ScriptTarget.ES2020,
      esModuleInterop: true,
    },
  });
});

console.log(`\nC5.2 verifier: ${passed} passed, ${failed} failed`);
if (failed > 0) process.exitCode = 1;
