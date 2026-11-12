/**
 * ADMIN-03 — Admin Shipments read-only surface.
 * Run: node ./scripts/test-admin-03-read-only-shipments.mjs
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

function readAdminShipmentsModule() {
  const files = [
    'src/features/admin/shipments/pages/ShipmentsPage.tsx',
    'src/features/admin/shipments/components/ShipmentsTable.tsx',
    'src/features/admin/shipments/components/ShipmentDetailsDrawer.tsx',
    'src/features/admin/shipments/services/shipments.service.ts',
    'src/features/admin/shipments/hooks/useShipments.ts',
  ];
  return files.map((rel) => read(rel)).join('\n');
}

test('admin shipments route remains registered', () => {
  const app = read('src/app/App.tsx');
  assert.match(app, /path="shipments"/);
  assert.match(app, /AdminShipmentsPage/);
  assert.match(app, /features\/admin\/shipments\/pages\/ShipmentsPage/);
});

test('admin sidebar still links to read-only shipments route', () => {
  const sidebar = read('src/components/dashboard/layout/Sidebar.tsx');
  assert.match(sidebar, /\/admin\/shipments/);
});

test('admin shipment page removes create/edit/delete/restore/status mutation UI', () => {
  const page = read('src/features/admin/shipments/pages/ShipmentsPage.tsx');
  assert.equal(page.includes('Create Shipment'), false);
  assert.equal(/ShipmentModal/.test(page), false);
  assert.equal(/DeleteDialog/.test(page), false);
  assert.equal(/createShipment/.test(page), false);
  assert.equal(/updateShipment/.test(page), false);
  assert.equal(/deleteShipment/.test(page), false);
  assert.equal(/restoreShipment/.test(page), false);
  assert.equal(/changeShipmentStatus/.test(page), false);
  assert.match(page, /ShipmentDetailsDrawer/);
  assert.match(page, /Read-only shipment oversight/);
});

test('admin shipments table keeps read-only status and view action only', () => {
  const table = read('src/features/admin/shipments/components/ShipmentsTable.tsx');
  assert.equal(/onChangeStatus/.test(table), false);
  assert.equal(/onEdit/.test(table), false);
  assert.equal(/onDelete/.test(table), false);
  assert.equal(/onRestore/.test(table), false);
  assert.equal(/ShipmentActionsDropdown/.test(table), false);
  assert.equal(/changeShipmentStatus/.test(table), false);
  assert.match(table, /onView/);
  assert.match(table, /shipment\.status\?\.name/);
  assert.equal(/statusOptions/.test(table), false);
});

test('admin shipment service exposes read APIs only', () => {
  const service = read('src/features/admin/shipments/services/shipments.service.ts');
  assert.match(service, /getShipments/);
  assert.match(service, /getShipment/);
  assert.match(service, /getShipmentQueue/);
  assert.equal(/createShipment/.test(service), false);
  assert.equal(/updateShipment/.test(service), false);
  assert.equal(/deleteShipment/.test(service), false);
  assert.equal(/restoreShipment/.test(service), false);
  assert.equal(/changeShipmentStatus/.test(service), false);
});

test('admin-exclusive mutation components are removed', () => {
  const removed = [
    'src/features/admin/shipments/components/ShipmentModal.tsx',
    'src/features/admin/shipments/components/ShipmentForm.tsx',
    'src/features/admin/shipments/components/DeleteDialog.tsx',
    'src/features/admin/shipments/components/ShipmentActionsDropdown.tsx',
  ];
  for (const rel of removed) {
    assert.equal(existsSync(resolve(ROOT, rel)), false, `should be deleted: ${rel}`);
  }
});

test('dispatcher shipment workflow remains intact', () => {
  const dispatcherService = read('src/features/dispatcher/shipments/services/shipments.service.ts');
  assert.match(dispatcherService, /getShipments/);
  assert.match(dispatcherService, /getShipment/);

  const dispatcherPage = read('src/features/dispatcher/shipments/pages/ShipmentsPage.tsx');
  assert.match(dispatcherPage, /ShipmentsPage/);

  const app = read('src/app/App.tsx');
  assert.match(app, /features\/dispatcher\/shipments\/pages\/ShipmentsPage/);
  assert.match(app, /features\/dispatcher\/shipments\/pages\/ShipmentDetailsPage/);
});

test('backend shipment routes remain unchanged', () => {
  const routes = read('backend/routes/api.php', REPO);
  assert.match(routes, /ShipmentController/);
  assert.match(routes, /\/shipments/);
  assert.equal(
    existsSync(resolve(REPO, 'backend/app/Http/Controllers/ShipmentController.php')),
    true,
  );
});

test('admin shipment module has no mutation service wiring', () => {
  const moduleSource = readAdminShipmentsModule();
  assert.equal(/createShipment\(/.test(moduleSource), false);
  assert.equal(/updateShipment\(/.test(moduleSource), false);
  assert.equal(/deleteShipment\(/.test(moduleSource), false);
  assert.equal(/restoreShipment\(/.test(moduleSource), false);
  assert.equal(/changeShipmentStatus\(/.test(moduleSource), false);
});
