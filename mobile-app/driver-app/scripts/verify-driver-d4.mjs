/**
 * DRIVER-D4 — Pickup / delivery execution phases + Active Trip CTAs.
 * Run: npm run test:driver-d4
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

console.log('=== DRIVER-D4 Execution Phases ===\n');

const workApi = read('src/features/driverApi/work.ts');
const mapper = read('src/features/driverApi/workMapper.ts');
const types = read('src/features/driverApi/workTypes.ts');
const phase = read('src/features/driverApi/executionPhase.ts');
const trip = read('src/app/active-trip/[id].tsx');

check('API: execution phase mutations are driver-scoped', () => {
  assert.match(workApi, /transitionExecutionPhase/);
  assert.match(workApi, /pickup\/arrive/);
  assert.match(workApi, /pickup\/loading-complete/);
  assert.match(workApi, /delivery\/arrive/);
  assert.match(workApi, /delivery\/unloading-complete/);
  assert.doesNotMatch(workApi, /['"`]\/assignments\/\$\{/);
  assert.doesNotMatch(workApi, /\/client\//);
});

check('Types/mapper: execution projection', () => {
  assert.match(types, /DriverExecutionSummary/);
  assert.match(types, /verificationBoundary/);
  assert.match(mapper, /mapExecution|verification_boundary/);
  assert.match(mapper, /execution:/);
});

check('Phase helpers: one CTA map + D5 verify gates', () => {
  assert.match(phase, /pickup_verifying/);
  assert.match(phase, /delivery_verifying/);
  assert.match(phase, /kind: 'verify'/);
  assert.match(phase, /kind: 'refresh'/);
  assert.match(phase, /pickup_verified/);
  assert.match(phase, /Continue toward delivery/);
  assert.match(phase, /Confirm arrival at pickup/);
  assert.match(phase, /Confirm loading complete/);
  assert.match(phase, /Scan pickup QR/);
  assert.match(phase, /Scan delivery QR/);
});

check('Active Trip: phase rendering + primary CTA', () => {
  assert.match(trip, /active-trip-phase/);
  assert.match(trip, /active-trip-phase-title/);
  assert.match(trip, /active-trip-phase-status/);
  assert.match(trip, /active-trip-cta/);
  assert.match(trip, /active-trip-cta-loading/);
  assert.match(trip, /active-trip-action-error/);
  assert.match(trip, /active-trip-stale/);
  assert.match(trip, /active-trip-next/);
  assert.match(trip, /transitionExecutionPhase/);
  assert.match(trip, /mutateLock/);
  assert.match(trip, /verify-trip/);
});

check('Active Trip: no fake GPS / map SDK / local verify bypass', () => {
  const mapView = read('src/features/tracking/DriverTripMapView.tsx');
  assert.match(trip + mapView, /active-trip-map-slot/);
  assert.doesNotMatch(trip, /react-native-maps/);
  assert.doesNotMatch(trip, /Mark Delivered|Proof of Delivery|Mark verified manually/i);
});

check('Reload / persistence uses server getAssignment', () => {
  assert.match(trip, /getAssignment/);
  assert.match(trip, /useFocusEffect/);
});

console.log('');
console.log(`DRIVER-D4: ${passed} passed, ${failed} failed`);
if (failed > 0) process.exit(1);
console.log('DRIVER-D4 VERDICT: PASS');
