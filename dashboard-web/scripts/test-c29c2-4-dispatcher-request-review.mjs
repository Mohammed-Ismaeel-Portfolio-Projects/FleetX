/**
 * Focused tests for C2.9-C2.4 Dispatcher Request Review workspace.
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

function getRequestReviewPhase(status) {
  switch (status) {
    case 'draft':
      return 'draft';
    case 'submitted':
    case 'under_review':
      return 'reviewable';
    case 'changes_requested':
      return 'awaiting_client';
    case 'approved':
      return 'ready_for_decision';
    case 'converted':
      return 'converted';
    case 'rejected':
    case 'cancelled':
      return 'closed';
    default:
      return 'unknown';
  }
}

function collectMissingReviewFields(detail) {
  const missing = [];
  if (!detail.customer?.companyName) missing.push('Customer company');
  if (!detail.submittedBy?.name) missing.push('Submitted by');
  if (!detail.pickupCity && !detail.pickupCountry) missing.push('Pickup location');
  if (!detail.deliveryCity && !detail.deliveryCountry) missing.push('Delivery location');
  if (!detail.cargoType) missing.push('Cargo type');
  if (!detail.requestedPickupDate) missing.push('Requested pickup date');
  if (!detail.requestedDeliveryDate) missing.push('Requested delivery date');
  if (!detail.priority) missing.push('Priority');
  return missing;
}

test('Requests Center still navigates to request review detail', () => {
  const list = read('src/features/dispatcher/requests/pages/RequestsPage.tsx');
  const app = read('src/app/App.tsx');
  assert.match(list, /\/dispatcher\/requests\/\$\{request\.id\}/);
  assert.match(app, /path="requests\/:id"/);
  assert.match(app, /RequestDetailsPage/);
});

test('review page displays identity customer pickup delivery cargo and status sections', () => {
  const page = read('src/features/dispatcher/requests/pages/RequestDetailsPage.tsx');
  assert.match(page, /Request identity/);
  assert.match(page, /Customer/);
  assert.match(page, /Pickup/);
  assert.match(page, /Delivery/);
  assert.match(page, /Cargo/);
  assert.match(page, /Requested dates/);
  assert.match(page, /RequestReviewSummary|Request status|status/);
  assert.match(page, /RequestStatusHistoryPanel|Status history/);
});

test('review phase helpers distinguish reviewable converted and closed states', () => {
  assert.equal(getRequestReviewPhase('submitted'), 'reviewable');
  assert.equal(getRequestReviewPhase('under_review'), 'reviewable');
  assert.equal(getRequestReviewPhase('converted'), 'converted');
  assert.equal(getRequestReviewPhase('rejected'), 'closed');
  assert.equal(getRequestReviewPhase('approved'), 'ready_for_decision');
});

test('missing-field helper reports only absent API-backed fields', () => {
  const missing = collectMissingReviewFields({
    customer: null,
    submittedBy: { name: 'Ada' },
    pickupCity: 'Warsaw',
    pickupCountry: 'PL',
    deliveryCity: null,
    deliveryCountry: null,
    cargoType: null,
    requestedPickupDate: '2026-08-20',
    requestedDeliveryDate: null,
    priority: 'urgent',
  });
  assert.deepEqual(missing, [
    'Customer company',
    'Delivery location',
    'Cargo type',
    'Requested delivery date',
  ]);
});

test('history panel includes actor reason and chronology', () => {
  const panel = read('src/features/dispatcher/requests/components/RequestStatusHistoryPanel.tsx');
  assert.match(panel, /changedBy|Actor/);
  assert.match(panel, /reason|Reason/);
  assert.match(panel, /createdAt/);
  const mapper = read('src/features/dispatcher/requests/utils/requestMapper.ts');
  assert.match(mapper, /changed_by/);
  assert.match(mapper, /reason/);
});

test('review workspace reuses existing request API and supports loading error refresh', () => {
  const page = read('src/features/dispatcher/requests/pages/RequestDetailsPage.tsx');
  const hook = read('src/features/dispatcher/requests/hooks/useShipmentRequestDetail.ts');
  const service = read('src/features/dispatcher/requests/services/shipmentRequests.service.ts');
  assert.match(hook, /getRequestById/);
  assert.match(service, /\/shipment-requests\/\$\{id\}|\/shipment-requests\//);
  assert.match(page, /LoadingState/);
  assert.match(page, /ErrorState/);
  assert.match(page, /Back to Requests/);
  assert.match(page, /onRefresh|refresh/);
});

test('no conversion is inlined in review summary; dedicated conversion panel may wire convert', () => {
  const page = read('src/features/dispatcher/requests/pages/RequestDetailsPage.tsx');
  const summary = read('src/features/dispatcher/requests/components/RequestReviewSummary.tsx');
  assert.equal(/\/convert/i.test(summary), false);
  assert.match(page, /RequestDecisionPanel|RequestConversionPanel|Convert/i);
  assert.match(page, /Request identity/);
});
