/**
 * DRIVER-D3 — Acknowledge / Start / Active Trip.
 * Run: npm run test:driver-d3
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

console.log('=== DRIVER-D3 Execution Start ===\n');

const workApi = read('src/features/driverApi/work.ts');
const detail = read('src/app/assignment/[id].tsx');
const trip = read('src/app/active-trip/[id].tsx');
const layout = read('src/app/_layout.tsx');
const workScreen = read('src/app/(tabs)/index.tsx');

check('API: acknowledge + start use driver-scoped POSTs', () => {
  assert.match(workApi, /acknowledgeAssignment/);
  assert.match(workApi, /startAssignment/);
  assert.match(workApi, /\/acknowledge/);
  assert.match(workApi, /\/start/);
  assert.match(workApi, /apiClient\.post/);
  assert.doesNotMatch(workApi, /['"`]\/assignments\/\$\{/);
});

check('Assignment Detail: ack / start / loading / failure hooks', () => {
  assert.match(detail, /assignment-acknowledge/);
  assert.match(detail, /assignment-start/);
  assert.match(detail, /assignment-acknowledge-loading|mutating === 'ack'/);
  assert.match(detail, /assignment-start-loading|mutating === 'start'/);
  assert.match(detail, /assignment-action-error/);
  assert.match(detail, /statusKey === 'assigned'/);
  assert.match(detail, /statusKey === 'ready'/);
  assert.match(detail, /mutateLock/);
});

check('Active Trip shell: real status, map slot via TripMap component, no fake GPS SDK', () => {
  assert.match(trip, /active-trip/);
  assert.match(trip, /active-trip-header/);
  assert.match(trip, /active-trip-stale/);
  assert.match(trip, /active-trip-next/);
  const mapView = read('src/features/tracking/DriverTripMapView.tsx');
  assert.match(trip + mapView, /active-trip-map-slot/);
  assert.doesNotMatch(trip, /react-native-maps/);
  assert.doesNotMatch(trip, /turn-by-turn/i);
  assert.doesNotMatch(trip, /latitude:\s*\d+\.\d+/);
});

check('Routes registered', () => {
  assert.match(layout, /active-trip\/\[id\]/);
  assert.ok(fs.existsSync(path.join(root, 'src/app/active-trip/[id].tsx')));
});

check('Work Home can open Active Trip when in operation', () => {
  assert.match(workScreen, /in_operation/);
  assert.match(workScreen, /work-open-trip|Open Active Trip/);
});

check('No D5+ QR/POD in Active Trip / Assignment detail', () => {
  assert.doesNotMatch(detail + trip, /Scan QR|Mark Delivered|Proof of Delivery|QRScanner/i);
});

console.log('');
console.log(`DRIVER-D3: ${passed} passed, ${failed} failed`);
if (failed > 0) process.exit(1);
console.log('DRIVER-D3 VERDICT: PASS');
