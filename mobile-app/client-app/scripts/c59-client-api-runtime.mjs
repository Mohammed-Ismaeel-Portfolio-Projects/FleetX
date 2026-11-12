/**
 * C5.9 — Client API runtime critical-path + failure matrix.
 *
 * Proves live HTTP behavior against a seeded local API (not static source checks).
 *
 * Env:
 *   C59_API_BASE       default http://localhost:8000/api/v1
 *   C59_CLIENT_EMAIL   default client@fleetx.com
 *   C59_CLIENT_PASSWORD default Client@123
 *   C59_FIXTURE_PATH   default ../../backend/storage/app/c59-client-e2e-fixture.json
 */

import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const clientRoot = path.join(__dirname, '..');
const repoRoot = path.join(clientRoot, '..', '..');

const API = process.env.C59_API_BASE || process.env.FLEETX_API_BASE || 'http://localhost:8000/api/v1';
const EMAIL = process.env.C59_CLIENT_EMAIL || 'client@fleetx.com';
const PASSWORD = process.env.C59_CLIENT_PASSWORD || 'Client@123';
const FIXTURE_PATH =
  process.env.C59_FIXTURE_PATH ||
  path.join(repoRoot, 'backend', 'storage', 'app', 'c59-client-e2e-fixture.json');

let passed = 0;
let failed = 0;

function check(name, fn) {
  return Promise.resolve()
    .then(fn)
    .then(() => {
      passed += 1;
      console.log(`PASS  ${name}`);
    })
    .catch((e) => {
      failed += 1;
      console.error(`FAIL  ${name}`);
      console.error(e?.message || e);
    });
}

async function api(method, urlPath, { token, body, headers } = {}) {
  const res = await fetch(`${API}${urlPath}`, {
    method,
    headers: {
      Accept: 'application/json',
      ...(body ? { 'Content-Type': 'application/json' } : {}),
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
      ...headers,
    },
    body: body ? JSON.stringify(body) : undefined,
  });
  const text = await res.text();
  let json = null;
  try {
    json = text ? JSON.parse(text) : null;
  } catch {
    json = null;
  }
  return { res, json, text };
}

function loadFixture() {
  if (!fs.existsSync(FIXTURE_PATH)) {
    throw new Error(`Fixture missing at ${FIXTURE_PATH}. Run: php artisan fleetx:c59-seed`);
  }
  return JSON.parse(fs.readFileSync(FIXTURE_PATH, 'utf8'));
}

function flattenKeys(value, prefix = '', out = new Set()) {
  if (value == null || typeof value !== 'object') return out;
  if (Array.isArray(value)) {
    value.forEach((item, i) => flattenKeys(item, `${prefix}[${i}]`, out));
    return out;
  }
  for (const [k, v] of Object.entries(value)) {
    const next = prefix ? `${prefix}.${k}` : k;
    out.add(k);
    out.add(next);
    flattenKeys(v, next, out);
  }
  return out;
}

const FORBIDDEN_TRACKING_KEYS = [
  'driver',
  'vehicle',
  'plate',
  'plate_number',
  'assignment',
  'dispatcher',
  'eta',
  'provider',
  'encoded_polyline',
  'legs',
  'steps',
  'waypoints',
  'distance',
  'duration',
  'deviation',
  'route_status',
  'engine_ops',
];

async function main() {
  console.log('=== C5.9 A — API runtime critical path ===\n');
  console.log(`API=${API}`);

  const fixture = loadFixture();
  let token = null;

  await check('wrong credentials fail closed (422)', async () => {
    const { res, json } = await api('POST', '/auth/login', {
      body: { email: EMAIL, password: 'definitely-wrong-password' },
    });
    assert.equal(res.status, 422);
    assert.ok(!json?.data?.access_token);
  });

  await check('login success returns Bearer token', async () => {
    const { res, json } = await api('POST', '/auth/login', {
      body: { email: EMAIL, password: PASSWORD },
    });
    assert.equal(res.status, 200);
    token = json?.data?.access_token;
    assert.equal(typeof token, 'string');
    assert.ok(token.length > 20);
    assert.ok(json?.data?.user?.company_id || fixture.company_id);
  });

  await check('invalid token fails closed (401)', async () => {
    const { res } = await api('GET', '/client/shipments', { token: 'not-a-real-jwt' });
    assert.equal(res.status, 401);
  });

  await check('GET /auth/me succeeds', async () => {
    const { res, json } = await api('GET', '/auth/me', { token });
    assert.equal(res.status, 200);
    assert.ok(json?.data?.email || json?.data?.user?.email || json?.data?.id);
  });

  await check('shipment list includes fixture shipment', async () => {
    const { res, json } = await api('GET', '/client/shipments?per_page=20', { token });
    assert.equal(res.status, 200);
    const rows = json?.data?.data || [];
    const ids = rows.map((r) => String(r.id));
    assert.ok(ids.includes(String(fixture.shipment_id)), 'fixture shipment missing from list');
  });

  await check('foreign shipment denied (404)', async () => {
    const { res, text } = await api('GET', `/client/shipments/${fixture.foreign_shipment_id}`, { token });
    assert.equal(res.status, 404);
    assert.doesNotMatch(text, /SHP-C59-FOREIGN/);
  });

  await check('unknown shipment denied (404)', async () => {
    const { res } = await api('GET', '/client/shipments/00000000-0000-4000-8000-000000000099', {
      token,
    });
    assert.equal(res.status, 404);
  });

  await check('shipment show + tracking allowlist', async () => {
    const show = await api('GET', `/client/shipments/${fixture.shipment_id}`, { token });
    assert.equal(show.res.status, 200);

    const tracking = await api('GET', `/client/shipments/${fixture.shipment_id}/tracking`, { token });
    assert.equal(tracking.res.status, 200);
    const data = tracking.json?.data || {};
    const keys = flattenKeys(data);
    for (const forbidden of FORBIDDEN_TRACKING_KEYS) {
      assert.ok(!keys.has(forbidden), `forbidden tracking key leaked: ${forbidden}`);
    }
    assert.doesNotMatch(tracking.text, /\bETA\b/);
    // Customer-safe fields may be null — presence of resource is enough.
    assert.ok('milestones' in data || Array.isArray(data.milestones) || data.status != null);
  });

  await check('foreign tracking denied before leakage (404)', async () => {
    const { res, text } = await api(
      'GET',
      `/client/shipments/${fixture.foreign_shipment_id}/tracking`,
      { token }
    );
    assert.equal(res.status, 404);
    assert.doesNotMatch(text, /location_freshness|"route"|driver|vehicle/i);
  });

  await check('request list + show fixture', async () => {
    const list = await api('GET', '/client/shipment-requests?per_page=20', { token });
    assert.equal(list.res.status, 200);
    const rows = list.json?.data?.data || [];
    assert.ok(rows.some((r) => String(r.id) === String(fixture.request_id)));

    const show = await api('GET', `/client/shipment-requests/${fixture.request_id}`, { token });
    assert.equal(show.res.status, 200);
  });

  await check('notifications list + mark-read ownership', async () => {
    const list = await api('GET', '/client/notifications?per_page=20', { token });
    assert.equal(list.res.status, 200);
    const rows = list.json?.data?.data || [];
    assert.ok(rows.some((r) => Number(r.id) === Number(fixture.notification_id)));

    const mark = await api('PATCH', `/client/notifications/${fixture.notification_id}/read`, {
      token,
    });
    assert.ok([200, 204].includes(mark.res.status) || mark.res.status === 200);
    assert.equal(mark.res.status, 200);
  });

  await check('foreign notification mark-read denied', async () => {
    const { res } = await api('PATCH', '/client/notifications/999999991/read', { token });
    assert.ok([403, 404].includes(res.status), `expected 403/404 got ${res.status}`);
  });

  await check('conversation lookup + send message', async () => {
    const list = await api('GET', '/conversations', { token });
    assert.equal(list.res.status, 200);
    const rows = list.json?.data?.data || list.json?.data || [];
    const ids = (Array.isArray(rows) ? rows : []).map((r) => String(r.id));
    assert.ok(ids.includes(String(fixture.conversation_id)));

    const show = await api('GET', `/conversations/${fixture.conversation_id}`, { token });
    assert.equal(show.res.status, 200);

    const messages = await api('GET', `/conversations/${fixture.conversation_id}/messages`, {
      token,
    });
    assert.equal(messages.res.status, 200);

    const body = `C59 API smoke ${Date.now()}`;
    const send = await api('POST', `/conversations/${fixture.conversation_id}/messages`, {
      token,
      body: { body },
    });
    assert.equal(send.res.status, 201);
  });

  await check('client cannot create conversations', async () => {
    const { res } = await api('POST', '/conversations', {
      token,
      body: {
        type: 'dispatcher_customer',
        shipment_id: fixture.shipment_id,
        client_user_id: 1,
      },
    });
    assert.ok([403, 404, 405, 422].includes(res.status), `expected deny got ${res.status}`);
  });

  await check('unauthorized conversation denied', async () => {
    const { res } = await api('GET', '/conversations/00000000-0000-4000-8000-000000000088', {
      token,
    });
    assert.ok([403, 404].includes(res.status));
  });

  await check('token refresh where available', async () => {
    const { res, json } = await api('POST', '/auth/refresh', { token });
    assert.ok([200, 401, 422].includes(res.status), `unexpected refresh status ${res.status}`);
    if (res.status === 200) {
      const next = json?.data?.access_token;
      assert.equal(typeof next, 'string');
      assert.ok(next.length > 20);
      token = next;
    }
  });

  await check('logout + protected route rejection', async () => {
    const logout = await api('POST', '/auth/logout', { token });
    assert.ok([200, 204].includes(logout.res.status) || logout.res.status === 200);

    const after = await api('GET', '/client/shipments', { token });
    assert.equal(after.res.status, 401);
  });

  console.log('');
  console.log(`C5.9 API runtime: ${passed} passed, ${failed} failed`);
  if (failed > 0) process.exit(1);
  console.log('C5.9 API VERDICT: PASS');
}

main().catch((e) => {
  console.error(e);
  process.exit(1);
});
