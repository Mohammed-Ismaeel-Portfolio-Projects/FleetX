/**
 * DRIVER-WORKFLOW-01 — Assigned journey polish / handoff static checks.
 */
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const root = path.join(__dirname, '..');
const backend = path.join(root, '..', '..', 'backend');

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

function read(rel, base = root) {
  return fs.readFileSync(path.join(base, rel), 'utf8');
}

console.log('=== DRIVER-WORKFLOW-01 ===\n');

check('Identity handoff uses driver work APIs only (no client driver_id trust)', () => {
  const work = read('src/features/driverApi/work.ts');
  assert.match(work, /\/driver\/work\/active/);
  assert.match(work, /\/driver\/assignments\//);
  assert.doesNotMatch(work, /driver_id\s*:/);
  assert.doesNotMatch(work, /\/client\//);
});

check('pickup_verified exposes Continue toward delivery refresh CTA', () => {
  const phase = read('src/features/driverApi/executionPhase.ts');
  const trip = read('src/app/active-trip/[id].tsx');
  assert.match(phase, /case 'pickup_verified'/);
  assert.match(phase, /kind: 'refresh'/);
  assert.match(phase, /Continue toward delivery/);
  assert.match(trip, /action\.kind === 'refresh'/);
});

check('QR UX distinguishes wrong/already/network verification errors', () => {
  const verify = read('src/app/verify-trip/[id].tsx');
  assert.match(verify, /does not belong to this assignment/);
  assert.match(verify, /Pickup has already been verified/);
  assert.match(verify, /Pickup verification could not be completed/);
  assert.match(verify, /Position the QR/);
  assert.match(verify, /Verifying with server/);
  assert.match(verify, /Continue toward delivery/);
});

check('Delivery complete still re-fetches authoritative assignment state', () => {
  const done = read('src/app/delivery-complete/[id].tsx');
  assert.match(done, /getAssignment\(assignmentId\)/);
  assert.match(done, /key !== 'completed'/);
  assert.match(done, /refParam|ref\?:/);
});

check('Driver API index no longer claims D2 is stubbed', () => {
  const index = read('src/features/driverApi/index.ts');
  assert.doesNotMatch(index, /stubbed/i);
  assert.match(index, /\/api\/v1\/driver/);
});

check('Backend verify advances pickup to delivery_en_route after QR', () => {
  const action = read(
    'app/Actions/Driver/VerifyDriverAssignmentAction.php',
    backend
  );
  assert.match(action, /PICKUP_VERIFIED/);
  assert.match(action, /DELIVERY_EN_ROUTE/);
  assert.match(action, /forDriver/);
});

console.log(`\n${passed} passed, ${failed} failed`);
process.exit(failed ? 1 : 0);
