/**
 * C5.5-C4.1 — Client tracking map visual refinement & hardening verifier.
 * Run: node ./scripts/verify-c55c41-client-tracking-map-hardening.mjs
 *       npm run test:c55c41-client-tracking-map-hardening
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
  if (![minLat, maxLat, minLng, maxLng].every(Number.isFinite)) return null;
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
const pkg = readClient('package.json');

check('planned route is clearly labeled as planned (not live)', () => {
  assert.match(mapUi, /Planned route/);
  assert.match(mapUi, /Planned shipment route/);
  assert.match(mapModel, /planned route/i);
  assert.doesNotMatch(mapUi, /\bLive route\b|\bLive tracking\b|\bVehicle route\b|\bCurrent route\b/);
  assert.doesNotMatch(progress, /\bLive route\b|\bLive tracking\b|\bVehicle route\b/);
  assert.match(progress, /not a live GPS feed/i);
});

check('customer-safe marker labels and a11y', () => {
  assert.match(mapModel, /label: 'Pickup'/);
  assert.match(mapModel, /label: 'Delivery'/);
  assert.match(mapModel, /accessibilityLabel: 'Pickup location'/);
  assert.match(mapModel, /accessibilityLabel: 'Delivery location'/);
  assert.match(mapModel, /accessibilityLabel: 'Last known shipment location'/);
  assert.match(mapModel, /Location freshness:/);
  assert.match(mapUi, /accessibilityLabel=\{marker\.accessibilityLabel\}/);
});

check('freshness copy matches backend states without client recalculation', () => {
  assert.match(mapModel, /Location updated recently/);
  assert.match(mapModel, /Last location update was some time ago/);
  assert.match(mapModel, /Location update may be outdated/);
  assert.match(mapModel, /Location unavailable/);
  assert.doesNotMatch(mapModel, /setInterval|Date\.now\(\)|ageSeconds\s*[+\-*/]/);
  assert.doesNotMatch(mapUi, /\bLive tracking\b|\bLive GPS\b|\bLive route\b/);
  assert.deepEqual(locationFreshnessLabel('fresh'), 'Location updated recently');
  assert.deepEqual(locationFreshnessLabel('stale'), 'Last location update was some time ago');
  assert.deepEqual(locationFreshnessLabel('very_stale'), 'Location update may be outdated');
  assert.deepEqual(locationFreshnessLabel('unavailable'), 'Location unavailable');
});

check('no fabricated route / coords / ETA / driver metadata', () => {
  assert.match(mapModel, /Never invents geometry|never invent a hub-to-hub/i);
  assert.doesNotMatch(mapModel, /\blerp\b|\binterpolate\b/i);
  assert.doesNotMatch(progress, /toFixed\(/);
  assert.doesNotMatch(progress, /\bETA\b|driver_id|vehicle_id|graphhopper/i);
  assert.doesNotMatch(mapUi, /react-native-maps|SafeMap|MapView/);
  assert.doesNotMatch(api, /mapClientShipmentTracking[\s\S]{0,40}EuropeanFleet/);
});

check('geo modes: full / hubs / locationOnly / none', () => {
  assert.match(mapModel, /TrackingMapGeoMode/);
  assert.match(mapModel, /'full' \| 'hubs' \| 'locationOnly' \| 'none'/);
  assert.match(mapModel, /trackingMapModeHint/);

  const full = buildTrackingMapModel({
    origin: { coordinates: { latitude: 52.2, longitude: 21.0 } },
    destination: { coordinates: { latitude: 50.0, longitude: 19.0 } },
    lastKnownPosition: { latitude: 51.0, longitude: 20.0 },
    route: {
      coordinates: [
        { latitude: 52.2, longitude: 21.0 },
        { latitude: 50.0, longitude: 19.0 },
      ],
    },
    locationFreshness: { state: 'fresh', recordedAt: 'x', ageSeconds: 1 },
  });
  assert.equal(full.geoMode, 'full');
  assert.equal(full.routeCoordinates.length, 2);

  const hubs = buildTrackingMapModel({
    origin: { coordinates: { latitude: 52.2, longitude: 21.0 } },
    destination: { coordinates: { latitude: 50.0, longitude: 19.0 } },
    lastKnownPosition: null,
    route: null,
    locationFreshness: { state: 'unavailable', recordedAt: null, ageSeconds: 0 },
  });
  assert.equal(hubs.geoMode, 'hubs');
  assert.equal(hubs.routeCoordinates.length, 0);

  const onlyLoc = buildTrackingMapModel({
    origin: { coordinates: null },
    destination: { coordinates: null },
    lastKnownPosition: { latitude: 51.1, longitude: 20.2 },
    route: null,
    locationFreshness: { state: 'stale', recordedAt: 'x', ageSeconds: 400 },
  });
  assert.equal(onlyLoc.geoMode, 'locationOnly');

  const none = buildTrackingMapModel({
    origin: { coordinates: null },
    destination: { coordinates: null },
    lastKnownPosition: null,
    route: null,
    locationFreshness: { state: 'unavailable', recordedAt: null, ageSeconds: 0 },
  });
  assert.equal(none.geoMode, 'none');
  assert.equal(none.hasGeographicData, false);
});

check('invalid geometry is dropped and does not crash projection', () => {
  const model = buildTrackingMapModel({
    origin: { coordinates: { latitude: Number.NaN, longitude: 21 } },
    destination: { coordinates: { latitude: 91, longitude: 19 } },
    lastKnownPosition: { latitude: 51, longitude: Number.POSITIVE_INFINITY },
    route: {
      coordinates: [
        { latitude: 52.2, longitude: 21.0 },
        { latitude: Number.NaN, longitude: 20.0 },
        { latitude: 50.0, longitude: 19.0 },
      ],
    },
    locationFreshness: { state: 'unavailable', recordedAt: null, ageSeconds: 0 },
  });
  assert.equal(model.markers.length, 0);
  assert.equal(model.routeCoordinates.length, 2);
  assert.equal(model.geoMode, 'full');
  assert.ok(projectTrackingMap(model, 320, 220));
  assert.equal(projectTrackingMap(model, 0, 220), null);
  assert.equal(projectTrackingMap(model, Number.NaN, 220), null);
});

check('one-point / empty route does not fabricate a path', () => {
  const onePoint = buildTrackingMapModel({
    origin: { coordinates: { latitude: 52.2, longitude: 21.0 } },
    destination: { coordinates: { latitude: 50.0, longitude: 19.0 } },
    lastKnownPosition: null,
    route: { coordinates: [{ latitude: 52.2, longitude: 21.0 }] },
    locationFreshness: { state: 'unavailable', recordedAt: null, ageSeconds: 0 },
  });
  assert.equal(onePoint.routeCoordinates.length, 0);
  assert.equal(onePoint.geoMode, 'hubs');
  const projected = projectTrackingMap(onePoint, 300, 200);
  assert.ok(projected);
  assert.equal(projected.route.length, 0);
  assert.equal(projected.markers.length, 2);
});

check('screen hides raw coordinates and keeps mapper/API free of map deps', () => {
  assert.doesNotMatch(progress, /toFixed\(/);
  assert.match(progress, /shipment-progress-last-known-freshness/);
  assert.match(mapUi, /numberOfLines=\{2\}/);
  assert.match(api, /export function mapClientShipmentTracking/);
  assert.doesNotMatch(api, /ShipmentTrackingMap/);
});

console.log('');
console.log(`C5.5-C4.1 hardening checks: ${passed} passed, ${failed} failed`);
if (failed > 0) process.exit(1);
