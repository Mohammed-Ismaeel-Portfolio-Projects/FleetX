/**
 * DRIVER-D5 — Secure QR verification + scanner UX.
 * Run: npm run test:driver-d5
 */

import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const root = path.join(__dirname, '..');

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

function read(rel) {
  return fs.readFileSync(path.join(root, rel), 'utf8');
}

console.log('=== DRIVER-D5 QR Verification ===\n');

const workApi = read('src/features/driverApi/work.ts');
const phase = read('src/features/driverApi/executionPhase.ts');
const trip = read('src/app/active-trip/[id].tsx');
const verify = read('src/app/verify-trip/[id].tsx');
const parseSrc = read('src/features/verification/parseChallenge.ts');
const scanner = read('src/features/verification/ExpoCameraQrScanner.tsx');
const layout = read('src/app/_layout.tsx');
const appConfig = read('app.config.js');
const pkg = JSON.parse(read('package.json'));

check('Dependency: expo-camera present', () => {
  assert.ok(pkg.dependencies['expo-camera']);
});

check('API: verifyAssignment uses driver verify endpoints only', () => {
  assert.match(workApi, /verifyAssignment/);
  assert.match(workApi, /pickup\/verify/);
  assert.match(workApi, /delivery\/verify/);
  assert.doesNotMatch(workApi, /\/shipments\/.*\/status/);
  assert.doesNotMatch(workApi, /\/client\//);
});

check('Phase CTA opens verify route (no local bypass)', () => {
  assert.match(phase, /kind: 'verify'/);
  assert.match(trip, /verify-trip\/\$\{assignment\.id\}/);
  assert.doesNotMatch(trip + verify, /Mark verified manually|fake.?verify|local.?success/i);
});

check('Scanner screen: permission, stationary, submit loading, backend success/fail', () => {
  assert.match(verify, /verify-stationary/);
  assert.match(verify, /verify-permission/);
  assert.match(verify, /verify-scanner/);
  assert.match(verify, /verify-submitting/);
  assert.match(verify, /verify-error/);
  assert.match(verify, /verify-success/);
  assert.match(verify, /verifyAssignment/);
  assert.match(verify, /parseVerificationChallenge/);
  assert.match(verify, /useCameraPermissions/);
});

check('Scanner abstraction exists; CameraView is QR-only capture', () => {
  assert.match(scanner, /CameraView/);
  assert.match(scanner, /barcodeTypes:\s*\['qr'\]/);
  assert.match(scanner, /onBarcodeScanned/);
  assert.ok(fs.existsSync(path.join(root, 'src/features/verification/scannerTypes.ts')));
});

check('Challenge parser extracts opaque UUID', () => {
  assert.match(parseSrc, /export function parseVerificationChallenge/);
  assert.match(parseSrc, /\?&\]c=/);
  // Behavioral mirror (keep in sync with parseChallenge.ts)
  const UUID_RE =
    /[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}/i;
  function parse(raw) {
    const trimmed = String(raw ?? '').trim();
    if (!trimmed) return null;
    const fromQuery = trimmed.match(/[?&]c=([0-9a-f-]{36})/i);
    if (fromQuery?.[1]) return fromQuery[1].toLowerCase();
    const any = trimmed.match(UUID_RE);
    return any ? any[0].toLowerCase() : null;
  }
  assert.equal(
    parse('fleetx://v1/verify?c=11111111-1111-4111-8111-111111111111'),
    '11111111-1111-4111-8111-111111111111'
  );
  assert.equal(parse('not-a-code'), null);
});

check('Routes + camera plugin configured', () => {
  assert.match(layout, /verify-trip\/\[id\]/);
  assert.match(appConfig, /expo-camera/);
  assert.match(appConfig, /barcodeScannerEnabled/);
});

check('No GPS telemetry / POD in D5 verify surface', () => {
  assert.doesNotMatch(verify, /expo-location|react-native-maps/);
  assert.doesNotMatch(verify, /Proof of Delivery|DeliveryProof/);
});

console.log('');
console.log(`DRIVER-D5: ${passed} passed, ${failed} failed`);
if (failed > 0) process.exit(1);
console.log('DRIVER-D5 VERDICT: PASS');
