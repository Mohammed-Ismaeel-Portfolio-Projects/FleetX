/**
 * C2.9-C2.7 Static Verification — Dispatcher Shipment Operational Detail
 */
import { strict as assert } from 'node:assert';
import { readFileSync, existsSync } from 'node:fs';
import { test } from 'node:test';
import { resolve, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const ROOT = resolve(__dirname, '..', '..', '..');

function read(rel) {
  const p = resolve(ROOT, rel);
  if (!existsSync(p)) return null;
  return readFileSync(p, 'utf8');
}

test('C2.7 documentation exists', () => {
  const doc = read('docs/C2.9C2_7_SHIPMENT_OPERATIONAL_DETAIL.md');
  assert.ok(doc);
  assert.match(doc, /C2\.9-C2\.7/i);
  assert.match(doc, /Operational/i);
});

test('existing shipment detail route remains functional', () => {
  const app = read('dashboard-web/src/app/App.tsx');
  assert.match(app, /path="shipments\/:id"/);
  assert.match(app, /ShipmentDetailsPage/);
  assert.ok(
    existsSync(
      resolve(ROOT, 'dashboard-web/src/features/dispatcher/shipments/pages/ShipmentDetailsPage.tsx')
    )
  );
});

test('existing shipment show API is reused', () => {
  const service = read('dashboard-web/src/features/dispatcher/shipments/services/shipments.service.ts');
  assert.match(service, /getShipmentById/);
  assert.match(service, /\/shipments\/\$\{id\}/);
  assert.equal(/createAssignment|\/convert|\.patch\(/i.test(service), false);
});

test('operational sections and prominent status exist', () => {
  const panel = read(
    'dashboard-web/src/features/dispatcher/shipments/components/ShipmentDetailsPanel.tsx'
  );
  const header = read(
    'dashboard-web/src/features/dispatcher/shipments/components/ShipmentOperationalHeader.tsx'
  );
  const assignment = read(
    'dashboard-web/src/features/dispatcher/shipments/components/ShipmentAssignmentPanel.tsx'
  );
  assert.match(header, /StatusBadge/);
  assert.match(panel, /Route|Cargo|Customer/);
  assert.match(panel, /ShipmentLifecycleStrip|ShipmentAssignmentPanel|ShipmentStatusHistoryPanel/);
  assert.match(assignment, /Status history|Waiting for Assignment/);
});

test('waiting-for-assignment and request handoff are represented', () => {
  const assignment = read(
    'dashboard-web/src/features/dispatcher/shipments/components/ShipmentAssignmentPanel.tsx'
  );
  assert.match(assignment, /Waiting for Assignment/);
  assert.match(assignment, /Open request|\/dispatcher\/requests\//);
});

test('lifecycle is visible but not mutable', () => {
  const strip = read(
    'dashboard-web/src/features/dispatcher/shipments/components/ShipmentLifecycleStrip.tsx'
  );
  assert.match(strip, /Waiting Assignment/);
  assert.match(strip, /Completed/);
  assert.equal(/Mark In Transit|Mark Delivered|updateStatus/i.test(strip), false);
});

test('no duplicate shipment detail architecture', () => {
  const page = read('dashboard-web/src/features/dispatcher/shipments/pages/ShipmentDetailsPage.tsx');
  assert.match(page, /ShipmentDetailsPanel/);
  assert.equal(/OperationalShipmentPage|ShipmentOpsPage/i.test(page || ''), false);
});

test('C2.6 conversion handoff target remains intact', () => {
  const conversion = read(
    'dashboard-web/src/features/dispatcher/requests/components/RequestConversionPanel.tsx'
  );
  assert.match(conversion, /\/dispatcher\/shipments\//);
});

test('backend show expose path remains GetShipmentAction + ShipmentResource', () => {
  const action = read('backend/app/Actions/Shipment/GetShipmentAction.php');
  const resource = read('backend/app/Http/Resources/ShipmentResource.php');
  assert.match(action, /statusHistory/);
  assert.match(action, /shipmentOrder/);
  assert.match(resource, /shipment_order_id/);
  assert.match(resource, /status_history/);
});
