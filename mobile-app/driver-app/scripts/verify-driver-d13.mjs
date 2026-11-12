/**
 * DRIVER-D13 — Driver Availability Self-Service.
 * Run: npm run test:driver-d13
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

console.log('=== DRIVER-D13 Availability Self-Service ===\n');

for (const doc of [
  'D13_DISCOVERY.md',
  'D13_SECURITY_REVIEW.md',
  'D13_IMPLEMENTATION_REPORT.md',
]) {
  check(`Doc exists: ${doc}`, () => {
    assert.ok(fs.existsSync(path.join(docs, doc)), `Missing ${doc}`);
  });
}

check('Discovery: reuse status_id Available/Offline; do not use is_active', () => {
  const discovery = fs.readFileSync(path.join(docs, 'D13_DISCOVERY.md'), 'utf8');
  assert.match(discovery, /is_active/);
  assert.match(discovery, /Offline/);
  assert.match(discovery, /No migration/i);
  assert.match(discovery, /DRIVER_AVAILABILITY_BLOCKED_DURING_TRIP/);
});

check('API wiring: GET + PATCH /driver/availability', () => {
  const routes = read('routes/api.php', backend);
  assert.match(routes, /DriverAvailabilityController/);
  assert.match(routes, /\/availability/);
  assert.match(routes, /v1\.driver\.availability\.show/);
  assert.match(routes, /v1\.driver\.availability\.update/);
  const controller = read(
    'app/Http/Controllers/Driver/DriverAvailabilityController.php',
    backend
  );
  assert.match(controller, /GetDriverSelfAvailabilityAction/);
  assert.match(controller, /UpdateDriverSelfAvailabilityAction/);
});

check('No driver_id spoofing on requests or mobile client', () => {
  const getReq = read(
    'app/Http/Requests/Driver/QueryDriverAvailabilityRequest.php',
    backend
  );
  const patchReq = read(
    'app/Http/Requests/Driver/UpdateDriverAvailabilityRequest.php',
    backend
  );
  assert.match(getReq, /'driver_id'\s*=>\s*\[['"]prohibited['"]\]/);
  assert.match(patchReq, /'driver_id'\s*=>\s*\[['"]prohibited['"]\]/);
  const api = read('src/features/driverApi/availability.ts');
  assert.match(api, /\/driver\/availability/);
  assert.doesNotMatch(api, /params:\s*\{[^}]*driver_id/);
  assert.doesNotMatch(api, /['"`]driver_id['"`]/);
});

check('Active-trip guard is server-authoritative', () => {
  const action = read(
    'app/Actions/Driver/UpdateDriverSelfAvailabilityAction.php',
    backend
  );
  assert.match(action, /lockForUpdate/);
  assert.match(action, /DRIVER_AVAILABILITY_BLOCKED_DURING_TRIP|BLOCKED_DURING_TRIP/);
  assert.match(action, /is_active/);
  const support = read(
    'app/Support/DriverSelfServiceAvailability.php',
    backend
  );
  assert.match(support, /BLOCKED_DURING_TRIP/);
  assert.match(support, /Offline/);
  assert.doesNotMatch(support, /is_active' => false/);
});

check('Availability UI exists with Account mutation and Work indicator', () => {
  const account = read('src/app/(tabs)/account.tsx');
  assert.match(account, /account-availability/);
  assert.match(account, /account-set-available/);
  assert.match(account, /account-set-unavailable/);
  assert.match(account, /updateDriverAvailability/);
  assert.match(account, /getDriverAvailability/);
  assert.match(account, /mapAvailabilityReason/);
  assert.match(account, /You're offline\. Availability can't be changed/);
  const reasons = read('src/features/driverApi/availability.ts');
  assert.match(reasons, /You can't change availability while you're on an active trip/);
  assert.doesNotMatch(account, /setAvailability\(\{\s*availability:/);
  const work = read('src/app/(tabs)/index.tsx');
  assert.match(work, /work-availability/);
  assert.match(work, /getDriverAvailability/);
  assert.doesNotMatch(work, /updateDriverAvailability/);
  assert.doesNotMatch(work, /account-set-unavailable/);
});

check('Offline guard does not queue a local mutation', () => {
  const account = read('src/app/(tabs)/account.tsx');
  assert.match(account, /!online/);
  assert.doesNotMatch(account, /queueAvailability|offlineQueue|AsyncStorage/);
  const api = read('src/features/driverApi/availability.ts');
  assert.doesNotMatch(api, /AsyncStorage/);
});

check('Server-authoritative mutation (no fake local toggle)', () => {
  const api = read('src/features/driverApi/availability.ts');
  assert.match(api, /apiClient\.patch/);
  assert.match(api, /mapDriverAvailability/);
  const account = read('src/app/(tabs)/account.tsx');
  assert.match(account, /const updated = await updateDriverAvailability/);
  assert.match(account, /setAvailability\(updated\)/);
});

check('Backend D13 tests exist with required markers', () => {
  const files = [
    'tests/Feature/Driver/DriverAvailabilityTest.php',
    'tests/Feature/Driver/DriverAvailabilityAuthorizationTest.php',
    'tests/Feature/Driver/DriverAvailabilityConcurrencyTest.php',
  ];
  for (const f of files) {
    assert.ok(exists(f, backend), `Missing ${f}`);
  }
  const functional = read('tests/Feature/Driver/DriverAvailabilityTest.php', backend);
  assert.match(functional, /test_get_current_availability_defaults_to_available/);
  assert.match(functional, /test_set_unavailable/);
  assert.match(functional, /test_in_operation_cannot_become_unavailable/);
  assert.match(functional, /test_idempotent_available/);
  const auth = read(
    'tests/Feature/Driver/DriverAvailabilityAuthorizationTest.php',
    backend
  );
  assert.match(auth, /test_unauthenticated_denied/);
  assert.match(auth, /test_non_driver_roles_forbidden/);
  assert.match(auth, /test_driver_cannot_mutate_another_drivers_availability/);
  const conc = read(
    'tests/Feature/Driver/DriverAvailabilityConcurrencyTest.php',
    backend
  );
  assert.match(conc, /test_stale_client_blocked_when_assignment_becomes_active/);
  assert.match(conc, /lockForUpdate/);
});

check('Zero migration; does not touch Client or Ops UI', () => {
  const migrations = fs.readdirSync(path.join(backend, 'database', 'migrations'));
  assert.ok(
    !migrations.some((f) => /d13|availability/i.test(f)),
    'Unexpected D13 migration'
  );
  const account = read('src/app/(tabs)/account.tsx');
  assert.doesNotMatch(account, /\/client\//);
  assert.doesNotMatch(account, /\/ops\//);
});

check('Package scripts include D13 chain', () => {
  const pkg = JSON.parse(read('package.json'));
  assert.ok(pkg.scripts['test:driver-d13']);
  assert.ok(pkg.scripts['test:d13-availability']);
  assert.match(pkg.scripts['test:d13-availability'], /test:d12-push/);
});

console.log('');
console.log(`DRIVER-D13: ${passed} passed, ${failed} failed`);
if (failed > 0) process.exit(1);
console.log('DRIVER-D13 VERDICT: PASS');
