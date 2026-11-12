/**
 * C5.5-C4 — Client shipment tracking map UI (static + behavioral helpers).
 * Updated for C5.5-C4.1 customer-safe labels / freshness copy.
 * Run: node ./scripts/verify-c55c4-client-tracking-map-ui.mjs
 */

import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const clientRoot = path.join(__dirname, '..');

function readClient(rel) {
  return fs.readFileSync(path.join(clientRoot, rel), 'utf8');
}

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
    console.error(e);
  }
}

function isUsableGeoPoint(latitude, longitude) {
  return (
    typeof latitude === 'number' &&
    typeof longitude === 'number' &&
    Number.isFinite(latitude) &&
    Number.isFinite(longitude) &&
    latitude >= -90 &&
    latitude <= 90 &&
    longitude >= -180 &&
    longitude <= 180
  );
}

function locationFreshnessLabel(state) {
  switch (state) {
    case 'fresh':
      return 'Location updated recently';
    case 'stale':
      return 'Last location update was some time ago';
    case 'very_stale':
      return 'Location update may be outdated';
    case 'unavailable':
    default:
      return 'Location unavailable';
  }
}

function sanitizeRouteCoordinates(coordinates) {
  if (!Array.isArray(coordinates) || coordinates.length < 2) return [];
  const usable = [];
  for (const point of coordinates) {
    if (!point || !isUsableGeoPoint(point.latitude, point.longitude)) continue;
    usable.push({ latitude: point.latitude, longitude: point.longitude });
  }
  return usable.length >= 2 ? usable : [];
}

function buildTrackingMapModel(tracking) {
  const markers = [];
  const origin = tracking.origin?.coordinates;
  if (origin && isUsableGeoPoint(origin.latitude, origin.longitude)) {
    markers.push({
      kind: 'origin',
      latitude: origin.latitude,
      longitude: origin.longitude,
      label: 'Pickup',
      accessibilityLabel: 'Pickup location',
    });
  }
  const destination = tracking.destination?.coordinates;
  if (destination && isUsableGeoPoint(destination.latitude, destination.longitude)) {
    markers.push({
      kind: 'destination',
      latitude: destination.latitude,
      longitude: destination.longitude,
      label: 'Delivery',
      accessibilityLabel: 'Delivery location',
    });
  }
  const lastKnown = tracking.lastKnownPosition;
  if (lastKnown && isUsableGeoPoint(lastKnown.latitude, lastKnown.longitude)) {
    markers.push({
      kind: 'lastKnown',
      latitude: lastKnown.latitude,
      longitude: lastKnown.longitude,
      label: 'Last known',
      accessibilityLabel: 'Last known shipment location',
    });
  }
  const routeCoordinates = sanitizeRouteCoordinates(tracking.route?.coordinates ?? null);
  const hasOriginOrDestination = markers.some((m) => m.kind === 'origin' || m.kind === 'destination');
  const hasLastKnown = markers.some((m) => m.kind === 'lastKnown');
  let geoMode = 'none';
  if (routeCoordinates.length >= 2) geoMode = 'full';
  else if (hasOriginOrDestination) geoMode = 'hubs';
  else if (hasLastKnown) geoMode = 'locationOnly';
  return {
    markers,
    routeCoordinates,
    hasGeographicData: geoMode !== 'none',
    geoMode,
    locationFreshness: tracking.locationFreshness,
  };
}

function projectTrackingMap(model, width, height, padding = 28) {
  if (!Number.isFinite(width) || !Number.isFinite(height) || width <= 0 || height <= 0) return null;
  if (!model.hasGeographicData) return null;
  const points = [];
  for (const c of model.routeCoordinates) {
    if (isUsableGeoPoint(c.latitude, c.longitude)) points.push(c);
  }
  for (const m of model.markers) {
    if (isUsableGeoPoint(m.latitude, m.longitude)) {
      points.push({ latitude: m.latitude, longitude: m.longitude });
    }
  }
  if (points.length === 0) return null;
  let minLat = points[0].latitude;
  let maxLat = points[0].latitude;
  let minLng = points[0].longitude;
  let maxLng = points[0].longitude;
  for (const p of points) {
    if (p.latitude < minLat) minLat = p.latitude;
    if (p.latitude > maxLat) maxLat = p.latitude;
    if (p.longitude < minLng) minLng = p.longitude;
    if (p.longitude > maxLng) maxLng = p.longitude;
  }
  const latSpan = Math.max(maxLat - minLat, 0.002);
  const lngSpan = Math.max(maxLng - minLng, 0.002);
  const safePadding = Math.max(12, Math.min(padding, Math.min(width, height) / 4));
  const innerW = Math.max(width - safePadding * 2, 1);
  const innerH = Math.max(height - safePadding * 2, 1);
  const project = (latitude, longitude) => ({
    x: safePadding + ((longitude - minLng) / lngSpan) * innerW,
    y: safePadding + (1 - (latitude - minLat) / latSpan) * innerH,
  });
  return {
    markers: model.markers.map((m) => ({ ...m, ...project(m.latitude, m.longitude) })),
    route: model.routeCoordinates.map((c) => project(c.latitude, c.longitude)),
  };
}

const progress = readClient('src/app/shipment-tracking/[id].tsx');
const mapUi = readClient('src/features/shipments/ShipmentTrackingMap.tsx');
const mapModel = readClient('src/features/shipments/shipmentTrackingMap.ts');
const api = readClient('src/features/shipments/shipmentsApi.ts');
const types = readClient('src/features/shipments/types.ts');
const pkg = readClient('package.json');

check('tracking screen wires C5.5-C3 domain map panel', () => {
  assert.match(progress, /ShipmentTrackingMap/);
  assert.match(progress, /locationFreshnessLabel/);
  assert.match(progress, /tracking\.locationFreshness/);
  assert.match(progress, /shipment-progress-last-known-freshness/);
  assert.match(progress, /getClientShipmentTracking/);
  assert.doesNotMatch(progress, /react-native-maps|SafeMap|MapView/);
  assert.doesNotMatch(progress, /driver_id|vehicle_id|live-fleet|setInterval|Echo|Reverb/);
  assert.doesNotMatch(progress, /exact pickup|exact delivery|vehicle is here/i);
});

check('map UI uses hub-safe labels and marker testIDs', () => {
  assert.match(mapModel, /label: 'Pickup'/);
  assert.match(mapModel, /label: 'Delivery'/);
  assert.match(mapModel, /Last known/);
  assert.match(mapUi, /shipment-progress-map-marker-\$\{marker\.kind\}/);
  assert.match(mapUi, /shipment-progress-freshness/);
  assert.match(mapUi, /shipment-progress-map-unavailable/);
  assert.match(mapUi, /Planned route is not available/);
  assert.doesNotMatch(mapUi, /react-native-maps|SafeMap|MapView/);
  assert.doesNotMatch(mapUi, /driver_id|vehicle_id|graphhopper|encoded polyline/i);
});

check('map helpers consume domain contract only', () => {
  assert.match(mapModel, /buildTrackingMapModel/);
  assert.match(mapModel, /projectTrackingMap/);
  assert.match(mapModel, /locationFreshnessLabel/);
  assert.match(mapModel, /origin\?\.coordinates|origin\.coordinates/);
  assert.match(mapModel, /destination\?\.coordinates|destination\.coordinates/);
  assert.match(mapModel, /lastKnownPosition/);
  assert.match(mapModel, /route\?\.coordinates|route\.coordinates/);
  assert.match(mapModel, /locationFreshness/);
  assert.doesNotMatch(mapModel, /EuropeanFleetLocations|resolveCity|GetShipmentRoute/);
});

check('tracking map UI does not import native maps', () => {
  assert.doesNotMatch(api, /react-native-maps/);
  assert.doesNotMatch(mapUi, /from ['"]react-native-maps['"]/);
  assert.match(types, /locationFreshness: ClientLocationFreshness/);
  assert.match(types, /route: ClientPlannedRoute \| null/);
});

check('behavioral: full data builds origin/destination/lastKnown/route', () => {
  const model = buildTrackingMapModel({
    origin: { coordinates: { latitude: 52.2, longitude: 21.0 } },
    destination: { coordinates: { latitude: 50.0, longitude: 19.0 } },
    lastKnownPosition: { latitude: 51.0, longitude: 20.0, recordedAt: '2026-08-15 12:00:00' },
    route: {
      coordinates: [
        { latitude: 52.2, longitude: 21.0 },
        { latitude: 51.0, longitude: 20.0 },
        { latitude: 50.0, longitude: 19.0 },
      ],
    },
    locationFreshness: { state: 'fresh', recordedAt: '2026-08-15 12:00:00', ageSeconds: 30 },
  });
  assert.equal(model.hasGeographicData, true);
  assert.equal(model.markers.length, 3);
  assert.equal(model.routeCoordinates.length, 3);
  assert.deepEqual(
    model.markers.map((m) => m.kind),
    ['origin', 'destination', 'lastKnown']
  );
  const projected = projectTrackingMap(model, 320, 220);
  assert.ok(projected);
  assert.equal(projected.markers.length, 3);
  assert.equal(projected.route.length, 3);
});

check('behavioral: missing route does not fabricate geometry', () => {
  const model = buildTrackingMapModel({
    origin: { coordinates: { latitude: 52.2, longitude: 21.0 } },
    destination: { coordinates: { latitude: 50.0, longitude: 19.0 } },
    lastKnownPosition: null,
    route: null,
    locationFreshness: { state: 'unavailable', recordedAt: null, ageSeconds: 0 },
  });
  assert.equal(model.routeCoordinates.length, 0);
  assert.equal(model.markers.length, 2);
  assert.equal(model.hasGeographicData, true);
  const projected = projectTrackingMap(model, 300, 200);
  assert.ok(projected);
  assert.equal(projected.route.length, 0);
});

check('behavioral: missing coordinates → no geographic data / no invention', () => {
  const model = buildTrackingMapModel({
    origin: { coordinates: null },
    destination: { coordinates: null },
    lastKnownPosition: null,
    route: null,
    locationFreshness: { state: 'unavailable', recordedAt: null, ageSeconds: 0 },
  });
  assert.equal(model.hasGeographicData, false);
  assert.equal(model.markers.length, 0);
  assert.equal(projectTrackingMap(model, 320, 220), null);
});

check('behavioral: one-point route ignored', () => {
  const model = buildTrackingMapModel({
    origin: { coordinates: null },
    destination: { coordinates: null },
    lastKnownPosition: null,
    route: { coordinates: [{ latitude: 52.2, longitude: 21.0 }] },
    locationFreshness: { state: 'unavailable', recordedAt: null, ageSeconds: 0 },
  });
  assert.equal(model.routeCoordinates.length, 0);
  assert.equal(model.hasGeographicData, false);
});

check('behavioral: freshness labels for all four states', () => {
  assert.equal(locationFreshnessLabel('unavailable'), 'Location unavailable');
  assert.equal(locationFreshnessLabel('fresh'), 'Location updated recently');
  assert.equal(locationFreshnessLabel('stale'), 'Last location update was some time ago');
  assert.equal(locationFreshnessLabel('very_stale'), 'Location update may be outdated');
});

check('behavioral: last-known only marker without hubs still projects', () => {
  const model = buildTrackingMapModel({
    origin: { coordinates: null },
    destination: { coordinates: null },
    lastKnownPosition: { latitude: 51.1, longitude: 20.2, recordedAt: '2026-08-15 11:00:00' },
    route: null,
    locationFreshness: {
      state: 'very_stale',
      recordedAt: '2026-08-15 11:00:00',
      ageSeconds: 3600,
    },
  });
  assert.equal(model.markers.length, 1);
  assert.equal(model.markers[0].kind, 'lastKnown');
  assert.ok(projectTrackingMap(model, 300, 200));
});

console.log('');
console.log(`C5.5-C4 map UI checks: ${passed} passed, ${failed} failed`);
if (failed > 0) process.exit(1);
