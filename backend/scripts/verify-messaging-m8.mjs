/**
 * M8: multi-conversation isolation + device-contract flows + Reverb fallover signals.
 *
 * Usage (backend/):
 *   php scripts/seed-m8-isolation-fixture.php
 *   node scripts/verify-messaging-m8.mjs
 *
 * Requires: artisan serve + reverb:start + demo users.
 */

import Pusher from '../../dashboard-web/node_modules/pusher-js/dist/node/pusher.js';
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));

function loadEnvFile(filePath) {
  if (!fs.existsSync(filePath)) return;
  for (const line of fs.readFileSync(filePath, 'utf8').split(/\r?\n/)) {
    const trimmed = line.trim();
    if (!trimmed || trimmed.startsWith('#')) continue;
    const eq = trimmed.indexOf('=');
    if (eq < 0) continue;
    const key = trimmed.slice(0, eq).trim();
    let val = trimmed.slice(eq + 1).trim();
    if (
      (val.startsWith('"') && val.endsWith('"')) ||
      (val.startsWith("'") && val.endsWith("'"))
    ) {
      val = val.slice(1, -1);
    }
    if (!(key in process.env)) process.env[key] = val;
  }
}

loadEnvFile(path.join(__dirname, '../.env'));

const API = process.env.FLEETX_API_BASE || 'http://localhost:8000/api/v1';
const REVERB_KEY = process.env.REVERB_APP_KEY || process.env.VITE_REVERB_APP_KEY;
const REVERB_HOST = process.env.REVERB_HOST || 'localhost';
const REVERB_PORT = Number(process.env.REVERB_PORT || 8080);
const REVERB_SCHEME = process.env.REVERB_SCHEME || 'http';

function fail(message) {
  console.error('FAIL:', message);
  process.exit(1);
}
function ok(message) {
  console.log('OK:', message);
}

async function login(email, password) {
  const res = await fetch(`${API}/auth/login`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
    body: JSON.stringify({ email, password }),
  });
  const json = await res.json().catch(() => ({}));
  if (!res.ok) fail(`Login ${email}: ${res.status} ${json.message || ''}`);
  return json.data.access_token;
}

async function api(token, method, pathName, body) {
  const res = await fetch(`${API}${pathName}`, {
    method,
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
      Authorization: `Bearer ${token}`,
    },
    body: body ? JSON.stringify(body) : undefined,
  });
  const json = await res.json().catch(() => ({}));
  return { res, json };
}

function createPusher(token) {
  return new Pusher(REVERB_KEY, {
    wsHost: REVERB_HOST,
    wsPort: REVERB_PORT,
    wssPort: REVERB_PORT,
    forceTLS: REVERB_SCHEME === 'https',
    enabledTransports: ['ws', 'wss'],
    disableStats: true,
    cluster: 'mt1',
    authorizer: (channel) => ({
      authorize: (socketId, callback) => {
        fetch(`${API}/broadcasting/auth`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            Authorization: `Bearer ${token}`,
          },
          body: JSON.stringify({
            socket_id: socketId,
            channel_name: channel.name,
          }),
        })
          .then(async (response) => {
            if (!response.ok) {
              callback(new Error(`auth ${response.status}`), null);
              return;
            }
            callback(null, await response.json());
          })
          .catch((err) => callback(err, null));
      },
    }),
  });
}

async function waitConnected(pusher, label) {
  await new Promise((resolve, reject) => {
    const t = setTimeout(() => reject(new Error(`${label} connect timeout`)), 15000);
    pusher.connection.bind('connected', () => {
      clearTimeout(t);
      resolve();
    });
    pusher.connection.bind('error', (err) => {
      clearTimeout(t);
      reject(err?.error || err || new Error(`${label} error`));
    });
  });
}

async function subscribe(pusher, conversationId) {
  const channelName = `private-conversation.${conversationId}`;
  const channel = pusher.subscribe(channelName);
  await new Promise((resolve, reject) => {
    const t = setTimeout(() => reject(new Error(`subscribe timeout ${channelName}`)), 15000);
    channel.bind('pusher:subscription_succeeded', () => {
      clearTimeout(t);
      resolve();
    });
    channel.bind('pusher:subscription_error', (status) => {
      clearTimeout(t);
      reject(new Error(`subscription_error ${status}`));
    });
  });
  return channel;
}

async function isolationCase({ label, watcherToken, senderToken, openId, otherId }) {
  const pusher = createPusher(watcherToken);
  await waitConnected(pusher, label);
  const channelA = await subscribe(pusher, openId);

  let leaked = false;
  let sawOnA = null;
  channelA.bind('message.created', (payload) => {
    if (payload.conversation_id === openId) sawOnA = payload;
    if (payload.conversation_id && payload.conversation_id !== openId) leaked = true;
  });

  const body = `M8 isolation ${label} ${Date.now()}`;
  const send = await api(senderToken, 'POST', `/conversations/${otherId}/messages`, { body });
  if (send.res.status !== 201) fail(`${label} send B: ${send.res.status}`);

  await new Promise((r) => setTimeout(r, 2500));
  if (leaked) fail(`${label}: B event leaked onto A channel`);
  if (sawOnA && sawOnA.id === send.json.data.id) fail(`${label}: B message appeared on A`);

  const histB = await api(watcherToken, 'GET', `/conversations/${otherId}/messages?per_page=20`);
  if (!histB.res.ok) fail(`${label} hist B ${histB.res.status}`);
  const found = (histB.json?.data?.data || []).some((m) => m.id === send.json.data.id);
  if (!found) fail(`${label}: message missing from B history`);

  const histA = await api(watcherToken, 'GET', `/conversations/${openId}/messages?per_page=50`);
  if (!histA.res.ok) fail(`${label} hist A ${histA.res.status}`);
  const onA = (histA.json?.data?.data || []).some((m) => m.id === send.json.data.id);
  if (onA) fail(`${label}: B message incorrectly persisted on A`);

  ok(`${label}: A open, B message isolated; B history has message`);
  pusher.disconnect();
}

async function deviceFlow({ name, senderToken, receiverToken, conversationId, body }) {
  const pusher = createPusher(receiverToken);
  await waitConnected(pusher, name);
  const channel = await subscribe(pusher, conversationId);
  const eventPromise = new Promise((resolve, reject) => {
    const t = setTimeout(() => reject(new Error(`${name}: no WS`)), 15000);
    channel.bind('message.created', (payload) => {
      clearTimeout(t);
      resolve(payload);
    });
  });
  const send = await api(senderToken, 'POST', `/conversations/${conversationId}/messages`, {
    body,
  });
  if (send.res.status !== 201) fail(`${name} send ${send.res.status}`);
  const messageId = send.json.data.id;
  const received = await eventPromise;
  if (received.id !== messageId) fail(`${name} id mismatch`);
  ok(`${name}: REST+WS id=${messageId}`);
  pusher.disconnect();
}

async function main() {
  if (!REVERB_KEY) fail('REVERB_APP_KEY missing');

  const fixturePath = path.join(__dirname, '../storage/app/m8-isolation-fixture.json');
  if (!fs.existsSync(fixturePath)) fail('Run php scripts/seed-m8-isolation-fixture.php first');
  const fixture = JSON.parse(fs.readFileSync(fixturePath, 'utf8'));
  if (!fixture.driver?.[1] || !fixture.customer?.[1]) fail('Fixture needs 2 driver + 2 customer ids');

  ok(`API ${API}`);
  const dispatcherToken = await login('dispatcher@fleetx.com', 'Dispatcher@123').catch(async () =>
    login('admin@fleetx.com', 'password')
  );
  const driverToken = await login('driver@fleetx.com', 'Driver@123');
  const clientToken = await login('client@fleetx.com', 'Client@123');
  ok('Authenticated');

  const driverMe = await api(driverToken, 'GET', '/auth/identity');
  if (!driverMe.json?.data?.linked) fail('Driver identity unlinked');
  const clientMe = await api(clientToken, 'GET', '/auth/identity');
  if (!clientMe.json?.data?.linked) fail('Client identity unlinked');
  ok('Identities linked');

  // Device-contract wording bodies
  await deviceFlow({
    name: 'Device dispatcher→driver',
    senderToken: dispatcherToken,
    receiverToken: driverToken,
    conversationId: fixture.driver[0],
    body: 'Device test — dispatcher to driver',
  });
  await deviceFlow({
    name: 'Device driver→dispatcher',
    senderToken: driverToken,
    receiverToken: dispatcherToken,
    conversationId: fixture.driver[0],
    body: 'Device test — driver to dispatcher',
  });
  await deviceFlow({
    name: 'Device dispatcher→customer',
    senderToken: dispatcherToken,
    receiverToken: clientToken,
    conversationId: fixture.customer[0],
    body: 'Device test — dispatcher to customer',
  });
  await deviceFlow({
    name: 'Device customer→dispatcher',
    senderToken: clientToken,
    receiverToken: dispatcherToken,
    conversationId: fixture.customer[0],
    body: 'Device test — customer to dispatcher',
  });

  await isolationCase({
    label: 'Driver A/B',
    watcherToken: driverToken,
    senderToken: dispatcherToken,
    openId: fixture.driver[0],
    otherId: fixture.driver[1],
  });
  await isolationCase({
    label: 'Customer A/B',
    watcherToken: clientToken,
    senderToken: dispatcherToken,
    openId: fixture.customer[0],
    otherId: fixture.customer[1],
  });

  // Shipment handoff: list→shipment_id resolve without create
  const list = await api(driverToken, 'GET', `/conversations/${fixture.driver[0]}`);
  const shipmentId = list.json?.data?.shipment_id;
  if (!shipmentId) fail('conversation missing shipment_id');
  const byShip = await api(
    driverToken,
    'GET',
    `/conversations?type=dispatcher_driver&shipment_id=${shipmentId}&per_page=5`
  );
  if (!byShip.res.ok) fail(`shipment filter ${byShip.res.status}`);
  const match = (byShip.json?.data?.data || []).find((c) => c.id === fixture.driver[0]);
  if (!match) fail('shipment handoff list did not return authorized conversation');
  ok(`Shipment handoff resolve shipment=${shipmentId} → conversation=${match.id}`);

  // Authz regression
  const cross = await api(clientToken, 'GET', `/conversations/${fixture.driver[0]}`);
  if (cross.res.status !== 403 && cross.res.status !== 404) {
    fail(`client accessed driver conv (${cross.res.status})`);
  }
  ok('Authz: client ≠ dispatcher_driver');

  ok('M8 isolation + device-contract verification passed');
  process.exit(0);
}

main().catch((err) => {
  console.error(err);
  process.exit(1);
});
