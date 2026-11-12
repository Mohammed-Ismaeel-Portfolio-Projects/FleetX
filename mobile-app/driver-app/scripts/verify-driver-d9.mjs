/**
 * DRIVER-D9 — UX + security + production hardening.
 * Run: npm run test:driver-d9
 */

import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const root = path.join(__dirname, '..');

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

function read(rel) {
  return fs.readFileSync(path.join(root, rel), 'utf8');
}

console.log('=== DRIVER-D9 Hardening ===\n');

const work = read('src/app/(tabs)/index.tsx');
const trip = read('src/app/active-trip/[id].tsx');
const pod = read('src/app/pod/[id].tsx');
const verify = read('src/app/verify-trip/[id].tsx');
const complete = read('src/app/delivery-complete/[id].tsx');
const issueSheet = read('src/features/pod/ReportIssueSheet.tsx');
const motion = read('src/lib/safety/drivingMotion.ts');
const assignmentState = read('src/lib/driver/assignmentState.ts');
const network = read('src/lib/network/useNetworkStatus.ts');
const pkg = JSON.parse(read('package.json'));

check('Driving motion utility exists', () => {
  assert.match(motion, /MOVING_SPEED_KMH/);
  assert.match(motion, /isVehicleMoving/);
  assert.match(motion, /MOTION_DEFER_HINT/);
});

check('Assignment state helpers for work labels and phase gates', () => {
  assert.match(assignmentState, /workOperationalLabel/);
  assert.match(assignmentState, /verifyPhaseAllowed/);
  assert.match(assignmentState, /podEntryAllowed/);
});

check('Work Home: operational state + next hint + offline + alerts', () => {
  assert.match(work, /workOperationalLabel/);
  assert.match(work, /work-operational-state/);
  assert.match(work, /work-next-hint/);
  assert.match(work, /OfflineBanner|work-offline/);
  assert.match(work, /work-unread-alerts/);
});

check('Active Trip: motion-gated CTAs + pull refresh + offline', () => {
  assert.match(trip, /active-trip-motion-hint/);
  assert.match(trip, /isVehicleMoving/);
  assert.match(trip, /RefreshControl/);
  assert.match(trip, /active-trip-offline/);
  assert.match(trip, /openIssueSheet/);
});

check('POD: getOutcome gate + submit lock + loading/error states', () => {
  assert.match(pod, /getOutcome/);
  assert.match(pod, /podEntryAllowed/);
  assert.match(pod, /submitLock/);
  assert.match(pod, /pod-loading|pod-gate-error/);
});

check('Verify: phase gate + scan debounce + gate loading', () => {
  assert.match(verify, /verifyPhaseAllowed/);
  assert.match(verify, /SCAN_DEBOUNCE_MS/);
  assert.match(verify, /verify-loading|verify-gate-error/);
  assert.match(verify, /getAssignment/);
});

check('Delivery complete: server confirmation fetch', () => {
  assert.match(complete, /getAssignment/);
  assert.match(complete, /delivery-complete-loading/);
  assert.match(complete, /delivery-complete-error/);
  assert.doesNotMatch(complete, /useLocalSearchParams.*ref.*only/);
});

check('Issue sheet: submit lock ref', () => {
  assert.match(issueSheet, /submitLock/);
});

check('Network status hook (not offline-first engine)', () => {
  assert.match(network, /NetInfo/);
  assert.match(network, /useNetworkStatus/);
});

check('Package scripts include D9 chain', () => {
  assert.ok(pkg.scripts['test:driver-d9']);
  assert.ok(pkg.scripts['test:d9-hardening']);
});

check('No Client/Ops API shortcuts introduced', () => {
  const src = work + trip + pod + verify + complete;
  assert.doesNotMatch(src, /\/client\/notifications/);
  assert.doesNotMatch(src, /['"`]\/assignments(?:\/|['"`?])/);
});

console.log('');
console.log(`DRIVER-D9: ${passed} passed, ${failed} failed`);
if (failed > 0) process.exit(1);
console.log('DRIVER-D9 VERDICT: PASS');
