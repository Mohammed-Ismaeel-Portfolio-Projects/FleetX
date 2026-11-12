/**
 * DRIVER-D12 — Push notifications & device registration.
 * Run: npm run test:driver-d12
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

console.log('=== DRIVER-D12 Push Notifications ===\n');

for (const doc of [
  'D12_DISCOVERY.md',
  'D12_SECURITY_REVIEW.md',
  'D12_IMPLEMENTATION_REPORT.md',
]) {
  check(`Doc exists: ${doc}`, () => {
    assert.ok(fs.existsSync(path.join(docs, doc)), `Missing ${doc}`);
  });
}

check('Discovery: inbox remains source of truth; Expo Push chosen', () => {
  const discovery = fs.readFileSync(path.join(docs, 'D12_DISCOVERY.md'), 'utf8');
  assert.match(discovery, /SOURCE OF TRUTH/);
  assert.match(discovery, /Expo Push/);
  assert.match(discovery, /assignment\.cancelled/);
  assert.match(discovery, /pickup\.arrival[\s\S]*NO/);
});

check('driver_devices migration exists with down()', () => {
  const mig = read(
    'database/migrations/2026_08_17_150000_create_driver_devices_table.php',
    backend
  );
  assert.match(mig, /driver_devices/);
  assert.match(mig, /push_token/);
  assert.match(mig, /dropIfExists\('driver_devices'\)/);
  assert.match(mig, /Schema::create\('driver_devices'/);
  assert.doesNotMatch(mig, /Schema::create\('driver_notifications'/);
});

check('Push service is abstracted (not scattered in controllers)', () => {
  const iface = read('app/Services/Driver/PushNotificationService.php', backend);
  const expo = read('app/Services/Driver/ExpoPushNotificationService.php', backend);
  const dispatcher = read('app/Support/DriverPushDispatcher.php', backend);
  const policy = read('app/Support/DriverPushPolicy.php', backend);
  assert.match(iface, /interface PushNotificationService/);
  assert.match(expo, /exp\.host/);
  assert.match(dispatcher, /dispatchBestEffort/);
  assert.match(policy, /ASSIGNMENT_CANCELLED/);
  assert.match(policy, /MESSAGE_UNREAD/);
  assert.doesNotMatch(policy, /PICKUP_ARRIVAL/);
});

check('Writer push is best-effort after new inbox rows', () => {
  const writer = read('app/Support/DriverNotificationWriter.php', backend);
  assert.match(writer, /wasRecentlyCreated/);
  assert.match(writer, /DriverPushDispatcher/);
  assert.match(writer, /Never throws/);
});

check('Device APIs prohibit client-supplied driver identity', () => {
  const routes = read('routes/api.php', backend);
  assert.match(routes, /devices\/register/);
  assert.match(routes, /devices\/unregister/);
  assert.match(routes, /DriverDeviceController/);
  const reg = read('app/Http/Requests/Driver/RegisterDriverDeviceRequest.php', backend);
  assert.match(reg, /'driver_id'\s*=>\s*\[['"]prohibited['"]\]/);
  const devicesApi = read('src/features/driverApi/devices.ts');
  assert.match(devicesApi, /\/driver\/devices\/register/);
  assert.doesNotMatch(devicesApi, /params:\s*\{[^}]*driver_id/);
  assert.doesNotMatch(devicesApi, /['"`]driver_id['"`]/);
});

check('List resource omits raw push token', () => {
  const resource = read('app/Http/Resources/Driver/DriverDeviceResource.php', backend);
  assert.match(resource, /'platform'/);
  assert.doesNotMatch(resource, /'push_token'/);
});

check('Backend D12 tests exist with required markers', () => {
  const files = [
    'tests/Feature/Driver/DriverDeviceRegistrationTest.php',
    'tests/Feature/Driver/DriverDeviceAuthorizationTest.php',
    'tests/Feature/Driver/DriverPushDeliveryTest.php',
    'tests/Feature/Driver/DriverPushIsolationTest.php',
    'tests/Feature/Driver/DriverPushDeepLinkTest.php',
  ];
  for (const f of files) {
    assert.ok(exists(f, backend), `Missing ${f}`);
  }
  const delivery = read('tests/Feature/Driver/DriverPushDeliveryTest.php', backend);
  assert.match(delivery, /test_expo_failure_does_not_fail_business_action_or_inbox/);
  assert.match(delivery, /test_device_not_registered_deactivates_token/);
  const auth = read('tests/Feature/Driver/DriverDeviceAuthorizationTest.php', backend);
  assert.match(auth, /test_unauthenticated_denied/);
  assert.match(auth, /test_non_driver_roles_forbidden/);
  const deep = read('tests/Feature/Driver/DriverPushDeepLinkTest.php', backend);
  assert.match(deep, /assertArrayNotHasKey\('execution_phase'/);
});

check('Mobile: expo-notifications + permission CTA + tap routing', () => {
  const pkg = JSON.parse(read('package.json'));
  assert.ok(pkg.dependencies['expo-notifications'], 'expo-notifications missing');
  const app = read('app.config.js');
  assert.match(app, /expo-notifications/);
  assert.match(app, /POST_NOTIFICATIONS/);
  const account = read('src/app/(tabs)/account.tsx');
  assert.match(account, /account-enable-trip-alerts/);
  assert.match(account, /enableDriverPush/);
  const auth = read('src/lib/auth/AuthContext.tsx');
  assert.match(auth, /unregisterDriverPushBestEffort/);
  const boot = read('src/lib/push/PushBootstrap.tsx');
  assert.match(boot, /addNotificationResponseReceivedListener/);
  assert.match(boot, /hrefFromDriverPushData/);
  assert.match(boot, /syncDriverPushIfGranted/);
  const payload = read('src/lib/push/pushPayload.ts');
  assert.match(payload, /parseDriverPushData/);
  assert.match(payload, /notificationHref/);
  const reg = read('src/lib/push/pushRegistration.ts');
  assert.match(reg, /canOperate|getExpoPushTokenAsync/);
  assert.match(reg, /requestPermissionsAsync/);
  assert.doesNotMatch(reg, /\/client\//);
});

check('Push tap does not mutate; D1–D11 screens still re-fetch', () => {
  const boot = read('src/lib/push/PushBootstrap.tsx');
  assert.doesNotMatch(boot, /completeAssignment|submitPod|acknowledgeAssignment/);
  const trip = read('src/app/active-trip/[id].tsx');
  assert.match(trip, /getAssignment|stale/);
});

check('PHPUnit disables outbound push by default', () => {
  const xml = read('phpunit.xml', backend);
  assert.match(xml, /DRIVER_PUSH_ENABLED/);
  assert.match(xml, /false/);
});

check('Package scripts include D12 chain', () => {
  const pkg = JSON.parse(read('package.json'));
  assert.ok(pkg.scripts['test:driver-d12']);
  assert.ok(pkg.scripts['test:d12-push']);
});

console.log('');
console.log(`DRIVER-D12: ${passed} passed, ${failed} failed`);
if (failed > 0) process.exit(1);
console.log('DRIVER-D12 VERDICT: PASS');
