/**
 * C2.9-A2 Static Verification — Shipment Order Domain & Lifecycle Contract
 *
 * Verifies the design document exists and contains required architectural
 * decisions. Does NOT verify implementation (C2.9-A2 is design-only).
 */
import { strict as assert } from 'node:assert';
import { readFileSync, existsSync } from 'node:fs';
import { test } from 'node:test';
import { resolve, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const ROOT = resolve(__dirname, '..', '..', '..');
const DOC = resolve(ROOT, 'docs', 'C2.9A2_SHIPMENT_ORDER_DOMAIN_LIFECYCLE_CONTRACT.md');

function readDoc() {
  if (!existsSync(DOC)) return null;
  return readFileSync(DOC, 'utf8');
}

test('C2.9-A2 contract document exists', () => {
  const doc = readDoc();
  assert.ok(doc, 'C2.9A2_SHIPMENT_ORDER_DOMAIN_LIFECYCLE_CONTRACT.md must exist');
});

test('document is DESIGN ONLY / not implementation', () => {
  const doc = readDoc();
  assert.match(doc, /DESIGN ONLY/i);
  assert.match(doc, /NOT IMPLEMENTED/i);
  assert.match(doc, /NOT part of C2\.9-A2/i);
});

test('canonical terminology is explicitly defined', () => {
  const doc = readDoc();
  assert.match(doc, /Canonical Terminology/i);
  assert.match(doc, /ShipmentOrder/);
  assert.match(doc, /shipment_orders/);
  assert.match(doc, /Shipment Request/i);
});

test('request vs shipment separation is explicit', () => {
  const doc = readDoc();
  assert.match(doc, /Domain Boundary/i);
  assert.match(doc, /COMMERCIAL.*CUSTOMER REQUEST/i);
  assert.match(doc, /OPERATIONAL.*EXECUTION/i);
  assert.match(doc, /separately from operational|MUST NOT.*reuse Shipment operational/i);
  assert.match(doc, /Shipment Boundary/i);
});

test('lifecycle states are documented', () => {
  const doc = readDoc();
  assert.match(doc, /Lifecycle State Machine/i);
  assert.match(doc, /draft/i);
  assert.match(doc, /submitted/i);
  assert.match(doc, /under_review/i);
  assert.match(doc, /changes_requested/i);
  assert.match(doc, /approved/i);
  assert.match(doc, /rejected/i);
  assert.match(doc, /cancelled/i);
  assert.match(doc, /converted/i);
  assert.match(doc, /shipment_order/);
});

test('transition matrix exists', () => {
  const doc = readDoc();
  assert.match(doc, /Transition Matrix/i);
  assert.match(doc, /SUBMIT/i);
  assert.match(doc, /REQUEST_CHANGES|request changes/i);
  assert.match(doc, /RESUBMIT|resubmit/i);
  assert.match(doc, /APPROVE/i);
  assert.match(doc, /REJECT/i);
  assert.match(doc, /CONVERT/i);
});

test('ownership is server-derived', () => {
  const doc = readDoc();
  assert.match(doc, /Ownership Model/i);
  assert.match(doc, /SERVER ONLY|server-derived|server only/i);
  assert.match(doc, /customer_company_id/);
  assert.match(doc, /FORBIDDEN/i);
});

test('client cannot mutate lifecycle state', () => {
  const doc = readDoc();
  assert.match(doc, /client.*approve|Client.*FORBIDDEN|FORBIDDEN.*client/i);
  assert.match(doc, /status_id.*FORBIDDEN|FORBIDDEN.*status/i);
  assert.match(doc, /dedicated actions per transition|NOT.*generic.*status PATCH/i);
});

test('conversion rules exist', () => {
  const doc = readDoc();
  assert.match(doc, /Conversion Architecture/i);
  assert.match(doc, /ConvertShipmentOrderToShipmentAction|conversion/i);
  assert.match(doc, /idempotency|Idempotency/i);
  assert.match(doc, /transaction/i);
  assert.match(doc, /approved/i);
});

test('traceability is defined', () => {
  const doc = readDoc();
  assert.match(doc, /Traceability/i);
  assert.match(doc, /converted_shipment_id/);
  assert.match(doc, /shipment_order_id/);
  assert.match(doc, /bidirectional/i);
});

test('audit and history strategy is defined', () => {
  const doc = readDoc();
  assert.match(doc, /Audit.*History/i);
  assert.match(doc, /shipment_order_status_history/);
  assert.match(doc, /HasAudit/);
  assert.match(doc, /AuditLog/);
});

test('dormant Order decision is explicit', () => {
  const doc = readDoc();
  assert.match(doc, /Dormant Order/i);
  assert.match(doc, /RETIRE.*IGNORE|RETIRE \/ IGNORE/i);
  assert.match(doc, /do not reuse|NOT reused/i);
});

test('migration and backward compatibility addressed', () => {
  const doc = readDoc();
  assert.match(doc, /Migration.*Backward Compatibility|Backward Compatibility/i);
  assert.match(doc, /legacy|nullable/i);
  assert.match(doc, /POST \/api\/v1\/shipments/);
});

test('required contract sections present', () => {
  const doc = readDoc();
  assert.match(doc, /Executive Summary/);
  assert.match(doc, /Entity Definition/);
  assert.match(doc, /Security Model/);
  assert.match(doc, /Notification Architecture/);
  assert.match(doc, /Cancellation Rules/);
  assert.match(doc, /Versioning Strategy/);
  assert.match(doc, /Implementation Prerequisites/);
  assert.match(doc, /Recommended Next Phase/);
  assert.match(doc, /C2\.9-A3/);
});

test('shipment operational statuses not mixed with order states', () => {
  const doc = readDoc();
  assert.match(doc, /MUST NOT.*reuse Shipment operational/i);
  assert.match(doc, /in_transit/);
  assert.match(doc, /type=shipment/);
});
