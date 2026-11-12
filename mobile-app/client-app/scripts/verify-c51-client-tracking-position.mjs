/**
 * C5.1 — Client tracking last_known_position consumption (static + behavioral).
 * Run: node ./scripts/verify-c51-client-tracking-position.mjs
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

/** Mirrors mapClientLastKnownPosition in shipmentsApi.ts — keep in sync. */
function mapClientLastKnownPosition(raw) {
  if (raw == null || typeof raw !== 'object' || Array.isArray(raw)) {
    return null;
  }

  const latitude = asFiniteNumber(raw.latitude);
  const longitude = asFiniteNumber(raw.longitude);
  if (latitude == null || longitude == null) return null;
  if (latitude < -90 || latitude > 90 || longitude < -180 || longitude > 180) {
    return null;
  }

  let recordedAt = null;
  if (typeof raw.recorded_at === 'string') {
    const trimmed = raw.recorded_at.trim();
    recordedAt = trimmed ? trimmed : null;
  }

  return { latitude, longitude, recordedAt };
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

const types = readClient('src/features/shipments/types.ts');
const api = readClient('src/features/shipments/shipmentsApi.ts');
const progress = readClient('src/app/shipment-tracking/[id].tsx');
const resource = readRepo('backend/app/Http/Resources/Client/ClientShipmentTrackingResource.php');
const trackingAction = readRepo('backend/app/Actions/Shipment/GetClientShipmentTrackingAction.php');

check('backend already emits last_known_position (no Client phase backend rewrite)', () => {
  assert.match(resource, /last_known_position/);
  assert.match(resource, /latitude/);
  assert.match(resource, /longitude/);
  assert.match(resource, /recorded_at/);
  assert.ok(fs.existsSync(path.join(repoRoot, 'backend/app/Actions/Shipment/GetClientShipmentTrackingAction.php')));
  assert.match(trackingAction, /last_known_position|lastKnownPosition/);
});

check('Client types include lastKnownPosition allowlist', () => {
  assert.match(types, /export interface ClientLastKnownPosition/);
  assert.match(types, /latitude: number/);
  assert.match(types, /longitude: number/);
  assert.match(types, /recordedAt: string \| null/);
  assert.match(types, /lastKnownPosition: ClientLastKnownPosition \| null/);
  assert.doesNotMatch(types, /speed|heading|driver|vehicle|tracking_log/i);
});

check('mapper consumes last_known_position with allowlisted fields only', () => {
  assert.match(api, /export function mapClientLastKnownPosition/);
  assert.match(api, /lastKnownPosition: mapClientLastKnownPosition/);
  assert.match(api, /last_known_position\?: unknown/);
  assert.match(api, /record\.latitude/);
  assert.match(api, /record\.longitude/);
  assert.match(api, /record\.recorded_at/);
  assert.doesNotMatch(api, /speed|heading|driver_id|vehicle_id|live-fleet/);
});

check('tracking screen renders last-known section without map/live/polling', () => {
  assert.match(progress, /shipment-progress-last-known/);
  assert.match(progress, /Last known location/);
  assert.match(progress, /Location data is not available yet/);
  assert.match(progress, /lastKnownPosition/);
  assert.match(progress, /formatLastKnownRecordedAt/);
  assert.doesNotMatch(progress, /SafeMap|react-native-maps|setInterval|Echo|Reverb|live-fleet/);
  assert.doesNotMatch(progress, /\bETA\b|polyline|driver_id|vehicle_id/);
  assert.match(progress, /Milestones/);
  assert.match(progress, /not a live GPS feed/i);
});

check('behavioral: valid position preserves latitude/longitude/recorded_at', () => {
  const mapped = mapClientLastKnownPosition({
    latitude: 34.123456,
    longitude: 35.123456,
    recorded_at: '2026-08-14 18:42:00',
    speed: 99,
    driver_id: 'leak',
  });
  assert.deepEqual(mapped, {
    latitude: 34.123456,
    longitude: 35.123456,
    recordedAt: '2026-08-14 18:42:00',
  });
  assert.equal(Object.prototype.hasOwnProperty.call(mapped, 'speed'), false);
  assert.equal(Object.prototype.hasOwnProperty.call(mapped, 'driver_id'), false);
});

check('behavioral: null / missing position returns null', () => {
  assert.equal(mapClientLastKnownPosition(null), null);
  assert.equal(mapClientLastKnownPosition(undefined), null);
  assert.equal(mapClientLastKnownPosition({}), null);
});

check('behavioral: invalid latitude rejected', () => {
  assert.equal(
    mapClientLastKnownPosition({
      latitude: 91,
      longitude: 35,
      recorded_at: '2026-08-14 18:42:00',
    }),
    null
  );
  assert.equal(
    mapClientLastKnownPosition({
      latitude: -91,
      longitude: 35,
      recorded_at: '2026-08-14 18:42:00',
    }),
    null
  );
});

check('behavioral: invalid longitude rejected', () => {
  assert.equal(
    mapClientLastKnownPosition({
      latitude: 34,
      longitude: 181,
      recorded_at: '2026-08-14 18:42:00',
    }),
    null
  );
  assert.equal(
    mapClientLastKnownPosition({
      latitude: 34,
      longitude: -181,
      recorded_at: '2026-08-14 18:42:00',
    }),
    null
  );
});

check('behavioral: non-finite coordinates rejected', () => {
  assert.equal(
    mapClientLastKnownPosition({
      latitude: Number.NaN,
      longitude: 35,
      recorded_at: '2026-08-14 18:42:00',
    }),
    null
  );
  assert.equal(
    mapClientLastKnownPosition({
      latitude: 34,
      longitude: Number.POSITIVE_INFINITY,
      recorded_at: '2026-08-14 18:42:00',
    }),
    null
  );
});

check('behavioral: valid coords without recorded_at still map (timestamp optional on Client)', () => {
  const mapped = mapClientLastKnownPosition({
    latitude: '33.5',
    longitude: '35.5',
  });
  assert.deepEqual(mapped, {
    latitude: 33.5,
    longitude: 35.5,
    recordedAt: null,
  });
});

check('milestones path and existing endpoint remain intact', () => {
  assert.match(api, /\/client\/shipments\/\$\{encodeURIComponent\(id\)\}\/tracking/);
  assert.match(progress, /shipment-progress-milestones/);
  assert.match(progress, /getClientShipmentTracking/);
  assert.doesNotMatch(api, /tracking-logs|tracking-states|live-fleet/);
});

console.log(`\nC5.1 verifier: ${passed} passed, ${failed} failed`);
if (failed > 0) {
  process.exitCode = 1;
}
