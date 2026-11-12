/**
 * C2.9-A1 Static Verification — Shipment Order Existing Architecture Audit
 *
 * Verifies the audit assumptions by checking repository evidence:
 * 1. Dormant Order model/migration exist but have no API surface
 * 2. Shipment domain exists with full lifecycle
 * 3. No client create capability exists
 * 4. Status seeder has order statuses
 * 5. Audit document exists
 */
import { strict as assert } from 'node:assert';
import { readFileSync, existsSync } from 'node:fs';
import { test } from 'node:test';
import { resolve, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const ROOT = resolve(__dirname, '..', '..', '..');
const BACKEND = resolve(ROOT, 'backend');
const DOCS = resolve(ROOT, 'docs');

function read(rel) {
  const p = resolve(ROOT, rel);
  if (!existsSync(p)) return null;
  return readFileSync(p, 'utf8');
}

// ── 1. Dormant Order model exists ──
test('Order model exists', () => {
  const src = read('backend/app/Models/Order.php');
  assert.ok(src, 'Order.php must exist');
  assert.match(src, /class Order extends Model/, 'Order model class');
  assert.match(src, /client\(\)/, 'Order has client() relationship');
  assert.match(src, /shipments\(\)/, 'Order has shipments() relationship');
});

test('OrderItem model exists', () => {
  const src = read('backend/app/Models/OrderItem.php');
  assert.ok(src, 'OrderItem.php must exist');
  assert.match(src, /class OrderItem extends Model/);
});

// ── 2. Order has NO API surface ──
test('no OrderController exists', () => {
  const ctrl = read('backend/app/Http/Controllers/OrderController.php');
  assert.equal(ctrl, null, 'OrderController must not exist');
});

test('no Order routes in api.php', () => {
  const routes = read('backend/routes/api.php');
  assert.ok(routes);
  assert.equal(routes.includes('OrderController'), false, 'No OrderController reference in routes');
  assert.equal(/Route::.*orders/.test(routes), false, 'No /orders routes');
});

test('no OrderPolicy exists', () => {
  const pol = read('backend/app/Policies/OrderPolicy.php');
  assert.equal(pol, null, 'OrderPolicy must not exist');
});

test('no Order gates defined', () => {
  const prov = read('backend/app/Providers/AppServiceProvider.php');
  assert.ok(prov);
  assert.equal(prov.includes("'order."), false, 'No order.* gates');
});

// ── 3. Shipment domain is operational ──
test('ShipmentController exists with store method', () => {
  const ctrl = read('backend/app/Http/Controllers/ShipmentController.php');
  assert.ok(ctrl);
  assert.match(ctrl, /function store/);
  assert.match(ctrl, /CreateShipmentRequest/);
  assert.match(ctrl, /CreateShipmentAction/);
});

test('shipment.create gate is admin/dispatcher only', () => {
  const prov = read('backend/app/Providers/AppServiceProvider.php');
  assert.ok(prov);
  assert.match(prov, /shipment\.create/);
  assert.equal(prov.includes("client.shipment.create"), false, 'No client.shipment.create gate');
});

// ── 4. No client create capability ──
test('no client shipment/order create endpoint', () => {
  const routes = read('backend/routes/api.php');
  assert.ok(routes);
  assert.equal(routes.includes('client.shipments.store'), false, 'No v1.client.shipments.store route');
  assert.equal(routes.includes('client.orders'), false, 'No v1.client.orders routes');
  assert.equal(routes.includes("client.shipment-orders"), false, 'No v1.client.shipment-orders routes');
});

// ── 5. Status seeder has order statuses ──
test('order statuses are seeded', () => {
  const seeder = read('backend/database/seeders/StatusesSeeder.php');
  assert.ok(seeder);
  assert.match(seeder, /Pending.*order/s, 'Pending order status');
  assert.match(seeder, /Approved.*order/s, 'Approved order status');
  assert.match(seeder, /Rejected.*order/s, 'Rejected order status');
});

// ── 6. shipments table no longer has order_id ──
test('shipments table dropped order_id FK', () => {
  const mig = read('backend/database/migrations/2026_07_02_220001_update_shipments_table_for_fleetx_architecture.php');
  assert.ok(mig);
  assert.equal(mig.includes('order_id'), true, 'Migration references order_id in rollback');
  const shipmentModel = read('backend/app/Models/Shipment.php');
  assert.equal(shipmentModel.includes('order_id'), false, 'Shipment model has no order_id');
});

// ── 7. HasAudit and AuditLog exist ──
test('HasAudit trait exists', () => {
  const trait = read('backend/app/Traits/HasAudit.php');
  assert.ok(trait);
  assert.match(trait, /created_by/);
  assert.match(trait, /updated_by/);
});

test('AuditLog model exists', () => {
  const model = read('backend/app/Models/AuditLog.php');
  assert.ok(model);
  assert.match(model, /entity_type/);
  assert.match(model, /entity_id/);
});

// ── 8. Audit document exists ──
test('C2.9-A1 audit document exists with required sections', () => {
  const doc = read('docs/C2.9A1_SHIPMENT_ORDER_EXISTING_ARCHITECTURE_AUDIT.md');
  assert.ok(doc, 'Audit document must exist');
  assert.match(doc, /Executive Summary/);
  assert.match(doc, /Existing Shipment Creation Flow/);
  assert.match(doc, /Existing Shipment Data Model/);
  assert.match(doc, /Existing Status Architecture/);
  assert.match(doc, /Client.*Tenant Architecture/);
  assert.match(doc, /Dispatcher.*Ops Authorization/);
  assert.match(doc, /Existing Client Shipment Capabilities/);
  assert.match(doc, /Existing Notifications.*Messaging/);
  assert.match(doc, /Existing Order.*Booking.*Request Concepts/);
  assert.match(doc, /Existing Auditability/);
  assert.match(doc, /Reusable Components/);
  assert.match(doc, /Architectural Gaps/);
  assert.match(doc, /Risks.*Architectural Constraints/);
  assert.match(doc, /Evidence Index/);
  assert.match(doc, /NOT IMPLEMENTED/);
  assert.match(doc, /C2\.9-A2/);
});

test('audit document is AUDIT ONLY', () => {
  const doc = read('docs/C2.9A1_SHIPMENT_ORDER_EXISTING_ARCHITECTURE_AUDIT.md');
  assert.match(doc, /AUDIT ONLY/);
  assert.match(doc, /NOT created.*modified.*implemented/s);
});
