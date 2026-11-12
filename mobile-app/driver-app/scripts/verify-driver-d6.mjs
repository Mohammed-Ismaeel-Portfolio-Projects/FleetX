/**
 * DRIVER-D6 — Trip map + foreground GPS telemetry.
 * Run: npm run test:driver-d6
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

console.log('=== DRIVER-D6 Telemetry + Map ===\n');

const workApi = read('src/features/driverApi/work.ts');
const trip = read('src/app/active-trip/[id].tsx');
const mapView = read('src/features/tracking/DriverTripMapView.tsx');
const publisher = read('src/features/tracking/TelemetryPublisher.ts');
const location = read('src/features/tracking/locationProvider.ts');
const pkg = JSON.parse(read('package.json'));
const appConfig = read('app.config.js');

check('Dependency: expo-location present; no react-native-maps', () => {
  assert.ok(pkg.dependencies['expo-location']);
  assert.equal(pkg.dependencies['react-native-maps'], undefined);
});

check('API: driver tracking + trip-map only', () => {
  assert.match(workApi, /submitTracking/);
  assert.match(workApi, /getTripMap/);
  assert.match(workApi, /\/tracking/);
  assert.match(workApi, /trip-map/);
  assert.doesNotMatch(workApi, /tracking-logs|live-fleet|\/client\/shipments/);
});

check('Location provider abstraction + permission states', () => {
  assert.match(location, /LocationPermissionState/);
  assert.match(location, /requestPermission/);
  assert.match(location, /watchPosition/);
  assert.match(location, /createExpoLocationProvider/);
});

check('TelemetryPublisher: interval/distance + bounded retry + stop', () => {
  assert.match(publisher, /MIN_INTERVAL_MS|5000/);
  assert.match(publisher, /MIN_DISTANCE_M|25/);
  assert.match(publisher, /pending/);
  assert.match(publisher, /stop\(/);
  assert.match(publisher, /submitTracking/);
});

check('Active Trip: map + permission + tracking status + cleanup', () => {
  assert.match(trip, /DriverTripMapView/);
  assert.match(trip, /TelemetryPublisher/);
  assert.match(trip, /active-trip-tracking-status|trackingLabel/);
  assert.match(trip, /active-trip-request-location|requestLocation/);
  assert.match(trip, /stopTelemetry/);
  assert.match(trip, /getTripMap/);
});

check('Map: pickup/delivery/driver markers; no other drivers; no fake route invent', () => {
  assert.match(mapView, /active-trip-marker-pickup|kind === 'pickup'/);
  assert.match(mapView, /active-trip-marker-delivery|kind === 'delivery'/);
  assert.match(mapView, /active-trip-marker-driver|kind === 'driver'/);
  assert.match(mapView, /active-trip-map-recenter/);
  assert.match(mapView, /active-trip-map-fit/);
  assert.match(mapView, /routeAvailable|Planned route not available/);
  assert.doesNotMatch(mapView, /other.?driver|fleet|live-fleet/i);
  assert.doesNotMatch(mapView, /react-native-maps/);
  assert.doesNotMatch(mapView, /from ['"]react-native-maps['"]/);
});

check('App config: location permission + plugin', () => {
  assert.match(appConfig, /expo-location/);
  assert.match(appConfig, /NSLocationWhenInUseUsageDescription|locationWhenInUsePermission/);
});

console.log('');
console.log(`DRIVER-D6: ${passed} passed, ${failed} failed`);
if (failed > 0) process.exit(1);
console.log('DRIVER-D6 VERDICT: PASS');
