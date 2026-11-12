/**
 * ADMIN-06 — Admin Monthly Invoice governance UI.
 * Run: node ./scripts/test-admin-06-monthly-invoices.mjs
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

function readInvoiceModule() {
  return [
    'src/features/admin/invoices/pages/MonthlyInvoicesPage.tsx',
    'src/features/admin/invoices/components/MonthlyInvoicesTable.tsx',
    'src/features/admin/invoices/components/InvoiceDetailsDrawer.tsx',
    'src/features/admin/invoices/services/monthlyInvoices.service.ts',
  ].map((rel) => read(rel)).join('\n');
}

test('admin monthly invoices route and sidebar exist', () => {
  const app = read('src/app/App.tsx');
  assert.match(app, /path="monthly-invoices"/);
  assert.match(app, /MonthlyInvoicesPage/);

  const sidebar = read('src/components/dashboard/layout/Sidebar.tsx');
  assert.match(sidebar, /Monthly Invoices/);
  assert.match(sidebar, /\/admin\/monthly-invoices/);
});

test('invoice service maps to ops backend endpoints only', () => {
  const service = read('src/features/admin/invoices/services/monthlyInvoices.service.ts');
  assert.match(service, /'\/monthly-invoices'/);
  assert.match(service, /`\/monthly-invoices\/\$\{id\}`/);
  assert.match(service, /\/finalize`/);
  assert.match(service, /\/void`/);
  assert.match(service, /\/pdf`/);
  assert.equal(/updateInvoice|deleteInvoice|createInvoice|addLineItem|removeLineItem|updateLineItem/.test(service), false);
});

test('frontend displays persisted amounts and does not recalculate totals', () => {
  const moduleSource = readInvoiceModule();
  assert.match(moduleSource, /invoice\.subtotal/);
  assert.match(moduleSource, /invoice\.total/);
  assert.match(moduleSource, /item\.line_total/);
  assert.match(moduleSource, /item\.unit_price/);
  assert.equal(/PricingEngine|ShipmentFinalPricingService|ShipmentOrderPricingService|PricingPolicyResolver/.test(moduleSource), false);
  assert.equal(/reduce\(|sum\(|total \+=|subtotal \+=/.test(moduleSource), false);
});

test('no invoice CRUD editing controls exist', () => {
  const moduleSource = readInvoiceModule();
  assert.equal(/Edit Invoice|Create Invoice|Delete Invoice|Add Line Item|Remove Line Item|changeStatus|statusOptions/.test(moduleSource), false);
  assert.equal(/updateInvoice|deleteInvoice|createInvoice/.test(moduleSource), false);
});

test('lifecycle action visibility follows invoice status rules', () => {
  const drawer = read('src/features/admin/invoices/components/InvoiceDetailsDrawer.tsx');
  assert.match(drawer, /invoice\?\.status === 'draft'/);
  assert.match(drawer, /invoice\?\.status === 'draft' \|\| invoice\?\.status === 'finalized'/);
  assert.match(drawer, /Finalize Invoice/);
  assert.match(drawer, /Void Invoice/);
  assert.match(drawer, /Preview PDF/);
  assert.match(drawer, /VOID/);
});

test('finalize and void call backend lifecycle endpoints from page', () => {
  const page = read('src/features/admin/invoices/pages/MonthlyInvoicesPage.tsx');
  assert.match(page, /finalizeMonthlyInvoice/);
  assert.match(page, /voidMonthlyInvoice/);
  assert.match(page, /issued_at will be assigned by the server/);
  assert.match(page, /openMonthlyInvoicePdf/);
});

test('issued_at is rendered from server response fields', () => {
  const drawer = read('src/features/admin/invoices/components/InvoiceDetailsDrawer.tsx');
  assert.match(drawer, /invoice\.issued_at/);
  assert.equal(/issued_at:\s*new Date|issued_at\s*=/.test(drawer), false);
});

test('dispatcher and client invoice surfaces remain unchanged', () => {
  const app = read('src/app/App.tsx');
  assert.equal(app.includes('SmartDispatchPage'), false);
  assert.equal(/client\/monthly-invoices/.test(app), false);

  assert.equal(
    existsSync(resolve(ROOT, 'src/features/dispatcher/dispatch-ai/pages/SmartDispatchPage.tsx')),
    false,
  );
});

test('prior admin restrictions remain intact', () => {
  const sidebar = read('src/components/dashboard/layout/Sidebar.tsx');
  assert.equal(sidebar.includes('/admin/assignments'), false);
  assert.equal(sidebar.includes('AI Center'), false);

  const overview = read('src/components/dashboard/overview/OverviewPage.tsx');
  assert.equal(/AiWidget|FleetMapWidget|Pending Assignments/.test(overview), false);
});

test('backend monthly invoice routes remain present', () => {
  const routes = read('backend/routes/api.php', REPO);
  assert.match(routes, /MonthlyInvoiceController/);
  assert.match(routes, /monthly-invoices/);
  assert.match(routes, /finalize/);
  assert.match(routes, /void/);
});
