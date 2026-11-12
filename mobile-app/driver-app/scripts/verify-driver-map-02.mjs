/**
 * DRIVER-MAP-02 — Professional schematic trip map foundation.
 * Run: npm run test:driver-map-02
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

console.log('=== DRIVER-MAP-02 Trip Map Foundation ===\n');

const mapView = read('src/features/tracking/DriverTripMapView.tsx');
const trip = read('src/app/active-trip/[id].tsx');
const publisher = read('src/features/tracking/TelemetryPublisher.ts');
const location = read('src/features/tracking/locationProvider.ts');
const pkg = JSON.parse(read('package.json'));

check('DriverTripMapView exists and remains schematic (no react-native-maps)', () => {
  assert.match(mapView, /export (function|const) DriverTripMapView/);
  assert.equal(pkg.dependencies['react-native-maps'], undefined);
  assert.doesNotMatch(mapView, /from ['"]react-native-maps['"]/);
  assert.doesNotMatch(mapView, /<\s*MapView\b/);
});

check('No fabricated route / fake coordinates', () => {
  assert.match(mapView, /routeAvailable/);
  assert.match(mapView, /Route preview.{0,40}unavailable/);
  assert.doesNotMatch(mapView, /hardcoded.?lat|fakeCoords|Math\.random\(\).*lat/i);
  assert.match(mapView, /trip\?\.routeAvailable && \(trip\.route\?\.coordinates\?\.length/);
});

check('Markers + focus controls', () => {
  assert.match(mapView, /active-trip-marker-\$\{m\.kind\}|kind === 'pickup'/);
  assert.match(mapView, /kind === 'delivery'|active-trip-marker-delivery/);
  assert.match(mapView, /kind === 'driver'|active-trip-marker-driver/);
  assert.match(mapView, /active-trip-map-recenter/);
  assert.match(mapView, /active-trip-map-fit/);
  assert.match(mapView, /Focus driver/);
  assert.match(mapView, /Fit trip/);
});

check('Loading and error states', () => {
  assert.match(mapView, /Loading trip map/);
  assert.match(mapView, /Loading location/);
  assert.match(mapView, /Preparing route/);
  assert.match(mapView, /Trip map unavailable/);
  assert.match(mapView, /Location details are unavailable|Location \(data\|details\) unavailable/);
  assert.match(mapView, /LOCATION UNAVAILABLE|[Ll]ocation unavailable/);
  assert.match(mapView, /mapLoading/);
});

check('Driver freshness panel', () => {
  assert.match(mapView, /active-trip-map-driver-panel/);
  assert.match(mapView, /LIVE LOCATION|Updated \$\{age\}|just now/);
  assert.match(mapView, /seconds ago|Updated \$\{age\}/);
  assert.match(mapView, /active-trip-freshness/);
});

check('Active Trip wires map without blocking execution / tracking preserved', () => {
  assert.match(trip, /DriverTripMapView/);
  assert.match(trip, /tripMapLoading/);
  assert.match(trip, /TelemetryPublisher/);
  assert.match(trip, /createExpoLocationProvider/);
  assert.match(trip, /getTripMap/);
  assert.match(publisher, /MIN_INTERVAL_MS|5000/);
  assert.match(location, /watchPositionAsync/);
});

check('Tracking modules unchanged in contract (still imported as-is)', () => {
  assert.match(trip, /features\/tracking\/TelemetryPublisher/);
  assert.match(trip, /features\/tracking\/locationProvider/);
  assert.doesNotMatch(publisher, /DriverTripMapView/);
});

console.log('');
console.log(`DRIVER-MAP-02: ${passed} passed, ${failed} failed`);
if (failed > 0) process.exit(1);
console.log('DRIVER-MAP-02 VERDICT: PASS');
