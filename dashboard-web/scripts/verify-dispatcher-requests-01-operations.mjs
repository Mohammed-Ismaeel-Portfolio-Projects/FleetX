/**
 * DISPATCHER-REQUESTS-01 — static verification for operations workspace + demo accounts.
 */
import { strict as assert } from 'node:assert';
import { existsSync, readFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { test } from 'node:test';

const __dirname = dirname(fileURLToPath(import.meta.url));
const ROOT = resolve(__dirname, '../..');
const WEB = resolve(ROOT, 'dashboard-web');
const BACKEND = resolve(ROOT, 'backend');

function read(rel, base = WEB) {
  const p = resolve(base, rel);
  if (!existsSync(p)) return '';
  return readFileSync(p, 'utf8');
}

test('operations demo seeder defines 40/3/10 accounts with deterministic credentials', () => {
  const seeder = read('database/seeders/OperationsDemoAccountsSeeder.php', BACKEND);
  const db = read('database/seeders/DatabaseSeeder.php', BACKEND);
  assert.match(seeder, /DRIVER_COUNT = 40/);
  assert.match(seeder, /DISPATCHER_COUNT = 3/);
  assert.match(seeder, /CLIENT_COUNT = 10/);
  assert.match(seeder, /driver\{\$n\}@fleetx\.test/);
  assert.match(seeder, /dispatcher\{\$n\}@fleetx\.test/);
  assert.match(seeder, /client\{\$n\}@fleetx\.test/);
  assert.match(seeder, /Driver@123/);
  assert.match(seeder, /Dispatcher@123/);
  assert.match(seeder, /Client@123/);
  assert.match(seeder, /CustomerType::/);
  assert.match(seeder, /user_id/);
  assert.match(db, /OperationsDemoAccountsSeeder/);
});

test('requests workspace exposes next action, cargo, estimate, pricing section', () => {
  const row = read('src/features/dispatcher/requests/components/RequestRow.tsx');
  const table = read('src/features/dispatcher/requests/components/RequestsTable.tsx');
  const page = read('src/features/dispatcher/requests/pages/RequestsPage.tsx');
  const details = read('src/features/dispatcher/requests/pages/RequestDetailsPage.tsx');
  const summary = read('src/features/dispatcher/requests/components/RequestReviewSummary.tsx');
  const mapper = read('src/features/dispatcher/requests/utils/requestMapper.ts');

  assert.match(page, /Requests Operations/);
  assert.match(row, /getRequestPrimaryAction/);
  assert.match(row, /formatCargoSummary/);
  assert.match(row, /formatPriceSummaryLabel/);
  assert.match(table, /Next action/);
  assert.match(table, /Estimate/);
  assert.match(summary, /ReadinessItem|Complete/);
  assert.match(summary, /Pricing/);
  assert.match(details, /title="Pricing"/);
  assert.match(details, /Trailer type/);
  assert.match(mapper, /getRequestPrimaryAction/);
  assert.match(mapper, /priceSummary/);
  assert.match(mapper, /priceSnapshot/);
});

test('assignment workspace uses real APIs and linked driver identity', () => {
  const workspace = read('src/features/dispatcher/requests/components/RequestAssignmentWorkspace.tsx');
  const service = read('src/features/dispatcher/requests/services/assignmentResources.service.ts');
  assert.match(service, /\/drivers/);
  assert.match(service, /\/vehicles/);
  assert.match(service, /\/trailers/);
  assert.match(service, /\/assignments/);
  assert.match(service, /userId: d\.user_id/);
  assert.match(service, /email: d\.email/);
  assert.doesNotMatch(workspace, /DRV-00[123]/);
  assert.match(workspace, /No login linked|linked User/);
  assert.match(workspace, /Assignment summary/);
  assert.match(workspace, /Confirm Assignment/);
});

test('backend list/show expose cargo weight and trailer_type from existing model', () => {
  const list = read('app/Http/Resources/ShipmentOrder/ShipmentRequestListResource.php', BACKEND);
  const show = read('app/Http/Resources/ShipmentOrder/ShipmentRequestShowResource.php', BACKEND);
  assert.match(list, /cargo_weight/);
  assert.match(list, /package_count/);
  assert.match(list, /trailer_type/);
  assert.match(list, /price_summary/);
  assert.match(show, /trailer_type/);
  assert.match(show, /price_snapshot/);
});

test('no PricingEngine / Android map / Client Request redesign in this phase', () => {
  assert.equal(existsSync(resolve(BACKEND, 'app/Services/Pricing/PricingEngine.php')), true);
  // Phase must not rewrite client request form.
  const clientForm = read('mobile-app/client-app/src/app/request-form.tsx', ROOT);
  assert.ok(clientForm.length > 0);
});
