/**
 * C2.9-C2.3 Static Verification — Dispatcher Request Intake
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

test('C2.3 documentation exists', () => {
  const doc = read('docs/C2.9C2_3_DISPATCHER_REQUEST_INTAKE.md');
  assert.ok(doc);
  assert.match(doc, /C2\.9-C2\.3/i);
  assert.match(doc, /Request Intake/i);
});

test('Requests navigation exists in DispatcherLayout', () => {
  const layout = read('dashboard-web/src/layouts/DispatcherLayout.tsx');
  assert.match(layout, /label: 'Requests'/);
  assert.match(layout, /href: '\/dispatcher\/requests'/);
  assert.match(layout, /Shipments Center/);
  assert.match(layout, /Active Tracking/);
});

test('/dispatcher/requests route exists', () => {
  const app = read('dashboard-web/src/app/App.tsx');
  assert.match(app, /path="requests"/);
  assert.match(app, /path="requests\/:id"/);
  assert.match(app, /RequestsPage/);
  assert.match(app, /RequestDetailsPage/);
});

test('existing request API integration is used', () => {
  const service = read('dashboard-web/src/features/dispatcher/requests/services/shipmentRequests.service.ts');
  assert.match(service, /['`]\/shipment-requests['`]/);
  assert.match(service, /getRequests/);
  assert.match(service, /getRequestById/);
});

test('request list exists with status display', () => {
  assert.ok(existsSync(resolve(ROOT, 'dashboard-web/src/features/dispatcher/requests/pages/RequestsPage.tsx')));
  assert.ok(existsSync(resolve(ROOT, 'dashboard-web/src/features/dispatcher/requests/components/RequestsTable.tsx')));
  const badge = read('dashboard-web/src/features/dispatcher/requests/components/RequestStatusBadge.tsx');
  assert.match(badge, /StatusBadge|status/);
  const page = read('dashboard-web/src/features/dispatcher/requests/pages/RequestsPage.tsx');
  assert.match(page, /RequestStatusBadge|statusFilter|Request status/);
});

test('request detail navigation exists', () => {
  const page = read('dashboard-web/src/features/dispatcher/requests/pages/RequestsPage.tsx');
  assert.match(page, /\/dispatcher\/requests\//);
  assert.ok(existsSync(resolve(ROOT, 'dashboard-web/src/features/dispatcher/requests/pages/RequestDetailsPage.tsx')));
});

test('intake list remains free of decision mutations', () => {
  const service = read('dashboard-web/src/features/dispatcher/requests/services/shipmentRequests.service.ts');
  const list = read('dashboard-web/src/features/dispatcher/requests/pages/RequestsPage.tsx');
  assert.match(service, /getRequests/);
  assert.match(service, /getRequestById/);
  assert.equal(/\/approve|\/reject|\/convert|\.post\(/i.test(list), false);
});

test('existing Dispatcher navigation remains intact', () => {
  const layout = read('dashboard-web/src/layouts/DispatcherLayout.tsx');
  assert.match(layout, /Dashboard/);
  assert.match(layout, /Shipments Center/);
  assert.match(layout, /Active Tracking/);
  assert.match(layout, /Fleet Management/);
  assert.match(layout, /Driver Operations/);
  assert.match(layout, /Alerts Center/);
  assert.match(layout, /Communication Center/);
  assert.equal(existsSync(resolve(ROOT, 'dashboard-web/src/features/dispatcher/shipments/pages/ShipmentsPage.tsx')), true);
});
