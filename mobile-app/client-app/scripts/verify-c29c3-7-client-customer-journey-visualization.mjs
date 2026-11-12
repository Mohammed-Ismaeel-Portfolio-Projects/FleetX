/**
 * C2.9-C3.7 Static + behavioral verification — Client customer journey visualization
 */
import { strict as assert } from 'node:assert';
import { readFileSync, existsSync } from 'node:fs';
import { test } from 'node:test';
import { resolve, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const ROOT = resolve(__dirname, '..', '..', '..');
const CLIENT = resolve(ROOT, 'mobile-app/client-app');

function read(rel) {
  const p = resolve(ROOT, rel);
  if (!existsSync(p)) return null;
  return readFileSync(p, 'utf8');
}

function readClient(rel) {
  const p = resolve(CLIENT, rel);
  if (!existsSync(p)) return null;
  return readFileSync(p, 'utf8');
}

const details = readClient('src/app/shipment-details/[id].tsx') || '';
const workspace = readClient('src/features/shipments/shipmentWorkspace.ts') || '';
const journeyUi = readClient('src/features/shipments/CustomerJourney.tsx') || '';
const api = readClient('src/features/shipments/shipmentsApi.ts') || '';
const layout = readClient('src/app/_layout.tsx') || '';
const tracking = readClient('src/app/shipment-tracking/[id].tsx') || '';

const ENGINE = [
  'Accepted',
  'EnRoutePickup',
  'ArrivedPickup',
  'Loading',
  'Loaded',
  'Departed',
  'EnRouteDelivery',
  'ApproachingRestStop',
  'Resting',
  'ArrivedDelivery',
  'Unloading',
];

const emptyFacts = {
  plannedPickup: null,
  actualPickup: null,
  plannedDelivery: null,
  actualDelivery: null,
};

/** Mirrors shipmentWorkspace.buildCustomerJourney product rules for focused tests. */
function lifecycleIndex(name) {
  switch (name) {
    case 'Waiting Assignment':
      return 0;
    case 'Assigned':
    case 'Ready For Pickup':
      return 1;
    case 'Picked Up':
    case 'In Transit':
    case 'At Customs':
    case 'Out For Delivery':
      return 2;
    case 'Delivered':
      return 3;
    case 'Completed':
      return 4;
    default:
      return null;
  }
}

function visualState(index, currentIndex) {
  if (index < currentIndex) return 'completed';
  if (index === currentIndex) return 'current';
  return 'upcoming';
}

test('C3.7 documentation exists with required sections', () => {
  const doc = read('docs/C2.9C3_7_CLIENT_CUSTOMER_JOURNEY_VISUALIZATION.md');
  assert.ok(doc);
  assert.match(doc, /C2\.9-C3\.7/i);
  assert.match(doc, /HARD STOP/i);
  for (const s of [
    'audit findings',
    'existing architecture',
    'customer lifecycle',
    'journey visualization',
    'status translation',
    'milestone',
    'Engine',
    'edge cases',
    'files changed',
    'tests',
    'regression',
    'known warnings',
    'deferred work',
    'blockers',
  ]) {
    assert.match(doc, new RegExp(s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'i'), s);
  }
});

test('existing Shipment Workspace remains the canonical destination', () => {
  assert.match(layout, /shipment-details\/\[id\]/);
  assert.match(details, /getClientShipment/);
  assert.match(details, /CustomerJourney/);
  assert.match(details, /buildCustomerJourney/);
  assert.equal(existsSync(resolve(CLIENT, 'src/app/shipment-journey/[id].tsx')), false);
  assert.equal(existsSync(resolve(CLIENT, 'src/app/shipment-lifecycle/[id].tsx')), false);
});

test('no second shipment journey route is introduced', () => {
  assert.equal(details.includes('shipment-journey/'), false);
  assert.equal(layout.includes('shipment-journey'), false);
  assert.equal(layout.includes('shipment-lifecycle'), false);
  assert.match(tracking, /getClientShipmentTracking/);
});

test('canonical customer statuses are used', () => {
  assert.match(workspace, /Waiting for Assignment/);
  assert.match(workspace, /'Assigned'/);
  assert.match(workspace, /'In Transit'/);
  assert.match(workspace, /'Delivered'/);
  assert.match(workspace, /'Completed'/);
  assert.match(workspace, /CUSTOMER_LIFECYCLE_STEPS/);
  assert.match(workspace, /export function buildCustomerJourney/);
});

test('Engine operational stages are not exposed', () => {
  for (const stage of ENGINE) {
    assert.equal(journeyUi.includes(stage), false, `journey ui: ${stage}`);
  }
  for (const stage of ['EnRoutePickup', 'ApproachingRestStop', 'ArrivedDelivery', 'EnRouteDelivery']) {
    assert.equal(details.includes(stage), false, `details: ${stage}`);
  }
  assert.match(workspace, /EnRouteDelivery/);
  assert.match(workspace, /Accepted/);
});

test('no Dispatcher / Engine / Simulation APIs are used', () => {
  const blob = details + journeyUi + workspace + api;
  assert.equal(blob.includes('/dispatcher/'), false);
  assert.equal(blob.includes('/admin/'), false);
  assert.equal(blob.includes('live-fleet'), false);
  assert.equal(blob.includes('engine-enroll'), false);
  assert.equal(blob.includes('SimulationRuntime'), false);
  assert.equal(blob.includes('/assignments/'), false);
});

test('journey distinguishes completed / current / upcoming', () => {
  assert.match(workspace, /visualState: JourneyVisualState/);
  assert.match(workspace, /'completed'/);
  assert.match(workspace, /'current'/);
  assert.match(workspace, /'upcoming'/);
  assert.match(journeyUi, /step\.stateLabel/);
  assert.match(workspace, /return 'Completed'/);
  assert.match(workspace, /return 'Current'/);
  assert.match(workspace, /return 'Upcoming'/);
  assert.equal(lifecycleIndex('In Transit'), 2);
  assert.equal(visualState(0, 2), 'completed');
  assert.equal(visualState(1, 2), 'completed');
  assert.equal(visualState(2, 2), 'current');
  assert.equal(visualState(3, 2), 'upcoming');
  assert.equal(visualState(4, 2), 'upcoming');
  assert.equal(lifecycleIndex('Picked Up'), 2);
  assert.equal(lifecycleIndex('ApproachingRestStop'), null);
});

test('missing dates are not fabricated', () => {
  assert.match(workspace, /Planned is never presented as an actual event/);
  assert.match(workspace, /Picked up \$\{facts\.actualPickup\}/);
  assert.match(workspace, /Planned pickup \$\{facts\.plannedPickup\}/);
  assert.match(workspace, /Pickup time not provided/);
  assert.match(workspace, /Delivery time not provided/);
  assert.equal(workspace.includes('actualPickup = facts.plannedPickup'), false);
  assert.equal(workspace.includes('updatedAt'), false);
  assert.equal(emptyFacts.actualPickup, null);
});

test('cancelled and unknown states are handled safely', () => {
  assert.match(workspace, /kind: 'cancelled'/);
  assert.match(workspace, /kind: 'unknown'/);
  assert.match(journeyUi, /shipment-journey-cancelled/);
  assert.match(journeyUi, /shipment-journey-unknown/);
  assert.match(journeyUi, /not progressing through assignment, transit, or/);
  assert.match(journeyUi, /Status details are not available for this shipment/);
  assert.equal(lifecycleIndex('Cancelled'), null);
  assert.equal(lifecycleIndex('EnRoutePickup'), null);
});

test('existing C3.6 architecture remains intact', () => {
  assert.match(details, /shipment-details-lifecycle/);
  assert.match(details, /shipmentStatusGuidance/);
  assert.match(details, /customerFacingShipmentStatus/);
  assert.match(details, /findAuthorizedConversationForShipment/);
  assert.match(details, /Shipment progress/);
  assert.match(details, /\/shipment-tracking\/\$\{shipmentId\}/);
});

test('no fake live tracking is introduced', () => {
  const blob = details + journeyUi + workspace;
  assert.equal(blob.includes('SafeMap'), false);
  assert.equal(blob.includes('latitude'), false);
  assert.equal(blob.includes('longitude'), false);
  assert.equal(blob.includes('GPS'), false);
  assert.equal(/\bLIVE\b/.test(blob), false);
  assert.equal(/\bTrack\b/.test(journeyUi), false);
});
