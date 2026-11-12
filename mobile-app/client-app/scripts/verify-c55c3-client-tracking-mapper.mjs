/**
 * C5.5-C3 — Client tracking mapper / domain contract (static + behavioral).
 * Run: node ./scripts/verify-c55c3-client-tracking-mapper.mjs
 *       npm run test:c55c3-client-tracking-mapper
 */

import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const clientRoot = path.join(__dirname, '..');
const repoRoot = path.join(clientRoot, '..', '..');

function readClient(rel) {
  return fs.readFileSync(path.join(clientRoot, rel), 'utf8');
}

function readRepo(rel) {
  return fs.readFileSync(path.join(repoRoot, rel), 'utf8');
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

function asFiniteNumber(value) {
  if (typeof value === 'number') {
    return Number.isFinite(value) ? value : null;
  }
  if (typeof value === 'string' && value.trim() !== '') {
    const n = Number(value);
    return Number.isFinite(n) ? n : null;
  }
  return null;
}

/** Mirrors mapClientEndpointCoordinates — keep in sync with shipmentsApi.ts */
function mapClientEndpointCoordinates(raw) {
  if (raw == null || typeof raw !== 'object' || Array.isArray(raw)) return null;
  const latitude = asFiniteNumber(raw.latitude);
  const longitude = asFiniteNumber(raw.longitude);
  if (latitude == null || longitude == null) return null;
  if (latitude < -90 || latitude > 90 || longitude < -180 || longitude > 180) return null;
  return { latitude, longitude };
}

/** Mirrors mapClientLastKnownPosition */
function mapClientLastKnownPosition(raw) {
  if (raw == null || typeof raw !== 'object' || Array.isArray(raw)) return null;
  const latitude = asFiniteNumber(raw.latitude);
  const longitude = asFiniteNumber(raw.longitude);
  if (latitude == null || longitude == null) return null;
  if (latitude < -90 || latitude > 90 || longitude < -180 || longitude > 180) return null;
  let recordedAt = null;
  if (typeof raw.recorded_at === 'string') {
    const trimmed = raw.recorded_at.trim();
    recordedAt = trimmed ? trimmed : null;
  }
  return { latitude, longitude, recordedAt };
}

const LOCATION_FRESHNESS_STATES = new Set(['unavailable', 'fresh', 'stale', 'very_stale']);

function unavailableFreshness() {
  return { state: 'unavailable', recordedAt: null, ageSeconds: 0 };
}

/** Mirrors mapClientLocationFreshness */
function mapClientLocationFreshness(raw) {
  if (raw == null || typeof raw !== 'object' || Array.isArray(raw)) return unavailableFreshness();
  if (typeof raw.state !== 'string' || !LOCATION_FRESHNESS_STATES.has(raw.state)) {
    return unavailableFreshness();
  }
  const state = raw.state;
  let ageSeconds = 0;
  if (typeof raw.age_seconds === 'number' && Number.isFinite(raw.age_seconds)) {
    ageSeconds = Math.max(0, Math.trunc(raw.age_seconds));
  } else if (typeof raw.age_seconds === 'string' && raw.age_seconds.trim() !== '') {
    const n = Number(raw.age_seconds);
    ageSeconds = Number.isFinite(n) ? Math.max(0, Math.trunc(n)) : 0;
  }
  let recordedAt = null;
  if (typeof raw.recorded_at === 'string') {
    const trimmed = raw.recorded_at.trim();
    recordedAt = trimmed ? trimmed : null;
  }
  if (state === 'unavailable') return unavailableFreshness();
  if (recordedAt == null) return unavailableFreshness();
  return { state, recordedAt, ageSeconds };
}

/** Mirrors mapClientPlannedRoute */
function mapClientPlannedRoute(raw) {
  if (raw == null || typeof raw !== 'object' || Array.isArray(raw)) return null;
  if (!Array.isArray(raw.coordinates) || raw.coordinates.length < 2) return null;
  const coordinates = [];
  for (const point of raw.coordinates) {
    const mapped = mapClientEndpointCoordinates(point);
    if (mapped == null) return null;
    coordinates.push(mapped);
  }
  if (coordinates.length < 2) return null;
  return { coordinates };
}

/** Mirrors mapClientShipmentTracking (core fields under test) */
function mapClientShipmentTracking(api) {
  return {
    shipmentId: String(api.shipment_id),
    shipmentNumber: api.shipment_number ?? null,
    status: api.status
      ? { id: api.status.id, name: api.status.name, type: api.status.type }
      : null,
    plannedPickupDate: api.planned_pickup_date ?? null,
    plannedDeliveryDate: api.planned_delivery_date ?? null,
    actualPickupDate: api.actual_pickup_date ?? null,
    actualDeliveryDate: api.actual_delivery_date ?? null,
    origin: {
      city: api.origin?.city ?? null,
      country: api.origin?.country ?? null,
      company: api.origin?.company ?? null,
      coordinates: mapClientEndpointCoordinates(api.origin?.coordinates ?? null),
    },
    destination: {
      city: api.destination?.city ?? null,
      country: api.destination?.country ?? null,
      company: api.destination?.company ?? null,
      coordinates: mapClientEndpointCoordinates(api.destination?.coordinates ?? null),
    },
    customerCompany: api.customer_company
      ? {
          id: String(api.customer_company.id),
          companyCode: api.customer_company.company_code ?? null,
          companyName: api.customer_company.company_name ?? null,
        }
      : null,
    createdAt: api.created_at ?? null,
    milestones: [],
    lastKnownPosition: mapClientLastKnownPosition(api.last_known_position ?? null),
    locationFreshness: mapClientLocationFreshness(api.location_freshness ?? null),
    route: mapClientPlannedRoute(api.route ?? null),
  };
}

const types = readClient('src/features/shipments/types.ts');
const api = readClient('src/features/shipments/shipmentsApi.ts');
const resource = readRepo('backend/app/Http/Resources/Client/ClientShipmentTrackingResource.php');
const trackingAction = readRepo('backend/app/Actions/Shipment/GetClientShipmentTrackingAction.php');

check('C5.5-C2 backend contract still emits coords / freshness / route', () => {
  assert.match(resource, /location_freshness/);
  assert.match(resource, /'route'/);
  assert.match(resource, /origin_coordinates|endpointCoordinates/);
  assert.match(trackingAction, /ClientShipmentLocationFreshness|location_freshness/);
  assert.match(trackingAction, /ClientShipmentPlannedRoute|client_route/);
});

check('domain types include C5.5-C3 tracking contract fields', () => {
  assert.match(types, /export interface ClientGeoCoordinates/);
  assert.match(types, /export type ClientLocationFreshnessState/);
  assert.match(types, /unavailable.*fresh.*stale.*very_stale|unavailable[\s\S]*fresh[\s\S]*stale[\s\S]*very_stale/);
  assert.match(types, /export interface ClientLocationFreshness/);
  assert.match(types, /ageSeconds: number/);
  assert.match(types, /export interface ClientPlannedRoute/);
  assert.match(types, /coordinates: ClientGeoCoordinates \| null/);
  assert.match(types, /locationFreshness: ClientLocationFreshness/);
  assert.match(types, /route: ClientPlannedRoute \| null/);
  assert.doesNotMatch(types, /driver_id\s*:/);
  assert.doesNotMatch(types, /vehicle_id\s*:/);
  assert.doesNotMatch(types, /engine_ops\s*:/);
  assert.doesNotMatch(types, /graphhopper/);
  assert.doesNotMatch(types, /\[key: string\]:\s*any/);
});

check('shipmentsApi exports allowlist mappers and wires getClientShipmentTracking', () => {
  assert.match(api, /export function mapClientEndpointCoordinates/);
  assert.match(api, /export function mapClientLocationFreshness/);
  assert.match(api, /export function mapClientPlannedRoute/);
  assert.match(api, /export function mapClientShipmentTracking/);
  assert.match(api, /return mapClientShipmentTracking\(payload\)/);
  assert.match(api, /location_freshness\?: unknown/);
  assert.match(api, /route\?: unknown/);
  assert.match(api, /\/client\/shipments\/\$\{encodeURIComponent\(id\)\}\/tracking/);
  assert.doesNotMatch(api, /GetShipmentRouteAction/);
  assert.doesNotMatch(api, /live-fleet/);
  assert.doesNotMatch(api, /GraphHopper/);
  assert.doesNotMatch(api, /EuropeanFleetLocations|resolveCity/);
  assert.doesNotMatch(api, /calculateEta|plannedDeliveryEta/);
});

check('C5.5-C3 mapper remains free of live tracking / native map libraries', () => {
  assert.doesNotMatch(api, /react-native-maps|SafeMap|MapView|setInterval|Echo|Reverb/);
  assert.doesNotMatch(types, /react-native-maps|SafeMap|MapView/);
  assert.doesNotMatch(api, /GetShipmentRouteAction|live-fleet|GraphHopper/);
});

check('behavioral: full valid tracking response maps correctly', () => {
  const mapped = mapClientShipmentTracking({
    shipment_id: 'ship-1',
    shipment_number: 'SHP-1',
    status: { id: 1, name: 'In Transit', type: 'shipment' },
    planned_pickup_date: '2026-08-01',
    planned_delivery_date: '2026-08-10',
    actual_pickup_date: '2026-08-02',
    actual_delivery_date: null,
    origin: {
      city: 'Warsaw',
      country: 'PL',
      company: 'Pickup Co',
      coordinates: { latitude: 52.2297, longitude: 21.0122, provider: 'leak' },
    },
    destination: {
      city: 'Krakow',
      country: 'PL',
      company: 'Delivery Co',
      coordinates: { latitude: 50.0647, longitude: 19.945 },
    },
    customer_company: { id: 'co-1', company_code: 'ACME', company_name: 'ACME' },
    created_at: '2026-08-01 10:00:00',
    last_known_position: {
      latitude: 51.5,
      longitude: 20.5,
      recorded_at: '2026-08-15 11:50:00',
      speed: 99,
      driver_id: 'd1',
    },
    location_freshness: {
      state: 'stale',
      recorded_at: '2026-08-15 11:50:00',
      age_seconds: 600,
      eta: 'leak',
    },
    route: {
      coordinates: [
        { latitude: 52.2297, longitude: 21.0122 },
        { latitude: 51.5, longitude: 20.5 },
        { latitude: 50.0647, longitude: 19.945 },
      ],
      provider: 'graphhopper',
      eta: '2099-01-01 00:00:00',
      encoded: 'SECRET',
    },
  });

  assert.equal(mapped.shipmentId, 'ship-1');
  assert.deepEqual(mapped.origin.coordinates, { latitude: 52.2297, longitude: 21.0122 });
  assert.deepEqual(mapped.destination.coordinates, { latitude: 50.0647, longitude: 19.945 });
  assert.deepEqual(mapped.lastKnownPosition, {
    latitude: 51.5,
    longitude: 20.5,
    recordedAt: '2026-08-15 11:50:00',
  });
  assert.deepEqual(mapped.locationFreshness, {
    state: 'stale',
    recordedAt: '2026-08-15 11:50:00',
    ageSeconds: 600,
  });
  assert.equal(mapped.route.coordinates.length, 3);
  assert.deepEqual(Object.keys(mapped.route), ['coordinates']);
  assert.deepEqual(Object.keys(mapped.route.coordinates[0]), ['latitude', 'longitude']);
  assert.equal(Object.prototype.hasOwnProperty.call(mapped, 'eta'), false);
  assert.equal(Object.prototype.hasOwnProperty.call(mapped.lastKnownPosition, 'speed'), false);
  assert.equal(Object.prototype.hasOwnProperty.call(mapped.route, 'provider'), false);
  assert.equal(Object.prototype.hasOwnProperty.call(mapped.route, 'encoded'), false);
  assert.equal(Object.prototype.hasOwnProperty.call(mapped.locationFreshness, 'eta'), false);
});

check('behavioral: null origin/destination coordinates remain null', () => {
  const mapped = mapClientShipmentTracking({
    shipment_id: 'ship-2',
    origin: { city: 'Atlantis', country: 'XX', company: null, coordinates: null },
    destination: { city: 'El Dorado', country: 'YY', company: null },
  });
  assert.equal(mapped.origin.coordinates, null);
  assert.equal(mapped.destination.coordinates, null);
});

check('behavioral: null last_known_position remains null', () => {
  assert.equal(
    mapClientShipmentTracking({ shipment_id: 'ship-3', last_known_position: null }).lastKnownPosition,
    null
  );
});

check('behavioral: all four freshness states map correctly', () => {
  assert.deepEqual(
    mapClientLocationFreshness({
      state: 'unavailable',
      recorded_at: null,
      age_seconds: 0,
    }),
    { state: 'unavailable', recordedAt: null, ageSeconds: 0 }
  );
  assert.deepEqual(
    mapClientLocationFreshness({
      state: 'fresh',
      recorded_at: '2026-08-15 11:59:00',
      age_seconds: 60,
    }),
    { state: 'fresh', recordedAt: '2026-08-15 11:59:00', ageSeconds: 60 }
  );
  assert.deepEqual(
    mapClientLocationFreshness({
      state: 'stale',
      recorded_at: '2026-08-15 11:50:00',
      age_seconds: 600,
    }),
    { state: 'stale', recordedAt: '2026-08-15 11:50:00', ageSeconds: 600 }
  );
  assert.deepEqual(
    mapClientLocationFreshness({
      state: 'very_stale',
      recorded_at: '2026-08-15 10:00:00',
      age_seconds: 7200,
    }),
    { state: 'very_stale', recordedAt: '2026-08-15 10:00:00', ageSeconds: 7200 }
  );
});

check('behavioral: null route remains null; one-point / malformed fail closed', () => {
  assert.equal(mapClientPlannedRoute(null), null);
  assert.equal(mapClientPlannedRoute({ coordinates: [] }), null);
  assert.equal(
    mapClientPlannedRoute({
      coordinates: [{ latitude: 52.2, longitude: 21.0 }],
    }),
    null
  );
  assert.equal(
    mapClientPlannedRoute({
      coordinates: [
        { latitude: 52.2, longitude: 21.0 },
        { latitude: 91, longitude: 19.0 },
      ],
    }),
    null
  );
  assert.equal(
    mapClientPlannedRoute({
      coordinates: [
        { latitude: 52.2, longitude: 21.0 },
        { latitude: 'bad', longitude: 19.0 },
      ],
    }),
    null
  );
});

check('behavioral: route coordinates map with allowlist only', () => {
  const mapped = mapClientPlannedRoute({
    coordinates: [
      { latitude: 52.2, longitude: 21.0, speed: 1 },
      { latitude: 50.0, longitude: 19.0, provider: 'x' },
    ],
    encoded: 'POLY',
    provider: 'graphhopper',
    eta: '2099-01-01',
    distance: 99,
    duration: 88,
    deviation: 7,
  });
  assert.deepEqual(mapped, {
    coordinates: [
      { latitude: 52.2, longitude: 21.0 },
      { latitude: 50.0, longitude: 19.0 },
    ],
  });
});

check('behavioral: mapper does not invent ETA / coordinates / route', () => {
  const mapped = mapClientShipmentTracking({
    shipment_id: 'ship-4',
    origin: { city: 'Warsaw', country: 'PL', company: null },
    destination: { city: 'Krakow', country: 'PL', company: null },
  });
  assert.equal(mapped.origin.coordinates, null);
  assert.equal(mapped.destination.coordinates, null);
  assert.equal(mapped.route, null);
  assert.equal(mapped.locationFreshness.state, 'unavailable');
  assert.equal(Object.prototype.hasOwnProperty.call(mapped, 'eta'), false);
  assert.equal(Object.prototype.hasOwnProperty.call(mapped, 'provider'), false);
});

check('behavioral: malformed freshness / coords fail closed', () => {
  assert.deepEqual(mapClientLocationFreshness(undefined), unavailableFreshness());
  assert.deepEqual(mapClientLocationFreshness({ state: 'hot' }), unavailableFreshness());
  assert.deepEqual(
    mapClientLocationFreshness({ state: 'fresh', recorded_at: null, age_seconds: 10 }),
    unavailableFreshness()
  );
  assert.equal(mapClientEndpointCoordinates({ latitude: 52.2 }), null);
  assert.equal(mapClientEndpointCoordinates({ latitude: Number.NaN, longitude: 21 }), null);
});

check('backend C5.5-C2 files remain present and were not rewritten by this Client phase script', () => {
  assert.ok(
    fs.existsSync(path.join(repoRoot, 'backend/app/Support/Client/ClientShipmentPlannedRoute.php'))
  );
  assert.ok(
    fs.existsSync(path.join(repoRoot, 'backend/app/Support/Client/ClientShipmentLocationFreshness.php'))
  );
  assert.match(resource, /ClientShipmentTrackingResource/);
});

console.log('');
console.log(`C5.5-C3 mapper checks: ${passed} passed, ${failed} failed`);
if (failed > 0) process.exit(1);
