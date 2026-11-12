/**
 * M6 runtime verification: real WebSocket delivery via Laravel Reverb.
 *
 * Prerequisites:
 * - php artisan serve (API)
 * - php artisan reverb:start
 * - Demo users seeded (IdentityLinkSeeder / RolesSeeder)
 *
 * Usage (from backend/):
 *   node scripts/verify-messaging-realtime.mjs
 *
 * Exit 0 = Client B received MessageCreated over WebSocket with matching Message.id
 */

import Pusher from '../../dashboard-web/node_modules/pusher-js/dist/node/pusher.js';

const API = process.env.FLEETX_API_BASE || 'http://localhost:8000/api/v1';
const REVERB_KEY = process.env.REVERB_APP_KEY || process.env.VITE_REVERB_APP_KEY;
const REVERB_HOST = process.env.REVERB_HOST || 'localhost';
const REVERB_PORT = Number(process.env.REVERB_PORT || 8080);
const REVERB_SCHEME = process.env.REVERB_SCHEME || 'http';

const SENDER = {
  email: process.env.FLEETX_SENDER_EMAIL || 'dispatcher@fleetx.com',
  password: process.env.FLEETX_SENDER_PASSWORD || 'Dispatcher@123',
};
const RECEIVER = {
  email: process.env.FLEETX_RECEIVER_EMAIL || 'admin@fleetx.com',
  password: process.env.FLEETX_RECEIVER_PASSWORD || 'password',
};

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
    fail(`Login failed for ${email}: ${res.status} ${json.message || ''}`);
  }
  return json.data.access_token;
}

async function api(token, method, path, body) {
  const res = await fetch(`${API}${path}`, {
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

async function main() {
  if (!REVERB_KEY) {
    fail('REVERB_APP_KEY is not set. Export it from backend .env or pass env vars.');
  }

  ok(`API ${API}`);
  ok(`Reverb ws://${REVERB_HOST}:${REVERB_PORT} key=${REVERB_KEY.slice(0, 4)}…`);

  const senderToken = await login(SENDER.email, SENDER.password);
  const receiverToken = await login(RECEIVER.email, RECEIVER.password);
  ok('Authenticated sender + receiver');

  // Find an open conversation the sender can manage, or create via drivers list.
  let conversationId = process.env.FLEETX_CONVERSATION_ID || null;

  if (!conversationId) {
    const list = await api(senderToken, 'GET', '/conversations?per_page=5');
    if (!list.res.ok) fail(`List conversations: ${list.res.status}`);
    conversationId = list.json?.data?.data?.[0]?.id || null;
  }

  if (!conversationId) {
    // Resolve a driver shipment for find-or-create
    const drivers = await api(senderToken, 'GET', '/drivers?per_page=20&is_active=true');
    if (!drivers.res.ok) fail(`List drivers: ${drivers.res.status}`);
    const rows = drivers.json?.data?.data || [];
    const linked = rows.find(
      (d) => d.user_id && d.active_assignment?.shipment?.id
    );
    if (!linked) {
      fail('No linked driver with active shipment — seed IdentityLinkSeeder / simulation first.');
    }
    const open = await api(senderToken, 'POST', '/conversations', {
      type: 'dispatcher_driver',
      shipment_id: linked.active_assignment.shipment.id,
      driver_user_id: Number(linked.user_id),
    });
    if (!open.res.ok && open.res.status !== 201 && open.res.status !== 200) {
      fail(`Open conversation: ${open.res.status} ${open.json.message || ''}`);
    }
    conversationId = open.json.data.id;
  }

  ok(`Conversation ${conversationId}`);

  const channelName = `private-conversation.${conversationId}`;
  const uniqueBody = `M6 realtime probe ${Date.now()}`;

  let received = null;
  let authDenied = false;

  const pusher = new Pusher(REVERB_KEY, {
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
            Authorization: `Bearer ${receiverToken}`,
          },
          body: JSON.stringify({
            socket_id: socketId,
            channel_name: channel.name,
          }),
        })
          .then(async (response) => {
            if (!response.ok) {
              authDenied = true;
              callback(new Error(`auth ${response.status}`), null);
              return;
            }
            callback(null, await response.json());
          })
          .catch((err) => callback(err, null));
      },
    }),
  });

  await new Promise((resolve, reject) => {
    const t = setTimeout(() => reject(new Error('Pusher connection timeout')), 15000);
    pusher.connection.bind('connected', () => {
      clearTimeout(t);
      resolve();
    });
    pusher.connection.bind('error', (err) => {
      clearTimeout(t);
      reject(err?.error || err || new Error('Pusher error'));
    });
  });
  ok('Receiver WebSocket connected');

  const channel = pusher.subscribe(channelName);
  await new Promise((resolve, reject) => {
    const t = setTimeout(() => reject(new Error('Channel subscription timeout')), 15000);
    channel.bind('pusher:subscription_succeeded', () => {
      clearTimeout(t);
      resolve();
    });
    channel.bind('pusher:subscription_error', (status) => {
      clearTimeout(t);
      reject(new Error(`subscription_error ${status}`));
    });
  });
  if (authDenied) fail('Broadcast auth denied for receiver');
  ok(`Subscribed ${channelName}`);

  const eventPromise = new Promise((resolve, reject) => {
    const t = setTimeout(() => reject(new Error('No message.created within 15s')), 15000);
    channel.bind('message.created', (payload) => {
      clearTimeout(t);
      resolve(payload);
    });
  });

  const send = await api(senderToken, 'POST', `/conversations/${conversationId}/messages`, {
    body: uniqueBody,
  });
  if (send.res.status !== 201) {
    fail(`Send message: ${send.res.status} ${send.json.message || ''}`);
  }
  const messageId = send.json.data.id;
  ok(`REST persisted message ${messageId}`);

  received = await eventPromise;
  if (!received?.id) fail('Realtime payload missing id');
  if (received.id !== messageId) {
    fail(`Realtime id mismatch: ws=${received.id} rest=${messageId}`);
  }
  if (received.conversation_id !== conversationId) {
    fail('Realtime conversation_id mismatch');
  }
  if (received.body !== uniqueBody) {
    fail('Realtime body mismatch');
  }

  ok('End-to-end: REST persist → MessageCreated → WebSocket → receiver');
  ok('Message.id match confirmed (dedupe-ready)');

  // Isolation smoke: subscribe does not auto-switch; event carries conversation_id only.
  ok(`Isolation field conversation_id=${received.conversation_id}`);

  pusher.disconnect();
  process.exit(0);
}

main().catch((err) => {
  console.error(err);
  process.exit(1);
});
