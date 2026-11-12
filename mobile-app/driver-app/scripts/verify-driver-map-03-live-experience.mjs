/**
 * DRIVER-MAP-03 — Live trip map operational experience.
 * Run: npm run test:driver-map-03
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

console.log('=== DRIVER-MAP-03 Live Experience ===\n');

const mapView = read('src/features/tracking/DriverTripMapView.tsx');
const focus = read('src/features/tracking/mapFocusMode.ts');
const maps = read('src/features/tracking/openExternalMaps.ts');
const trip = read('src/app/active-trip/[id].tsx');
const publisher = read('src/features/tracking/TelemetryPublisher.ts');
const location = read('src/features/tracking/locationProvider.ts');
const pkg = JSON.parse(read('package.json'));

check('No Trip/Journey entity and no react-native-maps', () => {
  assert.doesNotMatch(mapView, /class Trip\b|Journey entity|createTrip/i);
  assert.doesNotMatch(focus, /Trip entity|Journey/);
  assert.equal(pkg.dependencies['react-native-maps'], undefined);
  assert.doesNotMatch(mapView, /from ['"]react-native-maps['"]/);
  assert.doesNotMatch(mapView, /<\s*MapView\b/);
});

check('Existing phases reused for focus (no new state machine)', () => {
  assert.match(focus, /pickup_pending/);
  assert.match(focus, /pickup_verifying/);
  assert.match(focus, /resolveAutoMapFocus/);
  assert.match(focus, /return 'pickup'/);
  assert.match(focus, /return 'delivery'/);
  assert.match(focus, /overview/);
  assert.match(mapView, /phaseStatusLabel/);
  assert.match(mapView, /executionPhase/);
});

check('No fake coordinates / no frontend route calculation engine', () => {
  assert.match(mapView, /Route preview.{0,40}unavailable/);
  assert.match(mapView, /routeAvailable/);
  assert.doesNotMatch(mapView, /GetShipmentRoute|OSRM|GraphHopper|recalculateRoute/);
  assert.doesNotMatch(maps, /route\.calculate|polylineEncode/);
  assert.match(mapView, /trip\?\.routeAvailable && \(trip\.route\?\.coordinates\?\.length/);
});

check('Navigation uses existing coordinates only', () => {
  assert.match(maps, /google\.navigation|maps\.apple|google\.com\/maps/);
  assert.match(trip, /openExternalMaps/);
  assert.match(trip, /active-trip-open-maps|onOpenInMaps/);
  assert.match(trip, /pickup\.coordinates|delivery\.coordinates/);
  assert.match(maps, /Open in Maps|buildExternalMapsUrls|openExternalMaps/);
});

check('Live tracking UX states without extra polling', () => {
  assert.match(mapView, /LIVE LOCATION|Driver location updated|Updated /);
  assert.match(mapView, /Using last known location|Using server position|LAST KNOWN/);
  assert.match(mapView, /Location unavailable|LOCATION UNAVAILABLE/);
  assert.match(mapView, /CURRENT TRIP/);
  assert.match(mapView, /active-trip-map-ops-card/);
  assert.doesNotMatch(mapView, /getTripMap\(|submitTracking\(/);
  assert.doesNotMatch(mapView, /setInterval\([^)]*getTripMap/);
});

check('Tracking ingestion modules preserved', () => {
  assert.match(publisher, /MIN_INTERVAL_MS|5000/);
  assert.match(publisher, /MIN_DISTANCE_M|25/);
  assert.match(location, /watchPositionAsync/);
  assert.match(trip, /TelemetryPublisher/);
  assert.match(trip, /createExpoLocationProvider/);
  assert.doesNotMatch(publisher, /DriverTripMapView|openExternalMaps/);
});

check('Error / offline copy present; execution not blocked by map', () => {
  assert.match(mapView, /Location details are unavailable|Location details unavailable/);
  assert.match(mapView, /Showing last available trip data/);
  assert.match(mapView, /Enable GPS|Enable location services|permissionLabel/);
  assert.match(trip, /verify-trip|transitionExecutionPhase|completeAssignment/);
});

console.log('');
console.log(`DRIVER-MAP-03: ${passed} passed, ${failed} failed`);
if (failed > 0) process.exit(1);
console.log('DRIVER-MAP-03 VERDICT: PASS');
