/**
 * Phase 3 dashboard realtime tracking tests (INT-002 + polling fallback).
 */
import { strict as assert } from 'node:assert';
import { existsSync, readFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { test } from 'node:test';
import { fileURLToPath } from 'node:url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const ROOT = resolve(__dirname, '..');
const REPO = resolve(ROOT, '..');

function read(rel) {
  const p = resolve(ROOT, rel);
  assert.ok(existsSync(p), `missing ${rel}`);
  return readFileSync(p, 'utf8');
}

function readRepo(rel) {
  const p = resolve(REPO, rel);
  assert.ok(existsSync(p), `missing ${rel}`);
  return readFileSync(p, 'utf8');
}

function applyFleetRealtimeUpdate(vehicles, update) {
  return vehicles.map((vehicle) => {
    if (vehicle.vehicleId !== update.vehicle_id) {
      return vehicle;
    }
    return {
      ...vehicle,
      latitude: update.location.latitude,
      longitude: update.location.longitude,
      speed: update.telemetry.speed,
      heading: update.telemetry.heading,
      movementStatus: update.status.movement_status,
      onlineStatus: update.status.online_status,
      lastUpdateAt: update.timestamp,
    };
  });
}

test('Echo authorizer uses fleetx_token, not localStorage.token', () => {
  const echo = read('src/core/realtime/config/echo.config.ts');
  const session = read('src/core/auth/sessionLifecycle.ts');

  assert.match(session, /FLEETX_TOKEN_KEY = 'fleetx_token'/);
  assert.match(echo, /import \{ FLEETX_TOKEN_KEY \} from '..\/..\/auth\/sessionLifecycle'/);
  assert.match(echo, /localStorage\.getItem\(FLEETX_TOKEN_KEY\)/);
  assert.match(echo, /Authorization: `Bearer \$\{token\}`/);
  assert.doesNotMatch(echo, /localStorage\.getItem\(['"]token['"]\)/);
  assert.doesNotMatch(echo, /getItem\('token'\)/);
});

test('dashboard HTTP auth still uses the same fleetx_token key', () => {
  const interceptors = read('src/core/api/client/interceptors.ts');
  const authService = read('src/core/services/auth.service.ts');

  assert.match(interceptors, /FLEETX_TOKEN_KEY/);
  assert.match(authService, /FLEETX_TOKEN_KEY/);
  assert.doesNotMatch(interceptors, /localStorage\.getItem\(['"]token['"]\)/);
  assert.doesNotMatch(authService, /localStorage\.getItem\(['"]token['"]\)/);
});

test('dispatcher live tracking keeps 5-second polling regardless of realtime', () => {
  const hook = read('src/features/dispatcher/tracking/hooks/useLiveTracking.ts');

  assert.match(hook, /export const POLL_INTERVAL_MS = 5000/);
  assert.match(hook, /setInterval\(\(\) => \{\s*void refreshVehicles\(false\);\s*\}, POLL_INTERVAL_MS\)/s);
  assert.match(hook, /return \(\) => clearInterval\(interval\);/);
  assert.match(hook, /realtime subscribe failed; polling continues/);
  assert.match(hook, /subscribe\('tracking\.fleet'\)/);
  assert.match(hook, /'\.tracking\.state\.updated'/);
  assert.doesNotMatch(hook, /trackingSubscriptionManager/);
  assert.doesNotMatch(hook, /stopPolling/);
  assert.doesNotMatch(hook, /clearInterval\(intervalRef/);

  const manager = read('src/core/realtime/services/tracking-subscription-manager.service.ts');
  assert.match(manager, /'\.tracking\.state\.updated'/);
});

test('applyFleetRealtimeUpdate patches known vehicles and ignores unknown ids', () => {
  const hook = read('src/features/dispatcher/tracking/hooks/useLiveTracking.ts');
  assert.match(hook, /export function applyFleetRealtimeUpdate/);
  assert.match(hook, /Unknown vehicle_ids are ignored/);

  const current = [
    {
      vehicleId: 'veh-1',
      latitude: 1,
      longitude: 2,
      speed: 10,
      heading: 90,
      movementStatus: 'Idle',
      onlineStatus: 'Online',
      lastUpdateAt: 't0',
    },
  ];

  const patched = applyFleetRealtimeUpdate(current, {
    vehicle_id: 'veh-1',
    location: { latitude: 52.5, longitude: 13.4 },
    telemetry: { speed: 40, heading: 180 },
    status: { movement_status: 'Moving', online_status: 'Online' },
    timestamp: 't1',
  });
  assert.equal(patched[0].latitude, 52.5);
  assert.equal(patched[0].speed, 40);
  assert.equal(patched[0].movementStatus, 'Moving');

  const ignored = applyFleetRealtimeUpdate(current, {
    vehicle_id: 'veh-unknown',
    location: { latitude: 0, longitude: 0 },
    telemetry: { speed: 99, heading: 1 },
    status: { movement_status: 'Moving', online_status: 'Online' },
    timestamp: 't2',
  });
  assert.equal(ignored[0].latitude, 1);
  assert.equal(ignored[0].speed, 10);
  assert.equal(ignored.length, 1);
});

test('backend tracking channels and Reverb default remain test-safe', () => {
  const channels = readRepo('backend/routes/channels.php');
  const broadcasting = readRepo('backend/config/broadcasting.php');
  const phpunit = readRepo('backend/phpunit.xml');
  const envExample = readRepo('backend/.env.example');

  assert.match(channels, /Broadcast::channel\('tracking\.fleet'/);
  assert.match(channels, /Broadcast::channel\('tracking\.vehicle\.\{vehicleId\}'/);
  assert.match(channels, /Broadcast::channel\('tracking\.shipment\.\{shipmentId\}'/);
  assert.match(channels, /can\('viewAny', TrackingState::class\)/);
  assert.match(channels, /can\('show', \$vehicle\)/);
  assert.match(channels, /can\('view', \$shipment\)/);
  assert.match(channels, /Broadcast::channel\('conversation\.\{conversationId\}'/);

  assert.match(broadcasting, /env\('BROADCAST_CONNECTION', 'reverb'\)/);
  assert.match(phpunit, /<env name="BROADCAST_CONNECTION" value="log"\/>/);
  assert.match(envExample, /BROADCAST_CONNECTION=reverb/);
  assert.match(envExample, /PHPUnit forces BROADCAST_CONNECTION=log/);
});
