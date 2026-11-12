/**
 * DRIVER-D14 — Server-authoritative speed safety alerts.
 * Run: npm run test:driver-d14
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

console.log('=== DRIVER-D14 Speed Safety Alerts ===\n');

for (const doc of [
  'D14_DISCOVERY.md',
  'D14_SECURITY_REVIEW.md',
  'D14_IMPLEMENTATION_REPORT.md',
]) {
  check(`Doc exists: ${doc}`, () => {
    assert.ok(fs.existsSync(path.join(docs, doc)), `Missing ${doc}`);
  });
}

check('Discovery: reuse device km/h; no legal road limits; zero migration', () => {
  const discovery = fs.readFileSync(path.join(docs, 'D14_DISCOVERY.md'), 'utf8');
  assert.match(discovery, /Outcome A/i);
  assert.match(discovery, /km\/h/);
  assert.match(discovery, /Zero migration/i);
  assert.match(discovery, /NOT legal/i);
});

check('Detection architecture is server-side after D6 ingest', () => {
  const ingest = read('app/Actions/Driver/SubmitDriverTrackingAction.php', backend);
  const evalAction = read(
    'app/Actions/Driver/EvaluateDriverSpeedSafetyAction.php',
    backend
  );
  assert.match(ingest, /EvaluateDriverSpeedSafetyAction/);
  assert.match(ingest, /deduped/);
  assert.match(evalAction, /DriverSpeedSafetyPolicy/);
  assert.match(evalAction, /minSamples|min_samples/);
  assert.match(evalAction, /cooldown/);
  assert.doesNotMatch(evalAction, /legal speed/);
});

check('No client-only violation logic or client-provided alert trust', () => {
  const req = read(
    'app/Http/Requests/Driver/SubmitDriverTrackingRequest.php',
    backend
  );
  assert.match(req, /'violation'\s*=>\s*\[['"]prohibited['"]\]/);
  const tracking = read('src/features/driverApi/work.ts');
  assert.doesNotMatch(tracking, /violation|speed_alert|speed_exceeded/);
  const loc = read('src/features/tracking/locationProvider.ts');
  assert.doesNotMatch(loc, /threshold_kmh|SPEED SAFETY/);
  const pub = read('src/features/tracking/TelemetryPublisher.ts');
  assert.doesNotMatch(pub, /speed_alert|violation/);
});

check('Active-trip gate + cooldown/debounce exist', () => {
  const evalAction = read(
    'app/Actions/Driver/EvaluateDriverSpeedSafetyAction.php',
    backend
  );
  assert.match(evalAction, /IN_OPERATION/);
  assert.match(evalAction, /is_active/);
  assert.match(evalAction, /cooldownSeconds|cooldown_seconds/);
  const policy = read('app/Support/DriverSpeedSafetyPolicy.php', backend);
  assert.match(policy, /thresholdKmh/);
  assert.match(policy, /enabled/);
});

check('Notification integration uses inbox SSOT and best-effort push', () => {
  const codes = read('app/Support/DriverNotificationCode.php', backend);
  assert.match(codes, /SPEED_EXCESS/);
  assert.match(codes, /safety\.speed_excess/);
  const push = read('app/Support/DriverPushPolicy.php', backend);
  assert.match(push, /SPEED_EXCESS/);
  const evalAction = read(
    'app/Actions/Driver/EvaluateDriverSpeedSafetyAction.php',
    backend
  );
  assert.match(evalAction, /DriverNotificationWriter/);
  assert.match(evalAction, /warning/);
  assert.doesNotMatch(evalAction, /'critical'/);
});

check('Motion-safe Driver UX exists', () => {
  const banner = read('src/features/notifications/TripAwarenessBanner.tsx');
  assert.match(banner, /trip-awareness-speed/);
  assert.match(banner, /SPEED SAFETY ALERT/);
  assert.match(banner, /isVehicleMoving/);
  assert.match(banner, /No action needed while driving/);
  assert.doesNotMatch(banner, /Alert\.alert/);
});

check('Backend D14 tests exist with required markers', () => {
  const files = [
    'tests/Feature/Driver/DriverSpeedSafetyTest.php',
    'tests/Feature/Driver/DriverSpeedSafetyAuthorizationTest.php',
  ];
  for (const f of files) {
    assert.ok(exists(f, backend), `Missing ${f}`);
  }
  const functional = read('tests/Feature/Driver/DriverSpeedSafetyTest.php', backend);
  assert.match(functional, /test_sustained_excess_creates_one_notification/);
  assert.match(functional, /test_single_sample_above_threshold_does_not_alert/);
  assert.match(functional, /test_cooldown_suppresses_repeat_while_still_speeding/);
  assert.match(functional, /test_push_failure_does_not_fail_telemetry/);
  assert.match(functional, /test_feature_disabled_does_not_alert/);
  assert.match(functional, /test_repeat_evaluate_does_not_duplicate_alert/);
  const auth = read(
    'tests/Feature/Driver/DriverSpeedSafetyAuthorizationTest.php',
    backend
  );
  assert.match(auth, /test_unauthenticated_denied/);
  assert.match(auth, /test_driver_cannot_see_another_drivers_speed_alert/);
});

check('Zero D14 migration; Client/Ops UI untouched', () => {
  const migrations = fs.readdirSync(path.join(backend, 'database', 'migrations'));
  assert.ok(!migrations.some((f) => /d14|speed_safety/i.test(f)));
  const ingest = read('app/Actions/Driver/SubmitDriverTrackingAction.php', backend);
  assert.doesNotMatch(ingest, /\/client\//);
});

check('Package scripts include D14 chain', () => {
  const pkg = JSON.parse(read('package.json'));
  assert.ok(pkg.scripts['test:driver-d14']);
  assert.ok(pkg.scripts['test:d14-speed']);
  assert.match(pkg.scripts['test:d14-speed'], /test:d13-availability/);
});

console.log('');
console.log(`DRIVER-D14: ${passed} passed, ${failed} failed`);
if (failed > 0) process.exit(1);
console.log('DRIVER-D14 VERDICT: PASS');
