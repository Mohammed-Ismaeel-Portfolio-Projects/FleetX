/**
 * C2.9-C2.5 Static Verification — Request Approval & Rejection
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

test('C2.5 documentation exists', () => {
  const doc = read('docs/C2.9C2_5_REQUEST_DECISION.md');
  assert.ok(doc);
  assert.match(doc, /C2\.9-C2\.5/i);
  assert.match(doc, /Approve|Reject/i);
});

test('Approve action exists and is wired to existing API', () => {
  const panel = read('dashboard-web/src/features/dispatcher/requests/components/RequestDecisionPanel.tsx');
  const service = read('dashboard-web/src/features/dispatcher/requests/services/shipmentRequests.service.ts');
  assert.match(panel, /Approve/);
  assert.match(service, /\/approve/);
  assert.match(service, /approveRequest/);
});

test('Reject action exists and is wired to existing API', () => {
  const panel = read('dashboard-web/src/features/dispatcher/requests/components/RequestDecisionPanel.tsx');
  const service = read('dashboard-web/src/features/dispatcher/requests/services/shipmentRequests.service.ts');
  assert.match(panel, /Reject/);
  assert.match(service, /\/reject/);
  assert.match(service, /rejectRequest/);
});

test('decision endpoints remain; conversion is not part of decision panel', () => {
  const service = read('dashboard-web/src/features/dispatcher/requests/services/shipmentRequests.service.ts');
  const panel = read('dashboard-web/src/features/dispatcher/requests/components/RequestDecisionPanel.tsx');
  assert.match(service, /\/approve/);
  assert.match(service, /\/reject/);
  assert.equal(/convertRequest/i.test(panel), false);
  assert.match(panel, /Request changes · deferred|deferred/);
});

test('decision gated to under_review backend semantics', () => {
  const mapper = read('dashboard-web/src/features/dispatcher/requests/utils/requestMapper.ts');
  assert.match(mapper, /canApproveOrRejectRequest/);
  assert.match(mapper, /status === 'under_review'/);
});

test('existing C2.3 intake list remains intact', () => {
  assert.ok(existsSync(resolve(ROOT, 'dashboard-web/src/features/dispatcher/requests/pages/RequestsPage.tsx')));
  const layout = read('dashboard-web/src/layouts/DispatcherLayout.tsx');
  assert.match(layout, /\/dispatcher\/requests/);
});

test('existing C2.4 review sections remain intact', () => {
  const page = read('dashboard-web/src/features/dispatcher/requests/pages/RequestDetailsPage.tsx');
  assert.match(page, /Request identity/);
  assert.match(page, /Customer/);
  assert.match(page, /RequestStatusHistoryPanel/);
  assert.match(page, /RequestDecisionPanel/);
});
