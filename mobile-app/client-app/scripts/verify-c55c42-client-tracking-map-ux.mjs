/**
 * C5.5-C4.2 — Client shipment tracking map UX refinement verifier.
 * Run: node ./scripts/verify-c55c42-client-tracking-map-ux.mjs
 *       npm run test:c55c42-client-tracking-map-ux
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

function trackingMapModeSummary(geoMode) {
  switch (geoMode) {
    case 'full':
      return 'Planned route and latest known location';
    case 'hubs':
      return 'Pickup and delivery locations';
    case 'locationOnly':
      return 'Latest known shipment location';
    case 'none':
    default:
      return 'Location details are currently unavailable';
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

const progress = readClient('src/app/shipment-tracking/[id].tsx');
const mapUi = readClient('src/features/shipments/ShipmentTrackingMap.tsx');
const mapModel = readClient('src/features/shipments/shipmentTrackingMap.ts');
const api = readClient('src/features/shipments/shipmentsApi.ts');
const pkg = readClient('package.json');

check('map card header: Shipment journey + mode summaries', () => {
  assert.match(mapUi, /Shipment journey/);
  assert.match(mapUi, /shipment-progress-map-title/);
  assert.match(mapUi, /shipment-progress-map-summary/);
  assert.match(mapModel, /trackingMapModeSummary/);
  assert.equal(trackingMapModeSummary('full'), 'Planned route and latest known location');
  assert.equal(trackingMapModeSummary('hubs'), 'Pickup and delivery locations');
  assert.equal(trackingMapModeSummary('locationOnly'), 'Latest known shipment location');
  assert.equal(trackingMapModeSummary('none'), 'Location details are currently unavailable');
  assert.match(mapModel, /Planned route and latest known location/);
  assert.match(mapModel, /Pickup and delivery locations/);
  assert.match(mapModel, /Latest known shipment location/);
  assert.match(mapModel, /Location details are currently unavailable/);
});

check('journey legend concepts only (no driver/vehicle/ETA)', () => {
  assert.match(mapUi, /shipment-progress-map-legend/);
  assert.match(mapUi, /label: 'Pickup'/);
  assert.match(mapUi, /label: 'Planned route'/);
  assert.match(mapUi, /label: 'Last known'/);
  assert.match(mapUi, /label: 'Delivery'/);
  assert.doesNotMatch(mapUi, /\bETA\b|Estimated arrival|Arriving in|On time|Delayed/i);
  assert.doesNotMatch(mapUi, /\bdriver\b|\bvehicle\b|\bplate\b|traffic|rerouting|live tracking/i);
  assert.doesNotMatch(progress, /\bETA\b|Estimated arrival|Arriving in/i);
  assert.doesNotMatch(progress, /driver_id|vehicle_id|\bplate\b/i);
});

check('freshness wording uses backend state (no client recalculation)', () => {
  assert.equal(locationFreshnessLabel('fresh'), 'Location updated recently');
  assert.equal(locationFreshnessLabel('stale'), 'Last location update was some time ago');
  assert.equal(locationFreshnessLabel('very_stale'), 'Location update may be outdated');
  assert.equal(locationFreshnessLabel('unavailable'), 'Location unavailable');
  assert.match(mapModel, /Location updated recently/);
  assert.match(mapModel, /Last location update was some time ago/);
  assert.match(mapModel, /Location update may be outdated/);
  assert.match(mapModel, /Location unavailable/);
  assert.doesNotMatch(mapModel, /setInterval|Date\.now\(\)|ageSeconds\s*[+\-*/]/);
  assert.match(mapUi, /locationFreshnessLabel\(model\.locationFreshness\.state\)/);
});

check('planned delivery context without ETA semantics', () => {
  assert.match(mapUi, /Planned delivery/);
  assert.match(mapUi, /formatPlannedDeliveryLabel/);
  assert.match(mapUi, /shipment-progress-map-planned-delivery/);
  assert.match(mapModel, /formatPlannedDeliveryLabel/);
  assert.doesNotMatch(mapUi, /\bETA\b|Estimated arrival|Arriving in/i);
  assert.doesNotMatch(mapModel, /\bETA\b|Estimated arrival|Arriving in|On time|Delayed/i);
});

check('geo-mode behavior preserved: full / hubs / locationOnly / none', () => {
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
  assert.deepEqual(
    full.markers.map((m) => m.kind),
    ['origin', 'destination', 'lastKnown']
  );

  const hubs = buildTrackingMapModel({
    origin: { coordinates: { latitude: 52.2, longitude: 21.0 } },
    destination: { coordinates: { latitude: 50.0, longitude: 19.0 } },
    lastKnownPosition: null,
    route: null,
    locationFreshness: { state: 'unavailable', recordedAt: null, ageSeconds: 0 },
  });
  assert.equal(hubs.geoMode, 'hubs');
  assert.equal(hubs.routeCoordinates.length, 0);
  assert.ok(!hubs.markers.some((m) => m.kind === 'lastKnown'));

  const onlyLoc = buildTrackingMapModel({
    origin: { coordinates: null },
    destination: { coordinates: null },
    lastKnownPosition: { latitude: 51.1, longitude: 20.2 },
    route: null,
    locationFreshness: { state: 'stale', recordedAt: 'x', ageSeconds: 400 },
  });
  assert.equal(onlyLoc.geoMode, 'locationOnly');
  assert.equal(onlyLoc.routeCoordinates.length, 0);
  assert.deepEqual(
    onlyLoc.markers.map((m) => m.kind),
    ['lastKnown']
  );

  const none = buildTrackingMapModel({
    origin: { coordinates: null },
    destination: { coordinates: null },
    lastKnownPosition: null,
    route: null,
    locationFreshness: { state: 'unavailable', recordedAt: null, ageSeconds: 0 },
  });
  assert.equal(none.geoMode, 'none');
  assert.equal(none.hasGeographicData, false);
  assert.match(mapUi, /shipment-progress-map-empty/);
  assert.match(mapUi, /No map details yet/);
  assert.match(mapUi, /Location details are currently unavailable for this shipment/);
});

check('no fabricated hub-to-hub route when planned route missing', () => {
  assert.match(mapModel, /never invent a hub-to-hub/i);
  assert.match(mapUi, /Planned route is not available/);
  const hubs = buildTrackingMapModel({
    origin: { coordinates: { latitude: 52.2, longitude: 21.0 } },
    destination: { coordinates: { latitude: 50.0, longitude: 19.0 } },
    lastKnownPosition: { latitude: 51.0, longitude: 20.0 },
    route: null,
    locationFreshness: { state: 'fresh', recordedAt: 'x', ageSeconds: 1 },
  });
  assert.equal(hubs.geoMode, 'hubs');
  assert.equal(hubs.routeCoordinates.length, 0);
});

check('no raw coordinates shown to customer; no new mapping dependency', () => {
  assert.doesNotMatch(progress, /toFixed\(/);
  assert.doesNotMatch(mapUi, /toFixed\(/);
  assert.doesNotMatch(mapUi, /latitude:\s*\{|longitude:\s*\{/);
  assert.doesNotMatch(pkg, /mapbox|@tanstack\/react-query/i);
  assert.doesNotMatch(mapUi, /react-native-maps|MapView|SafeMap|Mapbox|Leaflet/);
  assert.doesNotMatch(progress, /react-native-maps|MapView|SafeMap/);
  assert.match(mapModel, /MAX_RENDERED_ROUTE_POINTS = 64/);
});

check('accessibility labels cover journey concepts', () => {
  assert.match(mapModel, /accessibilityLabel: 'Pickup location'/);
  assert.match(mapModel, /accessibilityLabel: 'Delivery location'/);
  assert.match(mapModel, /accessibilityLabel: 'Last known shipment location'/);
  assert.match(mapUi, /accessibilityLabel=\{marker\.accessibilityLabel\}/);
  assert.match(mapUi, /Planned shipment route/);
  assert.match(mapUi, /accessibilityLabel=\{freshnessA11y\}/);
  assert.match(mapUi, /accessibilityLabel=\{`Planned delivery \$\{plannedDelivery\}`\}/);
});

check('mapper / API / loading boundary unchanged for C4.2', () => {
  assert.match(api, /export function mapClientShipmentTracking/);
  assert.doesNotMatch(api, /ShipmentTrackingMap|trackingMapModeSummary/);
  assert.match(progress, /getClientShipmentTracking/);
  assert.match(progress, /ShipmentTrackingMap/);
  assert.doesNotMatch(progress, /Echo|Reverb|WebSocket|setInterval/);
  assert.doesNotMatch(mapModel, /EuropeanFleetLocations|GetShipmentRoute|live-fleet/);
});

console.log('');
console.log(`C5.5-C4.2 UX checks: ${passed} passed, ${failed} failed`);
if (failed > 0) process.exit(1);
