/**
 * C2.9-C2.4 Static Verification — Dispatcher Request Review
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

test('C2.4 documentation exists', () => {
  const doc = read('docs/C2.9C2_4_DISPATCHER_REQUEST_REVIEW.md');
  assert.ok(doc);
  assert.match(doc, /C2\.9-C2\.4/i);
  assert.match(doc, /Request Review/i);
});

test('existing Requests route remains intact', () => {
  const app = read('dashboard-web/src/app/App.tsx');
  const layout = read('dashboard-web/src/layouts/DispatcherLayout.tsx');
  assert.match(app, /path="requests"/);
  assert.match(layout, /href: '\/dispatcher\/requests'/);
  assert.ok(existsSync(resolve(ROOT, 'dashboard-web/src/features/dispatcher/requests/pages/RequestsPage.tsx')));
});

test('request detail review route exists', () => {
  const app = read('dashboard-web/src/app/App.tsx');
  assert.match(app, /path="requests\/:id"/);
  assert.match(app, /RequestDetailsPage/);
  assert.ok(existsSync(resolve(ROOT, 'dashboard-web/src/features/dispatcher/requests/pages/RequestDetailsPage.tsx')));
});

test('review sections are implemented', () => {
  const page = read('dashboard-web/src/features/dispatcher/requests/pages/RequestDetailsPage.tsx');
  assert.match(page, /Request identity/);
  assert.match(page, /Customer/);
  assert.match(page, /Pickup/);
  assert.match(page, /Delivery/);
  assert.match(page, /Cargo/);
  assert.match(page, /Requested dates/);
  assert.match(page, /Operational metadata/);
});

test('existing request API is reused', () => {
  const service = read('dashboard-web/src/features/dispatcher/requests/services/shipmentRequests.service.ts');
  const hook = read('dashboard-web/src/features/dispatcher/requests/hooks/useShipmentRequestDetail.ts');
  assert.match(service, /getRequestById/);
  assert.match(service, /\/shipment-requests/);
  assert.match(hook, /getRequestById/);
});

test('request status is displayed', () => {
  const summary = read('dashboard-web/src/features/dispatcher/requests/components/RequestReviewSummary.tsx');
  assert.match(summary, /RequestStatusBadge/);
  assert.match(summary, /getRequestReviewPhase/);
});

test('history is displayed when available', () => {
  const panel = read('dashboard-web/src/features/dispatcher/requests/components/RequestStatusHistoryPanel.tsx');
  assert.match(panel, /Status history/);
  assert.match(panel, /changedBy|Actor/);
  const mapper = read('dashboard-web/src/features/dispatcher/requests/utils/requestMapper.ts');
  assert.match(mapper, /status_history/);
  assert.match(mapper, /changed_by/);
});

test('conversion is not inlined in review summary; dedicated panel may exist', () => {
  const summary = read('dashboard-web/src/features/dispatcher/requests/components/RequestReviewSummary.tsx');
  const page = read('dashboard-web/src/features/dispatcher/requests/pages/RequestDetailsPage.tsx');
  assert.equal(/\/convert/i.test(summary), false);
  assert.match(page, /Request identity/);
  assert.match(page, /RequestDecisionPanel|RequestConversionPanel/);
});

test('existing Dispatcher navigation remains intact', () => {
  const layout = read('dashboard-web/src/layouts/DispatcherLayout.tsx');
  assert.match(layout, /Requests/);
  assert.match(layout, /Shipments Center/);
  assert.match(layout, /Active Tracking/);
  assert.match(layout, /Fleet Management/);
});
