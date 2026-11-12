/**
 * C5.5-C4.3 — Client shipment tracking screen integration verifier.
 * Run: node ./scripts/verify-c55c43-client-tracking-screen-integration.mjs
 *       npm run test:c55c43-client-tracking-screen-integration
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
    markers.push({ kind: 'origin' });
  }
  const destination = tracking.destination?.coordinates;
  if (destination && isUsableGeoPoint(destination.latitude, destination.longitude)) {
    markers.push({ kind: 'destination' });
  }
  const lastKnown = tracking.lastKnownPosition;
  if (lastKnown && isUsableGeoPoint(lastKnown.latitude, lastKnown.longitude)) {
    markers.push({ kind: 'lastKnown' });
  }
  const routeCoordinates = sanitizeRouteCoordinates(tracking.route?.coordinates ?? null);
  const hasOriginOrDestination = markers.some((m) => m.kind === 'origin' || m.kind === 'destination');
  const hasLastKnown = markers.some((m) => m.kind === 'lastKnown');
  let geoMode = 'none';
  if (routeCoordinates.length >= 2) geoMode = 'full';
  else if (hasOriginOrDestination) geoMode = 'hubs';
  else if (hasLastKnown) geoMode = 'locationOnly';
  return { markers, routeCoordinates, geoMode, hasGeographicData: geoMode !== 'none' };
}

const progress = readClient('src/app/shipment-tracking/[id].tsx');
const mapUi = readClient('src/features/shipments/ShipmentTrackingMap.tsx');
const mapModel = readClient('src/features/shipments/shipmentTrackingMap.ts');
const api = readClient('src/features/shipments/shipmentsApi.ts');
const pkg = readClient('package.json');

check('ShipmentTrackingMap is integrated into the tracking screen', () => {
  assert.match(progress, /import \{ ShipmentTrackingMap \}/);
  assert.match(progress, /<ShipmentTrackingMap tracking=\{tracking\} theme=\{theme\} \/>/);
  assert.match(progress, /getClientShipmentTracking/);
  assert.match(progress, /shipment-progress-screen/);
});

check('single tracking API source — no second lifecycle / extra endpoints', () => {
  const trackingFetches = progress.match(/getClientShipmentTracking/g) || [];
  assert.ok(trackingFetches.length >= 1);
  assert.doesNotMatch(progress, /getClientShipmentTracking\([^)]*\)[\s\S]*getClientShipmentTracking/);
  assert.match(api, /\/client\/shipments\/\$\{[^}]+\}\/tracking/);
  assert.match(api, /MUST call GET \/api\/v1\/client\/shipments\/\{id\}\/tracking only/);
  assert.doesNotMatch(progress, /live-fleet|tracking-state|tracking-log|GetShipmentRoute|graphhopper/i);
  assert.doesNotMatch(progress, /useQuery|@tanstack\/react-query|Echo|Reverb|WebSocket|setInterval/);
  assert.doesNotMatch(mapUi, /getClientShipmentTracking|fetch\(/);
});

check('screen hierarchy: identity → status → journey → planned delivery → last-known → milestones', () => {
  const numberIdx = progress.indexOf('shipment-progress-number');
  const statusIdx = progress.indexOf('shipment-progress-status');
  const routeIdx = progress.indexOf('shipment-progress-route');
  const mapIdx = progress.indexOf('<ShipmentTrackingMap');
  const plannedIdx = progress.indexOf('shipment-progress-planned-delivery');
  const lastKnownIdx = progress.indexOf('shipment-progress-last-known');
  const milestonesIdx = progress.indexOf('shipment-progress-milestones');
  assert.ok(numberIdx > 0 && statusIdx > numberIdx);
  assert.ok(routeIdx > statusIdx);
  assert.ok(mapIdx > routeIdx);
  assert.ok(plannedIdx > mapIdx);
  assert.ok(lastKnownIdx > plannedIdx);
  assert.ok(milestonesIdx > lastKnownIdx);
});

check('planned delivery without ETA terminology', () => {
  assert.match(progress, /formatPlannedDeliveryLabel/);
  assert.match(progress, /Planned delivery/);
  assert.match(progress, /shipment-progress-planned-delivery/);
  assert.doesNotMatch(progress, /\bETA\b|Estimated arrival|Arriving in|On time|Delayed/i);
  assert.doesNotMatch(mapUi, /\bETA\b|Estimated arrival|Arriving in/i);
});

check('backend freshness consumed without client recalculation', () => {
  assert.match(progress, /locationFreshnessLabel/);
  assert.match(progress, /tracking\.locationFreshness/);
  assert.match(progress, /shipment-progress-last-known-freshness/);
  assert.match(mapModel, /Location updated recently/);
  assert.match(mapModel, /Last location update was some time ago/);
  assert.match(mapModel, /Location update may be outdated/);
  assert.match(mapModel, /Location unavailable/);
  assert.doesNotMatch(progress, /Date\.now\(\)|setInterval|ageSeconds\s*[+\-*/]/);
  assert.doesNotMatch(progress, /\bLive tracking\b|\bCurrently here\b|\bMoving now\b/i);
});

check('geo modes remain supported (full / hubs / locationOnly / none)', () => {
  assert.match(mapModel, /TrackingMapGeoMode/);
  assert.match(mapModel, /'full' \| 'hubs' \| 'locationOnly' \| 'none'/);

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
  });
  assert.equal(full.geoMode, 'full');

  const hubs = buildTrackingMapModel({
    origin: { coordinates: { latitude: 52.2, longitude: 21.0 } },
    destination: { coordinates: { latitude: 50.0, longitude: 19.0 } },
    lastKnownPosition: null,
    route: null,
  });
  assert.equal(hubs.geoMode, 'hubs');
  assert.equal(hubs.routeCoordinates.length, 0);

  const onlyLoc = buildTrackingMapModel({
    origin: { coordinates: null },
    destination: { coordinates: null },
    lastKnownPosition: { latitude: 51.1, longitude: 20.2 },
    route: null,
  });
  assert.equal(onlyLoc.geoMode, 'locationOnly');

  const none = buildTrackingMapModel({
    origin: { coordinates: null },
    destination: { coordinates: null },
    lastKnownPosition: null,
    route: null,
  });
  assert.equal(none.geoMode, 'none');
  assert.match(mapUi, /shipment-progress-map-empty|No map details yet/);
});

check('forbidden content audit — no ops / driver / vehicle / raw coords leakage', () => {
  assert.doesNotMatch(progress, /toFixed\(/);
  assert.doesNotMatch(progress, /driver_id|vehicle_id|\bplate\b|assignment|dispatcher|provider/i);
  assert.doesNotMatch(progress, /encoded polyline|route engine|engine_ops|deviation|rerouting|heading|\bspeed\b/i);
  assert.doesNotMatch(progress, /live-fleet|tracking-state|TrackingLog|GraphHopper/i);
  assert.doesNotMatch(mapUi, /driver_id|vehicle_id|\bplate\b|\bETA\b|toFixed\(/);
  assert.doesNotMatch(mapModel, /EuropeanFleetLocations|GetShipmentRoute|live-fleet/);
});

check('no native map / realtime / new package dependencies', () => {
  assert.doesNotMatch(pkg, /mapbox|@tanstack\/react-query/i);
  assert.doesNotMatch(progress, /react-native-maps|MapView|SafeMap|Mapbox|Leaflet/);
  assert.doesNotMatch(mapUi, /react-native-maps|MapView|SafeMap/);
  assert.doesNotMatch(progress, /Echo|Reverb|WebSocket/);
  assert.match(mapModel, /MAX_RENDERED_ROUTE_POINTS = 64/);
});

check('loading / error / retry boundary preserved on screen (map is presentational)', () => {
  assert.match(progress, /shipment-progress-loading/);
  assert.match(progress, /shipment-progress-error/);
  assert.match(progress, /shipment-progress-retry/);
  assert.match(progress, /shipment-progress-forbidden/);
  assert.match(progress, /shipment-progress-not-found/);
  assert.match(progress, /statusCode === 401/);
  assert.match(progress, /errorStatus === 403/);
  assert.match(progress, /errorStatus === 404/);
  assert.match(progress, /errorIsNetwork/);
  assert.doesNotMatch(mapUi, /shipment-progress-loading|shipment-progress-retry/);
});

check('responsive / long-text safety hooks present', () => {
  assert.match(progress, /numberOfLines=\{2\}/);
  assert.match(progress, /numberOfLines=\{3\}/);
  assert.match(progress, /flexShrink: 1/);
  assert.match(progress, /keyboardShouldPersistTaps="handled"/);
  assert.match(mapUi, /numberOfLines=\{2\}/);
});

console.log('');
console.log(`C5.5-C4.3 screen integration checks: ${passed} passed, ${failed} failed`);
if (failed > 0) process.exit(1);
