/**
 * DRIVER-BOUNDARY — D1 API boundary lock (no Ops mutations / Client APIs).
 * Run: npm run test:driver-boundary
 */

import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const root = path.join(__dirname, '..');
const srcRoot = path.join(root, 'src');

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

function walk(dir, out = []) {
  for (const ent of fs.readdirSync(dir, { withFileTypes: true })) {
    const p = path.join(dir, ent.name);
    if (ent.isDirectory()) walk(p, out);
    else if (/\.(ts|tsx|js|jsx)$/.test(ent.name)) out.push(p);
  }
  return out;
}

function read(rel) {
  return fs.readFileSync(path.join(root, rel), 'utf8');
}

console.log('=== DRIVER-BOUNDARY (D1) ===\n');

const files = walk(srcRoot);
const allSrc = files.map((f) => fs.readFileSync(f, 'utf8')).join('\n');

check('Driver API boundary modules exist', () => {
  assert.ok(fs.existsSync(path.join(root, 'src/features/driverApi/index.ts')));
  assert.ok(fs.existsSync(path.join(root, 'src/features/driverApi/auth.ts')));
  assert.ok(fs.existsSync(path.join(root, 'src/features/driverApi/work.ts')));
  assert.ok(fs.existsSync(path.join(root, 'src/features/driverApi/messaging.ts')));
  assert.ok(fs.existsSync(path.join(root, 'src/features/driverApi/account.ts')));
  assert.ok(fs.existsSync(path.join(root, 'src/features/driverApi/notifications.ts')));
});

check('Work API uses Driver client only (no Ops / Client paths)', () => {
  const work = read('src/features/driverApi/work.ts');
  assert.match(work, /getActiveAssignment/);
  assert.match(work, /apiClient\.get/);
  assert.match(work, /\/driver\/work\/active/);
  assert.doesNotMatch(work, /['"`]\/assignments(?:\/|['"`?])/);
  assert.doesNotMatch(work, /['"`]\/client\//);
});

check('App screens do not import Client app paths', () => {
  assert.doesNotMatch(allSrc, /client-app/);
  assert.doesNotMatch(allSrc, /from ['\"]@\/features\/client/);
});

check('No Ops assignment mutation endpoints in Driver src', () => {
  // Allow /driver/assignments/* reads; forbid bare Ops /assignments mutations.
  assert.doesNotMatch(allSrc, /['"`]\/assignments(?:\/|['"`?])/);
  assert.doesNotMatch(allSrc, /PATCH.*\/assignments/i);
  assert.doesNotMatch(allSrc, /engine-enroll/);
});

check('No Client API prefix usage in Driver src', () => {
  assert.doesNotMatch(allSrc, /['"`]\/client\//);
  assert.doesNotMatch(allSrc, /\/client\/shipments/);
  assert.doesNotMatch(allSrc, /\/client\/notifications/);
});

check('No live-fleet / ops tracking create from Driver screens', () => {
  assert.doesNotMatch(allSrc, /live-fleet/);
  assert.doesNotMatch(allSrc, /\/tracking-logs/);
  assert.doesNotMatch(allSrc, /\/tracking-states/);
});

check('Tab screens prefer driverApi for work/messaging/account', () => {
  const workScreen = read('src/app/(tabs)/index.tsx');
  const messages = read('src/app/(tabs)/messages.tsx');
  const account = read('src/app/(tabs)/account.tsx');
  const conversation = read('src/app/conversation/[id].tsx');
  assert.match(workScreen, /features\/driverApi\/work/);
  assert.match(messages, /features\/driverApi\/messaging/);
  assert.match(account, /features\/driverApi\/account/);
  assert.match(conversation, /features\/driverApi\/messaging/);
});

check('Assignment detail + Active Trip exist; GPS map is Driver-scoped (no fleet/native maps)', () => {
  assert.ok(fs.existsSync(path.join(root, 'src/app/assignment/[id].tsx')));
  assert.ok(fs.existsSync(path.join(root, 'src/app/active-trip/[id].tsx')));
  // D7 POD surfaces are Driver-scoped under /pod and driverApi — not Ops DeliveryProof APIs
  assert.doesNotMatch(allSrc, /\/api\/v1\/issues|tracking-logs|live-fleet/);
  assert.doesNotMatch(allSrc, /react-native-maps/);
  assert.doesNotMatch(allSrc, /live-fleet|tracking\/live-fleet/);
  // Foreground location allowed for D6 Active Trip telemetry only
  if (allSrc.includes('expo-location')) {
    assert.match(allSrc, /TelemetryPublisher|createExpoLocationProvider|watchPosition/);
  }
});

check('Driver work uses /driver/ work contracts only', () => {
  const work = read('src/features/driverApi/work.ts');
  assert.match(work, /\/driver\/work\/active/);
  assert.match(work, /\/driver\/assignments\//);
  assert.match(work, /acknowledge/);
  assert.match(work, /\/start/);
  assert.doesNotMatch(work, /['"`]\/assignments(?:\/|['"`?])/);
  assert.doesNotMatch(work, /['"`]\/client\//);
});

console.log('');
console.log(`DRIVER-BOUNDARY: ${passed} passed, ${failed} failed`);
if (failed > 0) process.exit(1);
console.log('DRIVER-BOUNDARY VERDICT: PASS');
