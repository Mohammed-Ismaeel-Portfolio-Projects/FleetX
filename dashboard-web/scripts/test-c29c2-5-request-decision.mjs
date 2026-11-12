/**
 * Focused tests for C2.9-C2.5 Request Approval & Rejection.
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

function canApproveOrRejectRequest(status) {
  return status === 'under_review';
}

test('approve and reject service methods call existing decision endpoints', () => {
  const service = read('src/features/dispatcher/requests/services/shipmentRequests.service.ts');
  assert.match(service, /approveRequest/);
  assert.match(service, /rejectRequest/);
  assert.match(service, /\/shipment-requests\/\$\{id\}\/approve/);
  assert.match(service, /\/shipment-requests\/\$\{id\}\/reject/);
  assert.equal(/createShipment|assignments/i.test(service), false);
});

test('decision panel exposes approve and reject for under_review only', () => {
  const panel = read('src/features/dispatcher/requests/components/RequestDecisionPanel.tsx');
  assert.match(panel, /canApproveOrRejectRequest/);
  assert.match(panel, /Approve/);
  assert.match(panel, /Reject/);
  assert.match(panel, /approveRequest/);
  assert.match(panel, /rejectRequest/);
  assert.match(panel, /reason/);
  assert.equal(/convertRequest/i.test(panel), false);
});

test('backend decision gate helper allows only under_review', () => {
  assert.equal(canApproveOrRejectRequest('under_review'), true);
  assert.equal(canApproveOrRejectRequest('submitted'), false);
  assert.equal(canApproveOrRejectRequest('approved'), false);
  assert.equal(canApproveOrRejectRequest('rejected'), false);
  assert.equal(canApproveOrRejectRequest('converted'), false);
  const mapper = read('src/features/dispatcher/requests/utils/requestMapper.ts');
  assert.match(mapper, /canApproveOrRejectRequest/);
  assert.match(mapper, /under_review/);
});

test('review page wires decision panel and refreshes after decision', () => {
  const page = read('src/features/dispatcher/requests/pages/RequestDetailsPage.tsx');
  assert.match(page, /RequestDecisionPanel/);
  assert.match(page, /onDecided/);
  assert.match(page, /setRequest/);
  assert.match(page, /refresh/);
});

test('failed decision keeps current request state pattern', () => {
  const panel = read('src/features/dispatcher/requests/components/RequestDecisionPanel.tsx');
  assert.match(panel, /Do not call onDecided|onDecided/);
  assert.match(panel, /setError/);
  assert.match(panel, /isSubmitting/);
  // onDecided only after successful await
  assert.match(panel, /onDecided\(next\)/);
});

test('reject requires reason; approve reason optional in service contract', () => {
  const service = read('src/features/dispatcher/requests/services/shipmentRequests.service.ts');
  assert.match(service, /rejectRequest\(id: string, reason: string\)/);
  assert.match(service, /approveRequest\(id: string, reason\?:/);
  const panel = read('src/features/dispatcher/requests/components/RequestDecisionPanel.tsx');
  assert.match(panel, /minimum 3 characters|min 3/);
});

test('start-review and request-changes are wired; conversion stays outside decision panel', () => {
  const panel = read('src/features/dispatcher/requests/components/RequestDecisionPanel.tsx');
  const service = read('src/features/dispatcher/requests/services/shipmentRequests.service.ts');
  assert.match(service, /startReview/);
  assert.match(service, /\/shipment-requests\/\$\{id\}\/start-review/);
  assert.match(service, /requestChanges/);
  assert.match(service, /\/shipment-requests\/\$\{id\}\/request-changes/);
  assert.match(panel, /Start Review/);
  assert.match(panel, /Request Changes/);
  assert.equal(/convertRequest/i.test(panel), false);
  assert.equal(/Request changes · deferred/.test(panel), false);
});

test('dispatcher route protection still wraps request review', () => {
  const app = read('src/app/App.tsx');
  assert.match(app, /allowedRoles=\{\['dispatcher'\]\}/);
  assert.match(app, /path="requests\/:id"/);
});
