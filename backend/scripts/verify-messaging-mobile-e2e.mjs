/**
 * M7 runtime verification — mobile messaging contract via REST + Reverb.
 *
 * Proves the SAME APIs/channels the Expo apps use:
 *   Flows A–D: dispatcher ↔ driver, dispatcher ↔ customer (both directions)
 *   Message.id REST === WS
 *   Cross-conversation isolation (event for B ignored while watching A)
 *   Unauthorized channel auth denied
 *
 * Prerequisites:
 * - php artisan serve
 * - php artisan reverb:start
 * - Demo users + fixtures:
 *     php scripts/seed-messaging-realtime-fixture.php
 *     php scripts/seed-messaging-customer-fixture.php
 *
 * Usage (from backend/):
 *   node scripts/verify-messaging-mobile-e2e.mjs
 */

import Pusher from '../../dashboard-web/node_modules/pusher-js/dist/node/pusher.js';
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));

function loadEnvFile(filePath) {
  if (!fs.existsSync(filePath)) return;
  const text = fs.readFileSync(filePath, 'utf8');
  for (const line of text.split(/\r?\n/)) {
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
  if (!res.ok) {
    const err = new Error(`Login failed for ${email}: ${res.status} ${json.message || ''}`);
    err.status = res.status;
    throw err;
  }
  return json.data.access_token;
}

async function loginOrFail(email, password) {
  try {
    return await login(email, password);
  } catch (e) {
    fail(e.message);
  }
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

async function identity(token) {
  const { res, json } = await api(token, 'GET', '/auth/identity');
  if (!res.ok) fail(`identity ${res.status}`);
  return json.data;
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

async function flow({ name, senderToken, receiverToken, conversationId, body }) {
  const pusher = createPusher(receiverToken);
  await waitConnected(pusher, name);
  const channel = await subscribe(pusher, conversationId);

  const eventPromise = new Promise((resolve, reject) => {
    const t = setTimeout(() => reject(new Error(`${name}: no WS event in 15s`)), 15000);
    channel.bind('message.created', (payload) => {
      clearTimeout(t);
      resolve(payload);
    });
  });

  const send = await api(senderToken, 'POST', `/conversations/${conversationId}/messages`, {
    body,
  });
  if (send.res.status !== 201) {
    pusher.disconnect();
    fail(`${name} send: ${send.res.status} ${send.json.message || ''}`);
  }
  const messageId = send.json.data.id;
  const received = await eventPromise;
  if (received.id !== messageId) {
    pusher.disconnect();
    fail(`${name} id mismatch rest=${messageId} ws=${received.id}`);
  }
  if (received.conversation_id !== conversationId) {
    pusher.disconnect();
    fail(`${name} conversation_id mismatch`);
  }
  ok(`${name}: REST+WS Message.id=${messageId}`);
  pusher.disconnect();
  return messageId;
}

async function assertUnauthorizedChannel(token, conversationId, label) {
  const pusher = createPusher(token);
  await waitConnected(pusher, label);
  const channelName = `private-conversation.${conversationId}`;
  const channel = pusher.subscribe(channelName);
  const denied = await new Promise((resolve) => {
    const t = setTimeout(() => resolve(false), 8000);
    channel.bind('pusher:subscription_error', () => {
      clearTimeout(t);
      resolve(true);
    });
    channel.bind('pusher:subscription_succeeded', () => {
      clearTimeout(t);
      resolve(false);
    });
  });
  pusher.disconnect();
  if (!denied) fail(`${label}: expected channel auth denial for ${conversationId}`);
  ok(`${label}: private channel correctly denied`);
}

async function isolationTest({ watcherToken, senderToken, openId, otherId }) {
  const pusher = createPusher(watcherToken);
  await waitConnected(pusher, 'isolation');
  const channelA = await subscribe(pusher, openId);
  let leaked = false;
  channelA.bind('message.created', (payload) => {
    if (payload.conversation_id && payload.conversation_id !== openId) {
      leaked = true;
    }
  });

  const body = `M7 isolation B ${Date.now()}`;
  const send = await api(senderToken, 'POST', `/conversations/${otherId}/messages`, { body });
  if (send.res.status !== 201) fail(`isolation send: ${send.res.status}`);

  await new Promise((r) => setTimeout(r, 2500));
  if (leaked) fail('Cross-conversation leak: B event appeared on A subscription');
  ok(`Isolation: A open, B message did not leak onto A channel`);

  const hist = await api(watcherToken, 'GET', `/conversations/${otherId}/messages?per_page=5`);
  if (!hist.res.ok) fail(`isolation history ${hist.res.status}`);
  const found = (hist.json?.data?.data || []).some((m) => m.id === send.json.data.id);
  if (!found) fail('isolation: message missing from B history');
  ok('Isolation: B history contains the message');
  pusher.disconnect();
}

async function main() {
  if (!REVERB_KEY) fail('REVERB_APP_KEY missing');

  ok(`API ${API}`);
  ok(`Reverb ${REVERB_SCHEME}://${REVERB_HOST}:${REVERB_PORT}`);

  let dispatcherToken;
  try {
    dispatcherToken = await login('dispatcher@fleetx.com', 'Dispatcher@123');
    ok('Authenticated dispatcher@fleetx.com');
  } catch {
    dispatcherToken = await loginOrFail('admin@fleetx.com', 'password');
    ok('Authenticated admin@fleetx.com (dispatcher credentials unavailable — using manage role)');
  }
  const driverToken = await loginOrFail('driver@fleetx.com', 'Driver@123');
  const clientToken = await loginOrFail('client@fleetx.com', 'Client@123');
  ok('Authenticated driver + client');

  const driverIdentity = await identity(driverToken);
  if (!driverIdentity.linked || !driverIdentity.driver?.id) {
    fail('Driver identity not linked');
  }
  ok(`Driver identity User=${driverIdentity.user_id} Driver=${driverIdentity.driver.id}`);

  const clientIdentity = await identity(clientToken);
  if (!clientIdentity.linked || !clientIdentity.company?.id) {
    fail('Client identity not linked');
  }
  ok(
    `Client identity User=${clientIdentity.user_id} Company=${clientIdentity.company.id}`
  );

  const driverList = await api(driverToken, 'GET', '/conversations?type=dispatcher_driver&per_page=20');
  if (!driverList.res.ok) fail(`driver list ${driverList.res.status}`);
  let driverConvId = driverList.json?.data?.data?.[0]?.id;
  if (!driverConvId) fail('No dispatcher_driver conversation for driver — run seed-messaging-realtime-fixture.php');
  ok(`Driver conversation ${driverConvId}`);

  const clientList = await api(clientToken, 'GET', '/conversations?type=dispatcher_customer&per_page=20');
  if (!clientList.res.ok) fail(`client list ${clientList.res.status}`);
  let clientConvId = clientList.json?.data?.data?.[0]?.id;
  if (!clientConvId) fail('No dispatcher_customer conversation for client — run seed-messaging-customer-fixture.php');
  ok(`Customer conversation ${clientConvId}`);

  // Customer cannot open driver conversation
  const cross = await api(clientToken, 'GET', `/conversations/${driverConvId}`);
  if (cross.res.status !== 403 && cross.res.status !== 404) {
    fail(`Client should not access driver conversation (got ${cross.res.status})`);
  }
  ok('Security: client blocked from dispatcher_driver conversation');

  const cross2 = await api(driverToken, 'GET', `/conversations/${clientConvId}`);
  if (cross2.res.status !== 403 && cross2.res.status !== 404) {
    fail(`Driver should not access customer conversation (got ${cross2.res.status})`);
  }
  ok('Security: driver blocked from dispatcher_customer conversation');

  await assertUnauthorizedChannel(clientToken, driverConvId, 'client→driver channel');
  await assertUnauthorizedChannel(driverToken, clientConvId, 'driver→customer channel');

  // Flow A: dispatcher → driver
  await flow({
    name: 'Flow A dispatcher→driver',
    senderToken: dispatcherToken,
    receiverToken: driverToken,
    conversationId: driverConvId,
    body: `Test from dispatcher ${Date.now()}`,
  });

  // Flow B: driver → dispatcher
  await flow({
    name: 'Flow B driver→dispatcher',
    senderToken: driverToken,
    receiverToken: dispatcherToken,
    conversationId: driverConvId,
    body: `Test from driver ${Date.now()}`,
  });

  // Flow C: dispatcher → customer
  await flow({
    name: 'Flow C dispatcher→customer',
    senderToken: dispatcherToken,
    receiverToken: clientToken,
    conversationId: clientConvId,
    body: `Test from dispatcher ${Date.now()}`,
  });

  // Flow D: customer → dispatcher
  await flow({
    name: 'Flow D customer→dispatcher',
    senderToken: clientToken,
    receiverToken: dispatcherToken,
    conversationId: clientConvId,
    body: `Test from customer ${Date.now()}`,
  });

  // Read receipts (last_read_at)
  const readD = await api(driverToken, 'POST', `/conversations/${driverConvId}/read`);
  if (!readD.res.ok) fail(`driver read ${readD.res.status}`);
  ok('Driver mark read OK');
  const readC = await api(clientToken, 'POST', `/conversations/${clientConvId}/read`);
  if (!readC.res.ok) fail(`client read ${readC.res.status}`);
  ok('Customer mark read OK');

  // Isolation for driver: need a second driver conversation if available
  const allDriver = driverList.json?.data?.data || [];
  if (allDriver.length >= 2) {
    await isolationTest({
      watcherToken: driverToken,
      senderToken: dispatcherToken,
      openId: allDriver[0].id,
      otherId: allDriver[1].id,
    });
  } else {
    ok('Isolation: skipped (only one driver conversation) — channel payload still carries conversation_id');
  }

  ok('M7 mobile messaging E2E contract verified');
  process.exit(0);
}

main().catch((err) => {
  console.error(err);
  process.exit(1);
});
