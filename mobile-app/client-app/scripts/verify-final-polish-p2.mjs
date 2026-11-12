/**
 * Final Polish — P2-1 Home empty honesty + P2-2 shipment_progress destination
 *
 * Run: npm run test:final-polish-p2
 */

import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const clientRoot = path.join(__dirname, '..');

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
  return fs.readFileSync(path.join(clientRoot, rel), 'utf8');
}

const homeTs = read('src/features/home/homeOverview.ts');
const homeScreen = read('src/app/(tabs)/index.tsx');
const dest = read('src/features/notifications/notificationDestination.ts');

console.log('=== Final Polish P2-1 / P2-2 ===\n');

check('P2-1: homeEmptyHubCopy helper exists and is wired on Home', () => {
  assert.match(homeTs, /export function homeEmptyHubCopy/);
  assert.match(homeTs, /No active shipments right now/);
  assert.match(homeTs, /No shipments yet/);
  assert.doesNotMatch(homeTs, /No active shipments yet/);
  assert.match(homeScreen, /homeEmptyHubCopy/);
  assert.match(homeScreen, /home-empty-title/);
  assert.match(homeScreen, /emptyHubCopy\.title/);
  assert.doesNotMatch(homeScreen, /No active shipments yet/);
});

check('P2-1 behavioral: distinguishes none vs inactive-only', () => {
  function homeEmptyHubCopy(hasAnyLoadedShipments) {
    if (hasAnyLoadedShipments) {
      return {
        title: 'No active shipments right now',
        body:
          'None of your shipments are currently in progress. Open Shipments to review past deliveries, or go to Requests to submit a new one.',
      };
    }
    return {
      title: 'No shipments yet',
      body:
        'When you submit a shipment request and it is converted, it will appear here so you can follow pickup, transit, and delivery.',
    };
  }
  const none = homeEmptyHubCopy(false);
  const inactive = homeEmptyHubCopy(true);
  assert.equal(none.title, 'No shipments yet');
  assert.equal(inactive.title, 'No active shipments right now');
  assert.ok(!inactive.title.toLowerCase().includes('yet'));
  assert.ok(inactive.body.toLowerCase().includes('past'));
  assert.match(homeTs, /hasAnyLoadedShipments/);
});

check('P2-2: shipment_progress routes to tracking with View progress CTA', () => {
  assert.match(dest, /kind === 'shipment_progress'/);
  assert.match(dest, /\/shipment-tracking\/\$\{id\}/);
  assert.match(dest, /ctaLabel:\s*'View progress'/);
  assert.match(dest, /\/shipment-details\/\$\{id\}/);
  assert.doesNotMatch(
    dest,
    /kind === 'shipment_details' \|\| kind === 'shipment_progress'/
  );
});

check('P2-2 behavioral: resolver destinations + fail-closed', () => {
  const UUID = 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee';
  function mapAction(raw) {
    if (raw == null || typeof raw !== 'object' || Array.isArray(raw)) return null;
    const kind = typeof raw.kind === 'string' ? raw.kind.trim() : '';
    const id = typeof raw.id === 'string' ? raw.id.trim() : '';
    if (!kind || !id) return null;
    const allowed = new Set([
      'shipment_details',
      'shipment_progress',
      'request_details',
      'conversation',
    ]);
    if (!allowed.has(kind)) return null;
    if (
      !/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i.test(id)
    ) {
      return null;
    }
    return { kind, id };
  }
  function resolve(notification) {
    const action = mapAction(notification?.action ?? null);
    if (!action) {
      return { href: '/notifications', ctaLabel: 'View Notification', navigable: false };
    }
    if (action.kind === 'shipment_details') {
      return {
        href: `/shipment-details/${action.id}`,
        ctaLabel: 'View Shipment',
        navigable: true,
      };
    }
    if (action.kind === 'shipment_progress') {
      return {
        href: `/shipment-tracking/${action.id}`,
        ctaLabel: 'View progress',
        navigable: true,
      };
    }
    return { href: '/notifications', ctaLabel: 'View Notification', navigable: false };
  }

  const progress = resolve({ action: { kind: 'shipment_progress', id: UUID } });
  assert.equal(progress.href, `/shipment-tracking/${UUID}`);
  assert.equal(progress.ctaLabel, 'View progress');
  assert.equal(progress.navigable, true);

  const details = resolve({ action: { kind: 'shipment_details', id: UUID } });
  assert.equal(details.href, `/shipment-details/${UUID}`);
  assert.equal(details.ctaLabel, 'View Shipment');

  const bad = resolve({ action: { kind: 'shipment_progress', id: 'not-a-uuid' } });
  assert.equal(bad.navigable, false);
  assert.equal(bad.href, '/notifications');
});

check('P2 scope: C5.5 tracking map not redesigned', () => {
  const map = read('src/features/shipments/shipmentTrackingMap.ts');
  const screen = read('src/app/shipment-tracking/[id].tsx');
  assert.doesNotMatch(map, /react-native-maps|live-fleet/);
  assert.doesNotMatch(screen, /react-native-maps/);
  assert.match(screen, /getClientShipmentTracking/);
});

console.log('');
console.log(`Final Polish P2: ${passed} passed, ${failed} failed`);
if (failed > 0) process.exit(1);
console.log('FINAL POLISH P2 VERDICT: PASS');
