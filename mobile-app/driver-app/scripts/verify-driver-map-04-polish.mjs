/**
 * DRIVER-MAP-04 — Final trip map UX polish.
 * Run: npm run test:driver-map-04
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

console.log('=== DRIVER-MAP-04 Polish ===\n');

const mapView = read('src/features/tracking/DriverTripMapView.tsx');
const timeline = read('src/features/tracking/mapTimeline.ts');
const trip = read('src/app/active-trip/[id].tsx');
const publisher = read('src/features/tracking/TelemetryPublisher.ts');
const location = read('src/features/tracking/locationProvider.ts');
const pkg = JSON.parse(read('package.json'));

check('No Trip entity / no react-native-maps / no fake coords', () => {
  assert.doesNotMatch(mapView, /class Trip\b|createJourney|Journey entity/i);
  assert.equal(pkg.dependencies['react-native-maps'], undefined);
  assert.doesNotMatch(mapView, /from ['"]react-native-maps['"]/);
  assert.doesNotMatch(mapView, /<\s*MapView\b/);
  assert.doesNotMatch(mapView, /hardcoded.?lat|fakeCoords|Math\.random\(\).*lat/i);
});

check('Timeline reuses existing phase mapping (tripProgressIndex)', () => {
  assert.match(timeline, /tripProgressIndex/);
  assert.match(timeline, /buildTripTimelineMilestones/);
  assert.match(timeline, /Assignment received/);
  assert.match(timeline, /Pickup verified/);
  assert.match(timeline, /In transit/);
  assert.match(timeline, /Delivery verification/);
  assert.match(timeline, /Completed/);
  assert.match(mapView, /active-trip-map-timeline/);
  assert.match(mapView, /TRIP PROGRESS/);
});

check('Location confidence states (device / server / unavailable)', () => {
  assert.match(mapView, /LIVE LOCATION/);
  assert.match(mapView, /Using device GPS/);
  assert.match(mapView, /LAST KNOWN LOCATION/);
  assert.match(mapView, /Using server position/);
  assert.match(mapView, /LOCATION UNAVAILABLE/);
  assert.match(mapView, /Enable GPS/);
  assert.doesNotMatch(mapView, /accuracyMeters\s*=\s*\d+|fakeAccuracy/i);
});

check('Operational empty-state copy', () => {
  assert.match(mapView, /Route preview is currently unavailable/);
  assert.match(mapView, /Trip execution can continue normally/);
  assert.match(mapView, /Location details are unavailable for this stop/);
  assert.match(mapView, /Location services are disabled/);
});

check('CURRENT TRIP card fields', () => {
  assert.match(mapView, /CURRENT TRIP/);
  assert.match(mapView, /Current status/);
  assert.match(mapView, /Last update/);
  assert.match(mapView, /active-trip-map-ops-card/);
});

check('Tracking ingestion files untouched by map polish imports', () => {
  assert.match(publisher, /MIN_INTERVAL_MS|5000/);
  assert.match(location, /watchPositionAsync/);
  assert.doesNotMatch(publisher, /mapTimeline|DriverTripMapView|LIVE LOCATION/);
  assert.doesNotMatch(location, /mapTimeline|DriverTripMapView/);
  assert.match(trip, /TelemetryPublisher/);
  assert.doesNotMatch(mapView, /submitTracking\(|getTripMap\(/);
});

check('Freshness timer remains local-only', () => {
  assert.match(mapView, /setInterval\(\(\) => setNowMs\(Date\.now\(\)\)/);
  assert.doesNotMatch(mapView, /setInterval\([^)]*getTripMap|setInterval\([^)]*submitTracking/);
});

console.log('');
console.log(`DRIVER-MAP-04: ${passed} passed, ${failed} failed`);
if (failed > 0) process.exit(1);
console.log('DRIVER-MAP-04 VERDICT: PASS');
