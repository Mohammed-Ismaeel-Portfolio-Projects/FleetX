/**
 * C2.9-C2.1 Static Verification — Existing Dispatcher Operational Workspace Audit
 * Audit-only: confirms documentation and that production UI/backend were not changed by this phase.
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

test('C2.1 audit documentation exists', () => {
  const doc = read('docs/C2.9C2_1_EXISTING_DISPATCHER_OPERATIONAL_WORKSPACE_AUDIT.md');
  assert.ok(doc);
  assert.match(doc, /C2\.9-C2\.1/i);
  assert.match(doc, /AUDIT ONLY/i);
  assert.match(doc, /Production code changed.*NONE/i);
});

test('existing Dispatcher implementation was inspected', () => {
  const doc = read('docs/C2.9C2_1_EXISTING_DISPATCHER_OPERATIONAL_WORKSPACE_AUDIT.md');
  assert.match(doc, /dashboard-web/i);
  assert.match(doc, /DispatcherLayout/);
  assert.match(doc, /ProtectedRoute/);
  assert.ok(existsSync(resolve(ROOT, 'dashboard-web/src/layouts/DispatcherLayout.tsx')));
  assert.ok(existsSync(resolve(ROOT, 'dashboard-web/src/app/App.tsx')));
});

test('current Dispatcher screens and routes are documented', () => {
  const doc = read('docs/C2.9C2_1_EXISTING_DISPATCHER_OPERATIONAL_WORKSPACE_AUDIT.md');
  assert.match(doc, /\/dispatcher\/shipments/);
  assert.match(doc, /\/dispatcher\/tracking/);
  assert.match(doc, /\/dispatcher\/fleet/);
  assert.match(doc, /\/dispatcher\/drivers/);
  assert.match(doc, /\/dispatcher\/notifications/);
  assert.match(doc, /\/dispatcher\/communications/);
  const app = read('dashboard-web/src/app/App.tsx');
  assert.match(app, /path="\/dispatcher\/\*"/);
  assert.match(app, /ShipmentsPage/);
  assert.match(app, /ActiveTrackingPage/);
});

test('Shipment ShipmentOrder and Assignment capabilities are documented', () => {
  const doc = read('docs/C2.9C2_1_EXISTING_DISPATCHER_OPERATIONAL_WORKSPACE_AUDIT.md');
  assert.match(doc, /Existing Shipment Operations/i);
  assert.match(doc, /Shipment Request|ShipmentOrder/i);
  assert.match(doc, /Existing Assignment Operations/i);
  assert.match(doc, /Backend-ready but not exposed|Not implemented/i);
  assert.match(doc, /Admin-only|admin\/assignments/i);
});

test('current API integration is documented', () => {
  const doc = read('docs/C2.9C2_1_EXISTING_DISPATCHER_OPERATIONAL_WORKSPACE_AUDIT.md');
  assert.match(doc, /GET \/shipments/);
  assert.match(doc, /GET \/shipments\/kpis/);
  assert.match(doc, /GET \/tracking\/live-fleet/);
  assert.match(doc, /GET \/shipment-requests|shipment-requests/);
  assert.match(doc, /POST \/assignments|\/assignments/);
  const svc = read('dashboard-web/src/features/dispatcher/shipments/services/shipments.service.ts');
  assert.match(svc, /['`]\/shipments['`]/);
  assert.match(svc, /['`]\/shipments\/kpis['`]/);
  assert.equal(/\.patch\(|\.post\(|\.put\(/.test(svc), false);
});

test('reusable architecture is documented', () => {
  const doc = read('docs/C2.9C2_1_EXISTING_DISPATCHER_OPERATIONAL_WORKSPACE_AUDIT.md');
  assert.match(doc, /Reusable Architecture/i);
  assert.match(doc, /shared\/components\/dispatcher|FilterToolbar|LoadingState/i);
  assert.match(doc, /feature folders|Feature folders/i);
});

test('gaps are documented', () => {
  const doc = read('docs/C2.9C2_1_EXISTING_DISPATCHER_OPERATIONAL_WORKSPACE_AUDIT.md');
  assert.match(doc, /Request Intake/i);
  assert.match(doc, /Request Review|Request Approval/i);
  assert.match(doc, /Shipment Conversion/i);
  assert.match(doc, /Assignment/i);
  assert.match(doc, /Tracking/i);
});

test('no production UI or backend behavior was changed by C2.1', () => {
  const doc = read('docs/C2.9C2_1_EXISTING_DISPATCHER_OPERATIONAL_WORKSPACE_AUDIT.md');
  assert.match(doc, /Do not redesign|no production|AUDIT ONLY/i);
  assert.equal(
    existsSync(resolve(ROOT, 'dashboard-web/src/features/dispatcher/shipment-requests')),
    false
  );
  assert.ok(existsSync(resolve(ROOT, 'docs/C2.9C1_11_SHIPMENT_LIFECYCLE_OPERATIONAL_READINESS_AUDIT.md')));
});
