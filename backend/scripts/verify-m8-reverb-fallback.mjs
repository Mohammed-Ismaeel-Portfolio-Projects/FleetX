/**
 * M8 Reverb disconnect honesty (transport-level).
 * Confirms REST send still works while WS subscribe fails / is unavailable.
 * Does not claim Expo UI status text — that requires interactive browser.
 *
 * Usage (Reverb RUNNING first for baseline, then STOP Reverb and re-run with FLEETX_EXPECT_WS=0):
 *   node scripts/verify-m8-reverb-fallback.mjs
 */

import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';
import Pusher from '../../dashboard-web/node_modules/pusher-js/dist/node/pusher.js';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
function loadEnvFile(filePath) {
  if (!fs.existsSync(filePath)) return;
  for (const line of fs.readFileSync(filePath, 'utf8').split(/\r?\n/)) {
    const t = line.trim();
    if (!t || t.startsWith('#')) continue;
    const eq = t.indexOf('=');
    if (eq < 0) continue;
    const k = t.slice(0, eq).trim();
    let v = t.slice(eq + 1).trim().replace(/^['"]|['"]$/g, '');
    if (!(k in process.env)) process.env[k] = v;
  }
}
loadEnvFile(path.join(__dirname, '../.env'));

const API = process.env.FLEETX_API_BASE || 'http://localhost:8000/api/v1';
const EXPECT_WS = process.env.FLEETX_EXPECT_WS !== '0';
const REVERB_KEY = process.env.REVERB_APP_KEY;
const REVERB_HOST = process.env.REVERB_HOST || 'localhost';
const REVERB_PORT = Number(process.env.REVERB_PORT || 8080);

function ok(m) {
  console.log('OK:', m);
}
function fail(m) {
  console.error('FAIL:', m);
  process.exit(1);
}

async function login(email, password) {
  const res = await fetch(`${API}/auth/login`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
    body: JSON.stringify({ email, password }),
  });
  const json = await res.json();
  if (!res.ok) fail(`login ${res.status}`);
  return json.data.access_token;
}

async function main() {
  const fixture = JSON.parse(
    fs.readFileSync(path.join(__dirname, '../storage/app/m8-isolation-fixture.json'), 'utf8')
  );
  const token = await login('driver@fleetx.com', 'Driver@123');
  const conv = fixture.driver[0];

  const body = `M8 REST fallback ${Date.now()}`;
  const send = await fetch(`${API}/conversations/${conv}/messages`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
      Authorization: `Bearer ${token}`,
    },
    body: JSON.stringify({ body }),
  });
  const sendJson = await send.json();
  if (send.status !== 201) fail(`REST send ${send.status}`);
  ok(`REST persist id=${sendJson.data.id} (usable without WS)`);

  let wsOk = false;
  try {
    const pusher = new Pusher(REVERB_KEY, {
      wsHost: REVERB_HOST,
      wsPort: REVERB_PORT,
      wssPort: REVERB_PORT,
      forceTLS: false,
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
            body: JSON.stringify({ socket_id: socketId, channel_name: channel.name }),
          })
            .then(async (r) => callback(null, await r.json()))
            .catch((e) => callback(e, null));
        },
      }),
    });
    await new Promise((resolve, reject) => {
      const t = setTimeout(() => reject(new Error('timeout')), 5000);
      pusher.connection.bind('connected', () => {
        clearTimeout(t);
        resolve();
      });
      pusher.connection.bind('error', () => {
        clearTimeout(t);
        reject(new Error('error'));
      });
      pusher.connection.bind('unavailable', () => {
        clearTimeout(t);
        reject(new Error('unavailable'));
      });
    });
    wsOk = true;
    pusher.disconnect();
  } catch {
    wsOk = false;
  }

  if (EXPECT_WS && !wsOk) fail('Expected WebSocket connect (Reverb up)');
  if (!EXPECT_WS && wsOk) fail('Expected WebSocket unavailable (Reverb down)');
  if (EXPECT_WS) ok('WebSocket connected (Connected path)');
  else ok('WebSocket unavailable — REST fallback path (do not claim Connected)');

  ok('M8 reverb fallback check done');
}

main().catch((e) => {
  console.error(e);
  process.exit(1);
});
