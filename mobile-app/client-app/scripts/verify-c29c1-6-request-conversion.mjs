/**
 * C2.9-C2.6 Static Verification — Request → Shipment Conversion
 *
 * Script filename uses the phase deliverable name from the C2.6 brief
 * (`verify-c29c1-6-request-conversion`). Phase identity remains C2.9-C2.6.
 * Does not replace `verify-c29c1-6-assigned-in-transit.mjs` (C1.6 lifecycle).
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

test('C2.6 documentation exists', () => {
  const doc = read('docs/C2.9C2_6_REQUEST_TO_SHIPMENT_CONVERSION.md');
  assert.ok(doc);
  assert.match(doc, /C2\.9-C2\.6/i);
  assert.match(doc, /Convert/i);
});

test('conversion action exists and reuses existing conversion API', () => {
  const panel = read(
    'dashboard-web/src/features/dispatcher/requests/components/RequestConversionPanel.tsx'
  );
  const service = read(
    'dashboard-web/src/features/dispatcher/requests/services/shipmentRequests.service.ts'
  );
  assert.match(panel, /Convert to shipment/);
  assert.match(panel, /convertRequest/);
  assert.match(service, /convertRequest/);
  assert.match(service, /\/shipment-requests\/\$\{id\}\/convert/);
});

test('conversion is gated to convertible approved requests', () => {
  const mapper = read('dashboard-web/src/features/dispatcher/requests/utils/requestMapper.ts');
  const panel = read(
    'dashboard-web/src/features/dispatcher/requests/components/RequestConversionPanel.tsx'
  );
  assert.match(mapper, /canConvertRequest/);
  assert.match(mapper, /status === 'approved'/);
  assert.match(panel, /canConvertRequest/);
  assert.match(panel, /rejected requests cannot be converted/i);
});

test('no duplicate conversion implementation exists in Dispatcher UI', () => {
  const service = read(
    'dashboard-web/src/features/dispatcher/requests/services/shipmentRequests.service.ts'
  );
  assert.equal(/CreateShipmentAction|ShipmentOrderConversionMapper/i.test(service), false);
  assert.match(service, /\/shipment-requests\/\$\{id\}\/convert/);
  const matches = service.match(/\/shipment-requests\/\$\{id\}\/convert/g) || [];
  assert.equal(matches.length, 1);
});

test('no Assignment Tracking or Shipment lifecycle actions introduced', () => {
  const panel = read(
    'dashboard-web/src/features/dispatcher/requests/components/RequestConversionPanel.tsx'
  );
  const service = read(
    'dashboard-web/src/features/dispatcher/requests/services/shipmentRequests.service.ts'
  );
  const page = read('dashboard-web/src/features/dispatcher/requests/pages/RequestDetailsPage.tsx');
  for (const src of [panel, service, page]) {
    assert.ok(src);
    assert.equal(
      /assignVehicle|assignDriver|\/assignments|startTransit|markDelivered|tracking\/init/i.test(src),
      false
    );
  }
});

test('C2.3 intake list remains intact', () => {
  assert.ok(
    existsSync(resolve(ROOT, 'dashboard-web/src/features/dispatcher/requests/pages/RequestsPage.tsx'))
  );
  const layout = read('dashboard-web/src/layouts/DispatcherLayout.tsx');
  assert.match(layout, /\/dispatcher\/requests/);
});

test('C2.4 review sections remain intact', () => {
  const page = read('dashboard-web/src/features/dispatcher/requests/pages/RequestDetailsPage.tsx');
  assert.match(page, /Request identity/);
  assert.match(page, /Customer/);
  assert.match(page, /RequestStatusHistoryPanel/);
});

test('C2.5 approve/reject decision panel remains intact', () => {
  const panel = read(
    'dashboard-web/src/features/dispatcher/requests/components/RequestDecisionPanel.tsx'
  );
  assert.match(panel, /Approve/);
  assert.match(panel, /Reject/);
  assert.match(panel, /approveRequest/);
  assert.match(panel, /rejectRequest/);
});

test('converted handoff navigation to shipment is available', () => {
  const panel = read(
    'dashboard-web/src/features/dispatcher/requests/components/RequestConversionPanel.tsx'
  );
  assert.match(panel, /Open shipment/);
  assert.match(panel, /\/dispatcher\/shipments\//);
});
