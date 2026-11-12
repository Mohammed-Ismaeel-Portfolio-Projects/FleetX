/**
 * Phase 2 dashboard auth session tests (INT-003 + INT-026).
 */
import { strict as assert } from 'node:assert';
import { existsSync, readFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { test } from 'node:test';
import { fileURLToPath } from 'node:url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const ROOT = resolve(__dirname, '..');

function read(rel) {
  const p = resolve(ROOT, rel);
  assert.ok(existsSync(p), `missing ${rel}`);
  return readFileSync(p, 'utf8');
}

function classifyAuthRequestFailure(status) {
  if (status === 401 || status === 403) {
    return 'unauthorized';
  }
  return 'unavailable';
}

function mapDashboardRole(roleName) {
  if (roleName === 'admin' || roleName === 'dispatcher') {
    return roleName;
  }
  return null;
}

function mapMeToDashboardUser(me) {
  const role = mapDashboardRole(me.role?.name);
  if (!role) {
    return null;
  }
  return { id: me.id, name: me.name, role };
}

function shouldSkipGlobalAuthRedirect(url) {
  if (!url) {
    return false;
  }
  const path = url.split('?')[0] ?? url;
  return (
    path.includes('/auth/logout') ||
    path.includes('/auth/me') ||
    path.includes('/auth/login') ||
    path.includes('/auth/register')
  );
}

async function restoreDashboardSession({ getToken, me }) {
  const storedToken = getToken();
  if (!storedToken) {
    return { kind: 'unauthenticated', user: null, keepToken: false, calledMe: false };
  }

  try {
    const payload = await me(storedToken);
    const user = mapMeToDashboardUser(payload);
    if (!user) {
      return { kind: 'unauthenticated', user: null, keepToken: false, calledMe: true };
    }
    return { kind: 'authenticated', user, keepToken: true, calledMe: true };
  } catch (error) {
    const status = error?.status;
    if (classifyAuthRequestFailure(status) === 'unauthorized') {
      return { kind: 'unauthenticated', user: null, keepToken: false, calledMe: true };
    }
    return { kind: 'unavailable', user: null, keepToken: true, calledMe: true };
  }
}

async function completeLogout({ logoutApi, clearSession }) {
  try {
    await logoutApi();
  } catch {
    // 401 and transport errors still clear local session.
  } finally {
    clearSession();
  }
}

test('session lifecycle helpers match dashboard source', () => {
  const src = read('src/core/auth/sessionLifecycle.ts');
  assert.match(src, /FLEETX_TOKEN_KEY = 'fleetx_token'/);
  assert.match(src, /FLEETX_USER_KEY = 'fleetx_user'/);
  assert.match(src, /status === 401 \|\| status === 403/);
  assert.match(src, /roleName === 'admin' \|\| roleName === 'dispatcher'/);
  assert.match(src, /\/auth\/logout/);
  assert.match(src, /\/auth\/me/);
});

test('A: valid token restore calls me and uses API identity', async () => {
  let meCalls = 0;
  const result = await restoreDashboardSession({
    getToken: () => 'valid-jwt',
    me: async () => {
      meCalls += 1;
      return { id: 7, name: 'API Dispatcher', role: { name: 'dispatcher' } };
    },
  });

  assert.equal(meCalls, 1);
  assert.equal(result.calledMe, true);
  assert.equal(result.kind, 'authenticated');
  assert.equal(result.keepToken, true);
  assert.deepEqual(result.user, { id: 7, name: 'API Dispatcher', role: 'dispatcher' });

  const provider = read('src/core/auth/AuthProvider.tsx');
  assert.match(provider, /authService\.me\(\)/);
  assert.match(provider, /mapMeToDashboardUser/);
  assert.match(provider, /persistDashboardSession/);
  assert.equal(/JSON\.parse\(storedUser\)/.test(provider), false);
});

test('B: invalid token 401 clears session', async () => {
  const result = await restoreDashboardSession({
    getToken: () => 'expired-jwt',
    me: async () => {
      const err = new Error('unauthorized');
      err.status = 401;
      throw err;
    },
  });

  assert.equal(result.kind, 'unauthenticated');
  assert.equal(result.keepToken, false);
  assert.equal(result.user, null);

  const provider = read('src/core/auth/AuthProvider.tsx');
  assert.match(provider, /classifyAuthRequestFailure/);
  assert.match(provider, /applyUnauthenticated/);
});

test('C: network\/5xx restore preserves token and does not retry in a loop', async () => {
  let meCalls = 0;
  const result = await restoreDashboardSession({
    getToken: () => 'still-valid-jwt',
    me: async () => {
      meCalls += 1;
      const err = new Error('unavailable');
      err.status = 503;
      throw err;
    },
  });

  assert.equal(meCalls, 1);
  assert.equal(result.kind, 'unavailable');
  assert.equal(result.keepToken, true);
  assert.equal(result.user, null);

  const network = await restoreDashboardSession({
    getToken: () => 'still-valid-jwt',
    me: async () => {
      throw new Error('network down');
    },
  });
  assert.equal(network.kind, 'unavailable');
  assert.equal(network.keepToken, true);

  const provider = read('src/core/auth/AuthProvider.tsx');
  assert.match(provider, /Unable to verify your session/);
  assert.equal(/setInterval/.test(provider), false);

  const route = read('src/core/auth/ProtectedRoute.tsx');
  assert.match(route, /sessionError/);
  assert.match(route, /retrySession/);
});

test('D: successful logout calls API then clears session', async () => {
  let logoutCalls = 0;
  let cleared = false;
  await completeLogout({
    logoutApi: async () => {
      logoutCalls += 1;
    },
    clearSession: () => {
      cleared = true;
    },
  });
  assert.equal(logoutCalls, 1);
  assert.equal(cleared, true);

  const provider = read('src/core/auth/AuthProvider.tsx');
  assert.match(provider, /authService\.logout\(\)/);
  assert.match(provider, /applyUnauthenticated\(\)/);
  const service = read('src/core/services/auth.service.ts');
  assert.match(service, /post<\{ message: string \}>\(\s*'\/auth\/logout'/);
  assert.match(service, /Authorization: `Bearer \$\{token\}`/);
});

test('E: logout 401 still clears local session', async () => {
  let cleared = false;
  await completeLogout({
    logoutApi: async () => {
      const err = new Error('gone');
      err.status = 401;
      throw err;
    },
    clearSession: () => {
      cleared = true;
    },
  });
  assert.equal(cleared, true);

  const provider = read('src/core/auth/AuthProvider.tsx');
  assert.match(provider, /catch \{/);
  assert.match(provider, /finally \{[\s\S]*applyUnauthenticated/);
});

test('interceptor skips auth lifecycle endpoints to prevent 401 loops', () => {
  assert.equal(shouldSkipGlobalAuthRedirect('/auth/logout'), true);
  assert.equal(shouldSkipGlobalAuthRedirect('/auth/me'), true);
  assert.equal(shouldSkipGlobalAuthRedirect('/auth/login'), true);
  assert.equal(shouldSkipGlobalAuthRedirect('/tracking/live-fleet'), false);

  const interceptors = read('src/core/api/client/interceptors.ts');
  assert.match(interceptors, /shouldSkipGlobalAuthRedirect/);
  assert.match(interceptors, /status === 401/);
});

test('layouts await or fire logout promise instead of sync local clear only', () => {
  const dispatcher = read('src/layouts/DispatcherLayout.tsx');
  assert.match(dispatcher, /void logout\(\)/);
  const sidebar = read('src/components/dashboard/layout/Sidebar.tsx');
  assert.match(sidebar, /void logout\(\)/);
});

test('dashboard still uses a single JWT key', () => {
  const provider = read('src/core/auth/AuthProvider.tsx');
  const service = read('src/core/services/auth.service.ts');
  const interceptors = read('src/core/api/client/interceptors.ts');
  for (const src of [provider, service, interceptors]) {
    assert.equal(/\blocalStorage\.getItem\('token'\)/.test(src), false);
  }
  assert.match(service, /FLEETX_TOKEN_KEY/);
});
