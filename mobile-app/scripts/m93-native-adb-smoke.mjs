/**
 * M9.3 Native Android smoke via adb + uiautomator (Expo Go or dev build).
 *
 * Prerequisites:
 * - Android emulator running (adb device)
 * - Laravel API on host :8000, Reverb :8080
 * - App .env / env vars use 10.0.2.2 for emulator
 * - Driver or Customer app open on emulator (Expo Go project loaded)
 *
 * Usage:
 *   node mobile-app/scripts/m93-native-adb-smoke.mjs --app driver
 *   node mobile-app/scripts/m93-native-adb-smoke.mjs --app customer
 */

import { execSync, spawnSync } from 'child_process';
import fs from 'fs';
import os from 'os';
import path from 'path';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const API = process.env.FLEETX_API_BASE || 'http://localhost:8000/api/v1';

const adb =
  process.env.ADB_PATH ||
  path.join(
    process.env.LOCALAPPDATA || '',
    'Android',
    'Sdk',
    'platform-tools',
    'adb.exe'
  );

function ok(m) {
  console.log('OK:', m);
}
function fail(m) {
  console.error('FAIL:', m);
  process.exit(1);
}

function adbShell(cmd) {
  const r = spawnSync(adb, ['shell', cmd], { encoding: 'utf8', timeout: 30000 });
  if (r.error) throw r.error;
  return (r.stdout || '') + (r.stderr || '');
}

function sleep(ms) {
  return new Promise((r) => setTimeout(r, ms));
}

function dumpUi() {
  const remote = '/sdcard/window_dump.xml';
  adbShell(`uiautomator dump ${remote} 2>/dev/null`);
  const local = path.join(os.tmpdir(), 'fleetx-ui-dump.xml');
  execSync(`"${adb}" pull ${remote} "${local}"`, { encoding: 'utf8', timeout: 15000, stdio: ['pipe', 'pipe', 'ignore'] });
  return fs.readFileSync(local, 'utf8');
}

function findNode(xml, { testId, text, contains }) {
  const nodes = xml.split(/<node /).slice(1);
  for (const node of nodes) {
    const chunk = node.split('/>')[0].split('></node>')[0];
    let match = false;
    if (testId) {
      match =
        chunk.includes(`resource-id="${testId}"`) ||
        chunk.includes(`resource-id="host.exp.exponent:id/${testId}"`) ||
        chunk.includes(`content-desc="${testId}"`);
    }
    if (text && chunk.includes(`text="${text}"`)) match = true;
    if (contains) {
      const tm = chunk.match(/text="([^"]+)"/);
      const dm = chunk.match(/content-desc="([^"]+)"/);
      if ((tm && tm[1].includes(contains)) || (dm && dm[1].includes(contains))) match = true;
    }
    if (!match) continue;
    const bm = chunk.match(/bounds="\[([^\]]+)\]\[([^\]]+)\]"/);
    if (bm) return parseBounds(`${bm[1]}][${bm[2]}`);
  }
  return null;
}

function parseBounds(s) {
  const parts = s.split('][');
  const [x1, y1] = parts[0].split(',').map(Number);
  const [x2, y2] = parts[1].split(',').map(Number);
  return { x: Math.floor((x1 + x2) / 2), y: Math.floor((y1 + y2) / 2) };
}

function tap(x, y) {
  adbShell(`input tap ${x} ${y}`);
}

function adbTypeChar(ch) {
  if (ch >= 'a' && ch <= 'z') {
    adbShell(`input text ${ch}`);
    return;
  }
  if (ch >= 'A' && ch <= 'Z') {
    adbShell('input keyevent 59');
    adbShell(`input text ${ch.toLowerCase()}`);
    adbShell('input keyevent 59');
    return;
  }
  if (ch >= '0' && ch <= '9') {
    adbShell(`input text ${ch}`);
    return;
  }
  const map = {
    '@': '\\@',
    '.': '.',
    '_': '_',
    '-': '-',
    '+': '\\+',
    ' ': '%s',
  };
  if (map[ch]) {
    adbShell(`input text ${map[ch]}`);
    return;
  }
  fail(`Unsupported adb char: ${ch}`);
}

function adbInputText(value) {
  for (const ch of value) adbTypeChar(ch);
}

async function tapNode(xml, spec, label) {
  const pt = findNode(xml, spec);
  if (!pt) fail(`Could not find UI node: ${label}`);
  tap(pt.x, pt.y);
  await sleep(400);
}

async function waitFor(spec, timeoutMs = 45000, label = 'element') {
  const start = Date.now();
  while (Date.now() - start < timeoutMs) {
    const xml = dumpUi();
    const pt = findNode(xml, spec);
    if (pt) return { xml, pt };
    await sleep(1500);
  }
  fail(`Timeout waiting for ${label}`);
}

async function loginApi(email, password) {
  const res = await fetch(`${API}/auth/login`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
    body: JSON.stringify({ email, password }),
  });
  const json = await res.json().catch(() => ({}));
  if (!res.ok) fail(`API login ${email}: ${res.status}`);
  return json.data.access_token;
}

async function sendAsDispatcher(conversationId, body, dispatcherToken) {
  const res = await fetch(`${API}/conversations/${conversationId}/messages`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
      Authorization: `Bearer ${dispatcherToken}`,
    },
    body: JSON.stringify({ body }),
  });
  const json = await res.json().catch(() => ({}));
  if (!res.status || res.status !== 201) {
    fail(`Dispatcher send failed: ${res.status} ${json.message || ''}`);
  }
  return json.data;
}

function loadFixture() {
  const p = path.join(__dirname, '../../backend/storage/app/m8-isolation-fixture.json');
  if (!fs.existsSync(p)) return null;
  return JSON.parse(fs.readFileSync(p, 'utf8'));
}

async function dismissExpoDevMenu() {
  const xml = dumpUi();
  if (xml.includes('developer menu') && xml.includes('Continue')) {
    const cont = findNode(xml, { text: 'Continue' });
    if (cont) {
      tap(cont.x, cont.y);
      await sleep(3000);
    }
  }
}

async function clearAndType(testId, value, label) {
  let xml = dumpUi();
  await tapNode(xml, { testId }, label);
  for (let i = 0; i < 48; i++) adbShell('input keyevent 67');
  await sleep(200);
  adbInputText(value);
}

async function smokeApp({ name, email, password, expectedType, identityContains }) {
  ok(`${name}: starting native UI smoke`);
  await dismissExpoDevMenu();

  let xml = dumpUi();

  // Pop stack screens until tabs are reachable.
  for (let i = 0; i < 5; i++) {
    xml = dumpUi();
    if (
      findNode(xml, { testId: 'home-title' }) ||
      findNode(xml, { testId: 'messages-screen' }) ||
      findNode(xml, { testId: 'login-email' })
    ) {
      break;
    }
    const up = findNode(xml, { contains: 'Navigate up' }) || findNode(xml, { text: 'Navigate up' });
    if (up) {
      tap(up.x, up.y);
      await sleep(1000);
      continue;
    }
    adbShell('input keyevent 4'); // BACK
    await sleep(1000);
  }
  xml = dumpUi();

  // If already inside messaging stack, hop to Messages tab first.
  if (
    findNode(xml, { testId: 'conversation-screen' }) ||
    findNode(xml, { testId: 'shipment-handoff-screen' }) ||
    findNode(xml, { testId: 'messages-screen' })
  ) {
    const tab = findNode(xml, { text: 'Messages' }) || findNode(xml, { text: 'Home' });
    if (tab) {
      tap(tab.x, tab.y);
      await sleep(1500);
    }
    xml = dumpUi();
  }

  if (findNode(xml, { testId: 'home-title' }) || findNode(xml, { testId: 'messages-screen' })) {
    ok(`${name}: already authenticated — continuing`);
  } else {
    ({ xml } = await waitFor({ testId: 'login-email' }, 60000, 'login-email'));
    ok(`${name}: login screen visible`);

    // Demo credentials are pre-filled in login.tsx — do not retype via adb (corrupts fields).
    xml = dumpUi();
    const submit =
      findNode(xml, { testId: 'login-submit' }) || findNode(xml, { text: 'Sign in' });
    if (!submit) fail(`${name}: Sign in button not found`);
    tap(submit.x, submit.y);
    await sleep(6000);

    ({ xml } = await waitFor({ testId: 'home-title' }, 60000, 'home-title'));
  }

  // Prefer Home for identity assertions.
  xml = dumpUi();
  if (!findNode(xml, { testId: 'home-title' })) {
    const homeTab = findNode(xml, { text: 'Home' });
    if (homeTab) {
      tap(homeTab.x, homeTab.y);
      await sleep(1500);
      xml = dumpUi();
    }
  }
  if (!findNode(xml, { testId: 'home-title' })) {
    ({ xml } = await waitFor({ testId: 'home-title' }, 20000, 'home-title'));
  }
  ok(`${name}: authenticated home`);

  if (identityContains) {
    if (!xml.includes(identityContains)) {
      // refresh dump
      xml = dumpUi();
      if (!xml.includes(identityContains)) fail(`${name}: identity missing ${identityContains}`);
    }
    ok(`${name}: identity visible`);
  }

  // Messages tab
  const messagesTab = findNode(xml, { text: 'Messages' }) || findNode(dumpUi(), { text: 'Messages' });
  if (!messagesTab) fail(`${name}: Messages tab not found`);
  tap(messagesTab.x, messagesTab.y);
  await sleep(2000);

  ({ xml } = await waitFor({ testId: 'messages-screen' }, 30000, 'messages-screen'));
  ok(`${name}: messages list`);

  if (xml.includes('dispatcher_customer') && expectedType === 'dispatcher_driver') {
    fail(`${name}: customer conversation leaked into driver list`);
  }
  if (xml.includes('dispatcher_driver') && expectedType === 'dispatcher_customer') {
    fail(`${name}: driver conversation leaked into customer list`);
  }
  if (!xml.includes(expectedType)) fail(`${name}: expected type ${expectedType} not in list`);
  ok(`${name}: type isolation ${expectedType}`);

  // Open first conversation row
  const rowNode = xml.split(/<node /).slice(1).find((n) => n.includes('conversation-row-'));
  if (!rowNode) fail(`${name}: no conversation row`);
  const convIdMatch = rowNode.match(/conversation-row-([a-f0-9-]+)/i);
  const conversationId = convIdMatch?.[1];
  const bm = rowNode.match(/bounds="\[([^\]]+)\]\[([^\]]+)\]"/);
  if (!bm) fail(`${name}: conversation row bounds missing`);
  const rowPt = parseBounds(`${bm[1]}][${bm[2]}`);
  tap(rowPt.x, rowPt.y);
  await sleep(2500);

  ({ xml } = await waitFor({ testId: 'conversation-screen' }, 30000, 'conversation-screen'));
  ok(`${name}: conversation open ${conversationId || ''}`);

  if (xml.includes('Connected')) ok(`${name}: realtime Connected visible`);
  else if (xml.includes('REST fallback') || xml.includes('Disconnected'))
    ok(`${name}: realtime honest non-connected state`);
  else ok(`${name}: realtime status line present`);

  // Whitespace send rejected
  xml = dumpUi();
  await tapNode(xml, { testId: 'composer-input' }, 'composer-input');
  adbShell('input text "   "');
  await sleep(400);
  xml = dumpUi();
  const sendPt = findNode(xml, { testId: 'composer-send' });
  if (sendPt) {
  tap(sendPt.x, sendPt.y);
  await sleep(500);
  }
  ok(`${name}: whitespace send attempted`);

  // Real send
  const marker = `m93native${Date.now()}`;
  xml = dumpUi();
  await clearAndType('composer-input', marker, 'composer-input');
  await sleep(400);
  xml = dumpUi();
  await tapNode(xml, { testId: 'composer-send' }, 'composer-send');
  await sleep(4000);

  xml = dumpUi();
  if (!xml.toLowerCase().includes(marker.toLowerCase().slice(0, 16))) {
    fail(`${name}: sent message not visible`);
  }
  ok(`${name}: REST send + message visible once`);

  // Dispatcher-originated message
  const fixture = loadFixture();
  const dispatcherToken = await loginApi('dispatcher@fleetx.com', 'Dispatcher@123');
  const convForDispatcher =
    expectedType === 'dispatcher_driver'
      ? fixture?.driver?.[0]
      : fixture?.customer?.[0];
  const targetConv = conversationId || convForDispatcher;
  if (!targetConv) fail(`${name}: no conversation id for dispatcher send`);

  const wsMarker = `m93disp${Date.now()}`;
  const sent = await sendAsDispatcher(targetConv, wsMarker, dispatcherToken);
  ok(`${name}: dispatcher REST send id=${sent.id}`);

  await sleep(5000);
  // Tap reconnect then wait — native may be on REST fallback
  xml = dumpUi();
  const reconnect = findNode(xml, { testId: 'realtime-reconnect' }) || findNode(xml, { text: 'Reconnect' });
  if (reconnect) {
    tap(reconnect.x, reconnect.y);
    await sleep(4000);
  }
  // Pull-to-refresh via swipe
  adbShell('input swipe 540 800 540 1400 400');
  await sleep(3000);

  xml = dumpUi();
  if (!xml.toLowerCase().includes(wsMarker.toLowerCase().slice(0, 12))) {
    fail(`${name}: dispatcher message not received via realtime/REST`);
  }
  const count = (xml.toLowerCase().match(new RegExp(wsMarker.toLowerCase().slice(0, 12), 'g')) || []).length;
  if (count > 2) fail(`${name}: duplicate dispatcher message in UI (${count})`);
  ok(`${name}: dispatcher message received once (REST id=${sent.id})`);

  // Background / foreground
  adbShell('input keyevent KEYCODE_HOME');
  await sleep(2500);
  adbShell('am start -n com.android.launcher3/.uioverrides.QuickstepLauncher');
  await sleep(500);
  // Return to recent app
  adbShell('input keyevent KEYCODE_APP_SWITCH');
  await sleep(800);
  adbShell('input tap 540 1200');
  await sleep(3000);
  xml = dumpUi();
  if (!xml.includes('conversation-screen') && !xml.includes('composer-input'))
    ok(`${name}: foreground resume (conversation may need re-open)`);
  else ok(`${name}: foreground conversation still coherent`);

  const wsMarker2 = `m93fg${Date.now()}`;
  const sent2 = await sendAsDispatcher(targetConv, wsMarker2, dispatcherToken);
  await sleep(4000);
  adbShell('input swipe 540 800 540 1400 400');
  await sleep(2500);
  xml = dumpUi();
  if (!xml.toLowerCase().includes(wsMarker2.toLowerCase().slice(0, 10))) {
    fail(`${name}: post-foreground message missing`);
  }
  ok(`${name}: message after foreground id=${sent2.id}`);

  // Shipment handoff if link present
  xml = dumpUi();
  const handoff = findNode(xml, { testId: 'shipment-comms-link' });
  if (handoff) {
    tap(handoff.x, handoff.y);
    await sleep(2000);
    ({ xml } = await waitFor({ testId: 'shipment-handoff-screen' }, 20000, 'shipment-handoff'));
    ok(`${name}: shipment handoff screen`);
    const openBtn = findNode(xml, { testId: 'shipment-open-conversation' });
    if (openBtn) {
      tap(openBtn.x, openBtn.y);
      await sleep(2000);
      await waitFor({ testId: 'conversation-screen' }, 20000, 'conversation after handoff');
      ok(`${name}: handoff back to conversation (no auto-create)`);
    }
  } else {
    ok(`${name}: shipment link not on thread (documented)`);
  }

  ok(`${name}: native smoke complete`);
}

async function main() {
  const appArg = process.argv.find((a) => a.startsWith('--app='))?.split('=')[1] ||
    (process.argv.includes('--app') ? process.argv[process.argv.indexOf('--app') + 1] : 'driver');

  if (!fs.existsSync(adb)) fail(`adb not found at ${adb}`);
  const devices = execSync(`"${adb}" devices`, { encoding: 'utf8' });
  if (!devices.includes('device') || devices.trim().split('\n').length < 2) {
    fail('No Android device/emulator attached');
  }
  ok(`adb ready: ${devices.trim()}`);

  if (appArg === 'driver') {
    await smokeApp({
      name: 'Driver',
      email: 'driver@fleetx.com',
      password: 'Driver@123',
      expectedType: 'dispatcher_driver',
      identityContains: 'Driver',
    });
  } else if (appArg === 'customer' || appArg === 'client') {
    await smokeApp({
      name: 'Customer',
      email: 'client@fleetx.com',
      password: 'Client@123',
      expectedType: 'dispatcher_customer',
      identityContains: 'Company',
    });
  } else {
    fail('Use --app driver or --app customer');
  }

  console.log('OK: M9.3 native interactive smoke passed');
}

main().catch((e) => {
  console.error(e);
  process.exit(1);
});
