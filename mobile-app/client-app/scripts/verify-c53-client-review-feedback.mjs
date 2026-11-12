/**
 * C5.3 — Client review_feedback projection + UI (static + behavioral).
 * Run: node ./scripts/verify-c53-client-review-feedback.mjs
 */

import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const clientRoot = path.join(__dirname, '..');
const repoRoot = path.join(clientRoot, '..', '..');

function readClient(rel) {
  return fs.readFileSync(path.join(clientRoot, rel), 'utf8');
}

function readRepo(rel) {
  return fs.readFileSync(path.join(repoRoot, rel), 'utf8');
}

let passed = 0;
let failed = 0;

function check(name, fn) {
  try {
    fn();
    passed += 1;
    console.log(`PASS  ${name}`);
  } catch (e) {
    failed += 1;
    console.error(`FAIL  ${name}`);
    console.error(e);
  }
}

/** Mirrors mapClientReviewFeedback in requestsApi.ts — keep in sync. */
function mapClientReviewFeedback(raw) {
  if (raw == null || typeof raw !== 'object' || Array.isArray(raw)) {
    return null;
  }
  if (typeof raw.message !== 'string') {
    return null;
  }
  const message = raw.message.trim();
  return message ? { message } : null;
}

const types = readClient('src/features/requests/types.ts');
const api = readClient('src/features/requests/requestsApi.ts');
const statusHelpers = readClient('src/features/requests/requestStatus.ts');
const detailsUi = readClient('src/app/request-details/[id].tsx');
const formUi = readClient('src/app/request-form.tsx');
const showResource = readRepo('backend/app/Http/Resources/Client/ClientShipmentRequestShowResource.php');
const listResource = readRepo('backend/app/Http/Resources/Client/ClientShipmentRequestListResource.php');
const helper = readRepo('backend/app/Support/Client/ClientShipmentRequestReviewFeedback.php');
const getAction = readRepo('backend/app/Actions/ShipmentOrder/GetClientShipmentRequestAction.php');
const transition = readRepo('backend/app/Actions/ShipmentOrder/TransitionShipmentOrderStatusAction.php');
const requestChanges = readRepo('backend/app/Actions/ShipmentOrder/RequestChangesShipmentOrderAction.php');

check('backend show resource exposes allowlisted review_feedback only', () => {
  assert.match(showResource, /review_feedback/);
  assert.match(showResource, /ClientShipmentRequestReviewFeedback/);
  assert.doesNotMatch(showResource, /dispatcher_notes/);
  assert.doesNotMatch(showResource, /status_history/);
  assert.doesNotMatch(showResource, /changed_by/);
});

check('list resource does not expose review_feedback or history', () => {
  assert.doesNotMatch(listResource, /review_feedback/);
  assert.doesNotMatch(listResource, /dispatcher_notes/);
  assert.doesNotMatch(listResource, /status_history/);
});

check('helper reads status-history reason and never dispatcher_notes', () => {
  assert.match(helper, /shipment_order_status_history|ShipmentOrderStatusHistory/);
  assert.match(helper, /\$history->reason/);
  assert.match(helper, /CHANGES_REQUESTED/);
  assert.match(helper, /REJECTED/);
  assert.match(helper, /Never reads dispatcher_notes/);
  assert.doesNotMatch(helper, /\$order->dispatcher_notes|\$order\['dispatcher_notes'\]/);
  assert.match(getAction, /statusHistory|CHANGES_REQUESTED/);
});

check('lifecycle actions remain untouched by C5.3 projection helper', () => {
  assert.match(transition, /ShipmentOrderStatusHistory::query\(\)->create/);
  assert.match(requestChanges, /TransitionShipmentOrderStatusAction/);
  assert.ok(!helper.includes('TransitionShipmentOrderStatusAction'));
});

check('Client types include reviewFeedback allowlist', () => {
  assert.match(types, /export interface ClientReviewFeedback/);
  assert.match(types, /message: string/);
  assert.match(types, /reviewFeedback: ClientReviewFeedback \| null/);
  assert.doesNotMatch(types, /dispatcherNotes|statusHistories|changedBy|reviewerId/);
});

check('mapper consumes review_feedback with message only', () => {
  assert.match(api, /export function mapClientReviewFeedback/);
  assert.match(api, /reviewFeedback: mapClientReviewFeedback/);
  assert.match(api, /review_feedback\?:/);
  assert.match(api, /raw\.message/);
  const mapperStart = api.indexOf('export function mapClientReviewFeedback');
  const mapperEnd = api.indexOf('function mapDetails', mapperStart);
  const mapperBody = api.slice(mapperStart, mapperEnd);
  assert.doesNotMatch(mapperBody, /dispatcher_notes|status_histories|changed_by/);
  assert.match(api, /delete \(body as \{ dispatcher_notes\?: unknown \}\)\.dispatcher_notes/);
});

check('request details shows changes_requested and rejected feedback', () => {
  assert.match(detailsUi, /request-details-review-feedback/);
  assert.match(detailsUi, /FleetX requested changes/);
  assert.match(detailsUi, /request-details-rejection-feedback/);
  assert.match(detailsUi, /FleetX could not approve this request/);
  assert.match(detailsUi, /reviewFeedback\.message/);
  assert.match(detailsUi, /isChangesRequestedStatus/);
  assert.match(detailsUi, /isRejectedStatus/);
  assert.doesNotMatch(detailsUi, /dispatcher_notes|Dispatcher|status_histories/);
});

check('request form shows changes_requested feedback banner', () => {
  assert.match(formUi, /request-form-review-feedback/);
  assert.match(formUi, /FleetX requested changes/);
  assert.match(formUi, /reviewFeedback/);
  assert.match(formUi, /isChangesRequestedStatus/);
  assert.doesNotMatch(formUi, /dispatcher_notes|status_histories/);
});

check('rejected helper exists without inventing resubmit for rejected', () => {
  assert.match(statusHelpers, /isRejectedStatus/);
  assert.match(statusHelpers, /canResubmitRequest/);
  assert.match(statusHelpers, /CHANGES_REQUESTED/);
  assert.ok(statusHelpers.includes("return isChangesRequestedStatus(name);"));
});

check('behavioral: valid feedback maps message', () => {
  const mapped = mapClientReviewFeedback({
    message: 'Please provide cargo dimensions.',
  });
  assert.deepEqual(mapped, { message: 'Please provide cargo dimensions.' });
});

check('behavioral: empty/whitespace/null feedback become null', () => {
  assert.equal(mapClientReviewFeedback(null), null);
  assert.equal(mapClientReviewFeedback({ message: '' }), null);
  assert.equal(mapClientReviewFeedback({ message: '   ' }), null);
  assert.equal(mapClientReviewFeedback({ message: 12 }), null);
  assert.equal(mapClientReviewFeedback({ reason: 'nope' }), null);
  assert.equal(mapClientReviewFeedback([{ message: 'x' }]), null);
});

check('behavioral: trims message without inventing content', () => {
  assert.deepEqual(mapClientReviewFeedback({ message: '  Fix pickup phone  ' }), {
    message: 'Fix pickup phone',
  });
});

console.log(`\nC5.3 review feedback: ${passed} passed, ${failed} failed`);
process.exit(failed > 0 ? 1 : 0);
