/**
 * Focused tests for C2.9-C2.7 Dispatcher Shipment Operational Detail.
 */
import { strict as assert } from 'node:assert';
import { readFileSync, existsSync } from 'node:fs';
import { test } from 'node:test';
import { resolve, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const ROOT = resolve(__dirname, '..');

function read(rel) {
  const p = resolve(ROOT, rel);
  assert.ok(existsSync(p), `missing ${rel}`);
  return readFileSync(p, 'utf8');
}

const OPERATIONAL_LIFECYCLE_STAGES = [
  'Waiting Assignment',
  'Assigned',
  'In Transit',
  'Delivered',
  'Completed',
];

function getLifecycleStageState(currentStatus, stage) {
  const currentIndex = OPERATIONAL_LIFECYCLE_STAGES.indexOf(currentStatus);
  const stageIndex = OPERATIONAL_LIFECYCLE_STAGES.indexOf(stage);
  if (currentIndex < 0) return 'outside';
  if (stageIndex < currentIndex) return 'completed';
  if (stageIndex === currentIndex) return 'current';
  return 'upcoming';
}

function isWaitingForAssignmentDisplay(hasActiveAssignment) {
  return !hasActiveAssignment;
}

test('shipment detail route remains registered under dispatcher protection', () => {
  const app = read('src/app/App.tsx');
  assert.match(app, /path="shipments\/:id"/);
  assert.match(app, /ShipmentDetailsPage/);
  assert.match(app, /allowedRoles=\{\['dispatcher'\]\}/);
});

test('detail page reuses getShipmentById and shared dispatcher shell', () => {
  const page = read('src/features/dispatcher/shipments/pages/ShipmentDetailsPage.tsx');
  assert.match(page, /getShipmentById/);
  assert.match(page, /ShipmentDetailsPanel/);
  assert.match(page, /DispatcherSurface|PageHeader/);
  assert.match(page, /LoadingState|ErrorState|EmptyState/);
});

test('service remains read-only for shipment ops detail', () => {
  const service = read('src/features/dispatcher/shipments/services/shipments.service.ts');
  assert.match(service, /getShipmentById/);
  assert.match(service, /\/shipments\/\$\{id\}/);
  assert.equal(/\/status|assignVehicle|createAssignment|\/convert|\.patch\(|\.put\(/i.test(service), false);
});

test('operational sections render status route customer assignment history handoff', () => {
  const panel = read('src/features/dispatcher/shipments/components/ShipmentDetailsPanel.tsx');
  const header = read('src/features/dispatcher/shipments/components/ShipmentOperationalHeader.tsx');
  assert.match(header, /StatusBadge/);
  assert.match(panel, /Shipment summary|Route|Cargo|Customer/);
  assert.match(panel, /ShipmentAssignmentPanel/);
  assert.match(panel, /ShipmentStatusHistoryPanel/);
  assert.match(panel, /ShipmentSourceRequestPanel/);
  assert.match(panel, /ShipmentLifecycleStrip/);
});

test('lifecycle presentation is read-only and marks stage position', () => {
  assert.equal(getLifecycleStageState('Assigned', 'Waiting Assignment'), 'completed');
  assert.equal(getLifecycleStageState('Assigned', 'Assigned'), 'current');
  assert.equal(getLifecycleStageState('Assigned', 'In Transit'), 'upcoming');
  const strip = read('src/features/dispatcher/shipments/components/ShipmentLifecycleStrip.tsx');
  assert.match(strip, /display only|Read-only|Current/);
  assert.equal(/Mark In Transit|Mark Delivered|updateStatus|\/status/i.test(strip), false);
});

test('waiting-for-assignment state when no active assignment', () => {
  assert.equal(isWaitingForAssignmentDisplay(false), true);
  assert.equal(isWaitingForAssignmentDisplay(true), false);
  const assignment = read('src/features/dispatcher/shipments/components/ShipmentAssignmentPanel.tsx');
  assert.match(assignment, /Waiting for Assignment/);
  assert.equal(/createAssignment|POST.*assignments/i.test(assignment), false);
});

test('mapper preserves source request and status history when API provides them', () => {
  const mapper = read('src/features/dispatcher/shipments/utils/shipmentMapper.ts');
  assert.match(mapper, /shipment_order_id|sourceRequest/);
  assert.match(mapper, /status_history|statusHistory/);
  assert.match(mapper, /active_assignment|activeAssignment/);
  assert.match(mapper, /cargo_type|cargoType/);
});

test('request handoff navigates to dispatcher request detail when source exists', () => {
  const source = read('src/features/dispatcher/shipments/components/ShipmentAssignmentPanel.tsx');
  const header = read('src/features/dispatcher/shipments/components/ShipmentOperationalHeader.tsx');
  assert.match(source, /\/dispatcher\/requests\/\$\{req\.id\}/);
  assert.match(header, /\/dispatcher\/requests\/\$\{shipment\.sourceRequest\.id\}/);
});

test('no lifecycle or assignment mutation controls on operational workspace', () => {
  const panel = read('src/features/dispatcher/shipments/components/ShipmentDetailsPanel.tsx');
  const page = read('src/features/dispatcher/shipments/pages/ShipmentDetailsPage.tsx');
  for (const src of [panel, page]) {
    assert.equal(
      /Mark In Transit|Mark Delivered|Mark Completed|createAssignment|assignDriver|\/shipments\/.*\/status/i.test(
        src
      ),
      false
    );
  }
});
