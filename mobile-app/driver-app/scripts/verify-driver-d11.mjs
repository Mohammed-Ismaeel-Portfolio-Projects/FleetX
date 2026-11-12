/**
 * DRIVER-D11 — Assignment History & Trip Summary Foundation.
 * Run: npm run test:driver-d11
 */

import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const root = path.join(__dirname, '..');
const repoRoot = path.join(root, '..', '..');
const docs = path.join(repoRoot, 'docs', 'driver');
const backend = path.join(repoRoot, 'backend');

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
    console.error(e?.message || e);
  }
}

function read(rel, base = root) {
  return fs.readFileSync(path.join(base, rel), 'utf8');
}

function exists(rel, base = root) {
  return fs.existsSync(path.join(base, rel));
}

console.log('=== DRIVER-D11 Assignment History ===\n');

const requiredDocs = [
  'D11_DISCOVERY.md',
  'D11_SECURITY_REVIEW.md',
  'D11_IMPLEMENTATION_REPORT.md',
];

for (const doc of requiredDocs) {
  check(`Doc exists: ${doc}`, () => {
    assert.ok(fs.existsSync(path.join(docs, doc)), `Missing ${doc}`);
  });
}

check('Discovery rejects a new history table', () => {
  const discovery = fs.readFileSync(path.join(docs, 'D11_DISCOVERY.md'), 'utf8');
  assert.match(discovery, /NOT REQUIRED/);
  assert.match(discovery, /No new history table/i);
  assert.match(discovery, /is_active = false/);
  assert.match(discovery, /Completed/);
  assert.match(discovery, /Cancelled/);
});

const routes = read('routes/api.php', backend);
const historyIdx = routes.indexOf("Route::get('/assignments/history'");
const showIdx = routes.indexOf("Route::get('/assignments/{assignment}', [DriverWorkController::class, 'show']");
const summaryIdx = routes.indexOf("Route::get('/assignments/{assignment}/summary'");

check('History route registered before {assignment}', () => {
  assert.ok(historyIdx >= 0, 'history route missing');
  assert.ok(showIdx >= 0, 'show route missing');
  assert.ok(historyIdx < showIdx, 'history must be registered before {assignment}');
});

check('Summary route is Driver-scoped and named', () => {
  assert.ok(summaryIdx > 0, 'summary route missing');
  assert.match(routes, /DriverHistoryController::class, 'index'/);
  assert.match(routes, /DriverHistoryController::class, 'summary'/);
  assert.match(routes, /v1\.driver\.assignments\.history/);
  assert.match(routes, /v1\.driver\.assignments\.summary/);
});

check('Dedicated history/summary actions exist', () => {
  assert.ok(
    exists('app/Actions/Driver/QueryDriverAssignmentHistoryAction.php', backend)
  );
  assert.ok(
    exists('app/Actions/Driver/GetDriverAssignmentSummaryAction.php', backend)
  );
  const query = read('app/Actions/Driver/QueryDriverAssignmentHistoryAction.php', backend);
  const summary = read('app/Actions/Driver/GetDriverAssignmentSummaryAction.php', backend);
  assert.match(query, /forDriver\(\$driver->id\)/);
  assert.match(query, /where\('is_active', false\)/);
  assert.match(query, /paginate\(/);
  assert.doesNotMatch(query, /\$request->(?:query|input|get)\(['"]driver_id/);
  assert.match(summary, /forDriver\(\$driver->id\)/);
  assert.match(summary, /ASSIGNMENT_NOT_HISTORICAL/);
  assert.doesNotMatch(summary, /\$request->(?:query|input|get)\(['"]driver_id/);
});

check('Whitelist DTOs exist and omit Ops/POD internals', () => {
  const item = read(
    'app/Http/Resources/Driver/DriverAssignmentHistoryItemResource.php',
    backend
  );
  const summary = read(
    'app/Http/Resources/Driver/DriverAssignmentSummaryResource.php',
    backend
  );
  assert.match(item, /DriverHistoryOutcome/);
  assert.match(item, /'assignment_code'/);
  assert.doesNotMatch(item, /'notes'/);
  assert.doesNotMatch(item, /'assigned_by'/);
  assert.doesNotMatch(item, /'engine_enrollment/);
  assert.match(summary, /'proof'/);
  assert.match(summary, /'exists'/);
  assert.match(summary, /'exceptions'/);
  assert.doesNotMatch(summary, /'image_url'\s*=>/);
  assert.doesNotMatch(summary, /'signature'\s*=>/);
  assert.doesNotMatch(summary, /'notes'/);
  assert.doesNotMatch(summary, /'assigned_by'/);
});

check('FormRequests prohibit client-supplied driver identity', () => {
  const histReq = read(
    'app/Http/Requests/Driver/QueryDriverAssignmentHistoryRequest.php',
    backend
  );
  const sumReq = read(
    'app/Http/Requests/Driver/ShowDriverAssignmentSummaryRequest.php',
    backend
  );
  assert.match(histReq, /'driver_id'\s*=>\s*\[['"]prohibited['"]\]/);
  assert.match(histReq, /'page'/);
  assert.match(histReq, /'per_page'/);
  assert.match(sumReq, /'driver_id'\s*=>\s*\[['"]prohibited['"]\]/);
});

check('No new assignment-history persistence table', () => {
  assert.ok(!exists('app/Models/AssignmentHistory.php', backend));
  const migrations = fs.readdirSync(path.join(backend, 'database', 'migrations'));
  const historyMigration = migrations.filter((f) =>
    /assignment_histories|driver_assignment_history/i.test(f)
  );
  assert.equal(historyMigration.length, 0, `unexpected migration: ${historyMigration.join(', ')}`);
});

check('Backend D11 tests exist with IDOR/auth markers', () => {
  const hist = read('tests/Feature/Driver/DriverAssignmentHistoryTest.php', backend);
  const auth = read(
    'tests/Feature/Driver/DriverAssignmentHistoryAuthorizationTest.php',
    backend
  );
  const sum = read('tests/Feature/Driver/DriverAssignmentSummaryTest.php', backend);
  assert.match(hist, /test_own_completed_assignment_appears_in_history/);
  assert.match(hist, /test_active_assignment_is_excluded_from_history/);
  assert.match(hist, /test_active_failed_delivery_is_excluded_from_history/);
  assert.match(hist, /test_pagination_works/);
  assert.match(hist, /test_driver_id_query_is_prohibited/);
  assert.match(hist, /test_soft_deleted_assignment_is_excluded_from_history/);
  assert.match(auth, /test_unauthenticated_denied/);
  assert.match(auth, /test_unlinked_driver_forbidden/);
  assert.match(auth, /test_non_driver_roles_forbidden/);
  assert.match(auth, /test_other_driver_summary_is_not_found/);
  assert.match(sum, /test_own_historical_summary_includes_safe_pod_and_exceptions/);
  assert.match(sum, /assertArrayNotHasKey\('image_url'/);
  assert.match(sum, /test_other_driver_denied_not_found/);
  assert.match(sum, /test_active_assignment_summary_is_conflict/);
});

const api = read('src/features/driverApi/history.ts');
const mapper = read('src/features/driverApi/historyMapper.ts');
const index = read('src/features/driverApi/index.ts');
const listUi = read('src/app/history/index.tsx');
const summaryUi = read('src/app/history/[id].tsx');
const workHome = read('src/app/(tabs)/index.tsx');
const layout = read('src/app/_layout.tsx');
const pkg = JSON.parse(read('package.json'));

check('Driver API client uses Driver history/summary contracts only', () => {
  assert.match(api, /\/driver\/assignments\/history/);
  assert.match(api, /\/driver\/assignments\/\$\{assignmentId\}\/summary/);
  assert.match(api, /listAssignmentHistory/);
  assert.match(api, /getAssignmentSummary/);
  assert.doesNotMatch(api, /params:\s*\{[^}]*driver_id/);
  assert.doesNotMatch(api, /['"`]driver_id['"`]/);
  assert.doesNotMatch(api, /['"`]\/assignments(?:\/|['"`?])/);
  assert.doesNotMatch(api, /\/client\//);
  assert.match(index, /driverHistoryApi/);
});

check('Pagination is wired in client + list UI', () => {
  assert.match(api, /per_page:\s*20/);
  assert.match(api, /params:\s*\{\s*page/);
  assert.match(mapper, /current_page|lastPage/);
  assert.match(listUi, /onEndReached/);
  assert.match(listUi, /lastPage/);
  assert.match(listUi, /listAssignmentHistory/);
});

check('History list UI: loading/empty/error/retry/offline', () => {
  assert.match(listUi, /history-loading/);
  assert.match(listUi, /history-empty/);
  assert.match(listUi, /No completed assignments yet/);
  assert.match(listUi, /history-error|history-retry/);
  assert.match(listUi, /OfflineBanner/);
  assert.match(listUi, /View Summary/);
  assert.match(workHome, /work-assignment-history/);
  assert.match(workHome, /\/history/);
  assert.match(layout, /history\/index/);
  assert.match(layout, /history\/\[id\]/);
});

check('Summary UI is read-only and re-fetches on focus', () => {
  assert.match(summaryUi, /getAssignmentSummary/);
  assert.match(summaryUi, /useFocusEffect/);
  assert.match(summaryUi, /This assignment is no longer available/);
  assert.match(summaryUi, /history-summary-outcome/);
  assert.match(summaryUi, /history-summary-pod/);
  assert.match(summaryUi, /history-summary-exceptions/);
  assert.doesNotMatch(summaryUi, /Start Trip/);
  assert.doesNotMatch(summaryUi, /Continue Trip/);
  assert.doesNotMatch(summaryUi, /Acknowledge Assignment/);
  assert.doesNotMatch(summaryUi, /Scan pickup QR/);
  assert.doesNotMatch(summaryUi, /Capture proof of delivery/);
  assert.doesNotMatch(summaryUi, /Finish trip/);
  assert.doesNotMatch(summaryUi, /Report Issue/);
  assert.doesNotMatch(summaryUi, /completeAssignment/);
  assert.doesNotMatch(summaryUi, /submitPod/);
  assert.doesNotMatch(summaryUi, /failDelivery/);
  assert.doesNotMatch(listUi, /Start Trip/);
  assert.doesNotMatch(listUi, /completeAssignment/);
});

check('Package scripts include D11 chain', () => {
  assert.ok(pkg.scripts['test:driver-d11']);
  assert.ok(pkg.scripts['test:d11-history']);
  assert.match(pkg.scripts['test:d11-history'], /test:d10-final/);
});

console.log('');
console.log(`DRIVER-D11: ${passed} passed, ${failed} failed`);
if (failed > 0) process.exit(1);
console.log('DRIVER-D11 VERDICT: PASS');
