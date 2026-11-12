/**
 * DRIVER-D15 — Live motion safety (D15-B policy + D15-C evaluator).
 * Run: npm run test:driver-d15
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

console.log('=== DRIVER-D15 Live Motion Safety ===\n');

for (const doc of [
  'D15_DISCOVERY.md',
  'D15_ARCHITECTURE.md',
  'D15B_IMPLEMENTATION_REPORT.md',
  'D15C_IMPLEMENTATION_REPORT.md',
  'D15D_DISCOVERY.md',
  'D15D_ARCHITECTURE.md',
  'D15D_IMPLEMENTATION_REPORT.md',
  'D15E_DISCOVERY.md',
  'D15E_ARCHITECTURE.md',
  'D15E_IMPLEMENTATION_REPORT.md',
  'D15_SECURITY_REVIEW.md',
]) {
  check(`Doc exists: ${doc}`, () => {
    assert.ok(fs.existsSync(path.join(docs, doc)), `Missing ${doc}`);
  });
}

check('Discovery lock: observe live; do not simulate', () => {
  const discovery = fs.readFileSync(path.join(docs, 'D15_DISCOVERY.md'), 'utf8');
  assert.match(discovery, /VehicleDynamicsEngine/);
  assert.match(discovery, /Observe live/);
  assert.match(discovery, /0\.06/);
});

check('Kinematics helper exists with explicit units', () => {
  const kin = read('app/Support/DriverMotionKinematics.php', backend);
  assert.match(kin, /kmhToMetersPerSecond/);
  assert.match(kin, /accelerationMs2/);
  assert.match(kin, /deltaTimeSeconds/);
  assert.doesNotMatch(kin, /VehicleDynamicsEngine/);
  assert.doesNotMatch(kin, /computeTick/);
});

check('Live motion policy is separate from D14 speed and sim constraints', () => {
  const cfg = read('config/driver_safety.php', backend);
  assert.match(cfg, /'speed'\s*=>/);
  assert.match(cfg, /'motion'\s*=>/);
  assert.match(cfg, /threshold_kmh/);
  assert.match(cfg, /harsh_brake_ms2/);
  assert.match(cfg, /2\.5/);
  assert.match(cfg, /1\.5/);
  const motionBlock = cfg.slice(cfg.indexOf("'motion'"));
  assert.doesNotMatch(motionBlock, /0\.15\b/);
  assert.doesNotMatch(motionBlock, /0\.22\b/);
  const policy = read('app/Support/DriverMotionSafetyPolicy.php', backend);
  assert.match(policy, /VehicleProfileFactory::normalizeFreightClass/);
  assert.match(policy, /isLiveDriverEvidence/);
  assert.match(policy, /FREIGHT_VAN/);
  assert.match(policy, /FREIGHT_HEAVY_TRUCK/);
});

check('D15-C evaluator hooks Driver ingest only, after D14', () => {
  const ingest = read('app/Actions/Driver/SubmitDriverTrackingAction.php', backend);
  assert.match(ingest, /EvaluateDriverSpeedSafetyAction/);
  assert.match(ingest, /EvaluateDriverMotionSafetyAction/);
  const speedPos = ingest.indexOf('EvaluateDriverSpeedSafetyAction');
  const motionPos = ingest.indexOf('EvaluateDriverMotionSafetyAction');
  assert.ok(speedPos >= 0 && motionPos > speedPos, 'D15-C must run after D14');

  const writer = read('app/Actions/Tracking/CreateTrackingLogAction.php', backend);
  assert.doesNotMatch(writer, /EvaluateDriverMotion/);
  assert.doesNotMatch(writer, /DriverMotionSafetyPolicy/);

  const evalAction = read(
    'app/Actions/Driver/EvaluateDriverMotionSafetyAction.php',
    backend
  );
  assert.match(evalAction, /whereNotNull\('received_at'\)/);
  assert.match(evalAction, /lockForUpdate/);
  assert.match(evalAction, /DriverMotionKinematics/);
  assert.doesNotMatch(evalAction, /VehicleDynamicsEngine/);
  assert.doesNotMatch(evalAction, /TrackingSimulatorService/);
  assert.doesNotMatch(evalAction, /EnvironmentEngine/);
  assert.doesNotMatch(evalAction, /DriverIntelligenceEngine/);
  assert.doesNotMatch(evalAction, /VehicleHealthEngine/);
  assert.doesNotMatch(evalAction, /speed_delta_kmh/);
});

check('D15-D consumes inbox episodes; no second detector', () => {
  const evalAction = read(
    'app/Actions/Driver/EvaluateDriverMotionSafetyAction.php',
    backend
  );
  assert.match(evalAction, /Sharp slowdown detected/);
  assert.match(evalAction, /Rapid stop detected/);
  assert.doesNotMatch(evalAction, /you crashed|violated the law/i);

  const summary = read(
    'app/Actions/Driver/GetDriverAssignmentSummaryAction.php',
    backend
  );
  assert.match(summary, /groupBy\('code'\)/);
  assert.match(summary, /HARSH_BRAKING/);
  assert.match(summary, /SUDDEN_STOP/);
  assert.doesNotMatch(summary, /VehicleDynamicsEngine/);
  assert.doesNotMatch(summary, /TrackingLog/);

  const resource = read(
    'app/Http/Resources/Driver/DriverAssignmentSummaryResource.php',
    backend
  );
  assert.match(resource, /harsh_braking_count/);
  assert.match(resource, /sudden_stop_count/);
  assert.match(resource, /speed_alert_count/);
  assert.match(resource, /motion_alert_count/);
});

check('Notification codes and push policy are independent of D14', () => {
  const codes = read('app/Support/DriverNotificationCode.php', backend);
  assert.match(codes, /SPEED_EXCESS/);
  assert.match(codes, /safety\.harsh_braking/);
  assert.match(codes, /safety\.sudden_stop/);
  const push = read('app/Support/DriverPushPolicy.php', backend);
  assert.match(push, /HARSH_BRAKING/);
  assert.match(push, /SUDDEN_STOP/);
  const speed = read('app/Support/DriverSpeedSafetyPolicy.php', backend);
  assert.match(speed, /thresholdKmh/);
  assert.doesNotMatch(speed, /harshBrake/);
});

check('Zero D15 migration', () => {
  const migrations = fs.readdirSync(path.join(backend, 'database', 'migrations'));
  assert.ok(!migrations.some((f) => /d15|motion_event|harsh_brake/i.test(f)));
});

check('No client-side motion detector', () => {
  const loc = read('src/features/tracking/locationProvider.ts');
  assert.doesNotMatch(loc, /harsh_brake|a_obs|threshold_ms2/);
  const pub = read('src/features/tracking/TelemetryPublisher.ts');
  assert.doesNotMatch(pub, /harsh_brake|sudden_stop/);
});

check('Motion-safe Driver UX for D15 codes', () => {
  const banner = read('src/features/notifications/TripAwarenessBanner.tsx');
  assert.match(banner, /trip-awareness-motion/);
  assert.match(banner, /trip-awareness-harsh/);
  assert.match(banner, /trip-awareness-sudden/);
  assert.match(banner, /MOTION SAFETY ALERT/);
  assert.match(banner, /safety\.harsh_braking/);
  assert.match(banner, /safety\.sudden_stop/);
  assert.match(banner, /isVehicleMoving/);
  assert.match(banner, /onCriticalChange\?\.\(picked\.critical !== null\)/);
  assert.doesNotMatch(banner, /Alert\.alert/);
  const history = read('src/app/history/[id].tsx');
  assert.match(history, /sharp-slowdown notice/);
  assert.match(history, /rapid-stop notice/);
  assert.match(history, /not crash or legal findings/);
  assert.match(history, /not a legal road speed limit/);
  const inbox = read('src/app/(tabs)/notifications.tsx');
  assert.doesNotMatch(inbox, /isVehicleMoving|drivingMotion/);
  const trip = read('src/app/active-trip/[id].tsx');
  assert.match(trip, /onCriticalChange=\{setAwarenessCritical\}/);
  assert.match(trip, /awarenessCritical/);
});

check('D15-E authorization tests cover mark-read IDOR and push isolation', () => {
  const auth = read(
    'tests/Feature/Driver/DriverMotionSafetyAuthorizationTest.php',
    backend
  );
  assert.match(auth, /test_driver_cannot_mark_read_another_drivers_motion_alert/);
  assert.match(auth, /test_forged_motion_notification_id_is_not_found/);
  assert.match(auth, /test_mark_all_read_does_not_touch_another_drivers_motion_alert/);
  assert.match(auth, /test_motion_push_does_not_target_another_drivers_device/);
  const evalTest = read(
    'tests/Feature/Driver/DriverMotionSafetyTest.php',
    backend
  );
  assert.match(evalTest, /test_push_disabled_still_writes_inbox_and_does_not_mutate_availability/);
});

check('Backend D15 tests exist', () => {
  for (const f of [
    'tests/Unit/Support/DriverMotionKinematicsTest.php',
    'tests/Unit/Support/DriverMotionSafetyPolicyTest.php',
    'tests/Unit/Support/DriverMotionSafetyArchitectureTest.php',
    'tests/Feature/Driver/DriverMotionSafetyTest.php',
    'tests/Feature/Driver/DriverMotionSafetyAuthorizationTest.php',
    'tests/Feature/Driver/DriverMotionSafetySummaryTest.php',
  ]) {
    assert.ok(exists(f, backend), `Missing ${f}`);
  }
});

check('Package scripts include D15', () => {
  const pkg = JSON.parse(read('package.json'));
  assert.ok(pkg.scripts['test:driver-d15']);
  assert.ok(pkg.scripts['test:d15-motion']);
  assert.match(pkg.scripts['test:d15-motion'], /test:d14-speed/);
});

console.log('');
console.log(`DRIVER-D15: ${passed} passed, ${failed} failed`);
if (failed > 0) process.exit(1);
console.log('DRIVER-D15 VERDICT: PASS');
