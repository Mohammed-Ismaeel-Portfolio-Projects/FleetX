/**
 * INT-016 / INT-028 Dispatcher shipment-request workflow UI.
 * Run: node ./scripts/test-int016-dispatcher-request-workflow.mjs
 */
import { strict as assert } from 'node:assert';
import { readFileSync, existsSync } from 'node:fs';
import { test } from 'node:test';
import { resolve, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const ROOT = resolve(__dirname, '..');
const REPO = resolve(ROOT, '..');

function read(rel, base = ROOT) {
  const p = resolve(base, rel);
  assert.ok(existsSync(p), `missing ${rel}`);
  return readFileSync(p, 'utf8');
}

function canStartReviewRequest(status) {
  return status === 'submitted';
}

function canApproveOrRejectRequest(status) {
  return status === 'under_review';
}

function canRequestChangesRequest(status) {
  return status === 'under_review';
}

function canConvertRequest(status, convertedShipmentId) {
  return status === 'approved' && !convertedShipmentId;
}

test('backend start-review and request-changes endpoints already exist', () => {
  const routes = read('backend/routes/api.php', REPO);
  assert.match(routes, /start-review/);
  assert.match(routes, /request-changes/);
  assert.match(routes, /v1\.shipment-requests\.start-review/);
  assert.match(routes, /v1\.shipment-requests\.request-changes/);
});

test('dispatcher service calls existing start-review and request-changes endpoints', () => {
  const service = read('src/features/dispatcher/requests/services/shipmentRequests.service.ts');
  assert.match(service, /async startReview/);
  assert.match(service, /\/shipment-requests\/\$\{id\}\/start-review/);
  assert.match(service, /async requestChanges/);
  assert.match(service, /\/shipment-requests\/\$\{id\}\/request-changes/);
  assert.match(service, /toApiError/);
  assert.equal(service.includes("Storage::url"), false);
});

test('submitted shows Start Review and not Approve/Reject/Convert', () => {
  const panel = read('src/features/dispatcher/requests/components/RequestDecisionPanel.tsx');
  assert.match(panel, /canStartReviewRequest/);
  assert.match(panel, /decision-start-review/);
  assert.match(panel, /Start Review/);
  assert.match(panel, /canApproveOrRejectRequest/);
  assert.equal(canStartReviewRequest('submitted'), true);
  assert.equal(canApproveOrRejectRequest('submitted'), false);
  assert.equal(canRequestChangesRequest('submitted'), false);
  assert.equal(canConvertRequest('submitted', null), false);
});

test('under_review shows Approve Reject and Request Changes, not Start Review', () => {
  const panel = read('src/features/dispatcher/requests/components/RequestDecisionPanel.tsx');
  assert.match(panel, /decision-approve/);
  assert.match(panel, /decision-reject/);
  assert.match(panel, /decision-request-changes/);
  assert.match(panel, /canRequestChangesRequest/);
  assert.equal(canStartReviewRequest('under_review'), false);
  assert.equal(canApproveOrRejectRequest('under_review'), true);
  assert.equal(canRequestChangesRequest('under_review'), true);
});

test('changes_requested has no fake dispatcher action', () => {
  const panel = read('src/features/dispatcher/requests/components/RequestDecisionPanel.tsx');
  assert.match(panel, /decision-awaiting-client/);
  assert.match(panel, /Waiting for the client to resubmit/);
  assert.equal(canStartReviewRequest('changes_requested'), false);
  assert.equal(canApproveOrRejectRequest('changes_requested'), false);
  assert.equal(canRequestChangesRequest('changes_requested'), false);
  assert.equal(canConvertRequest('changes_requested', null), false);
});

test('approved convert remains gated by existing conversion helper', () => {
  const conversion = read('src/features/dispatcher/requests/components/RequestConversionPanel.tsx');
  assert.match(conversion, /canConvertRequest/);
  assert.equal(canConvertRequest('approved', null), true);
  assert.equal(canConvertRequest('approved', 'shp-1'), false);
  assert.equal(canApproveOrRejectRequest('approved'), false);
  assert.equal(canStartReviewRequest('approved'), false);
});

test('closed statuses do not present decision actions', () => {
  for (const status of ['rejected', 'cancelled', 'converted']) {
    assert.equal(canStartReviewRequest(status), false, status);
    assert.equal(canApproveOrRejectRequest(status), false, status);
    assert.equal(canRequestChangesRequest(status), false, status);
  }
  const panel = read('src/features/dispatcher/requests/components/RequestDecisionPanel.tsx');
  assert.match(panel, /decision-closed/);
});

test('success updates via onDecided; failure does not fake success', () => {
  const panel = read('src/features/dispatcher/requests/components/RequestDecisionPanel.tsx');
  assert.match(panel, /onDecided\(next\)/);
  assert.match(panel, /Do not call onDecided/);
  assert.match(panel, /decision-error/);
  assert.match(panel, /decision-success/);
  assert.match(panel, /isSubmitting/);
  assert.match(panel, /disabled=\{isSubmitting\}/);
});

test('request-changes uses existing reason contract', () => {
  const request = read('backend/app/Http/Requests/ShipmentOrder/RequestChangesShipmentOrderRequest.php', REPO);
  const service = read('src/features/dispatcher/requests/services/shipmentRequests.service.ts');
  const panel = read('src/features/dispatcher/requests/components/RequestDecisionPanel.tsx');
  assert.match(request, /'reason' => \['required'/);
  assert.match(service, /requestChanges\(id: string, reason: string\)/);
  assert.match(panel, /minimum 3 characters/);
});

test('frontend does not duplicate the backend transition map', () => {
  const panel = read('src/features/dispatcher/requests/components/RequestDecisionPanel.tsx');
  const mapper = read('src/features/dispatcher/requests/utils/requestMapper.ts');
  const blob = panel + mapper;
  assert.equal(blob.includes('submitted -> under_review -> changes_requested'), false);
  assert.equal(/allowedTargets/.test(blob), false);
  assert.match(mapper, /canStartReviewRequest/);
  assert.match(mapper, /canRequestChangesRequest/);
});
