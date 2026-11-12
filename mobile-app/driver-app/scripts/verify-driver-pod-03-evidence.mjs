/**
 * POD-03 Driver App Evidence Capture Upgrade Verification Script.
 * Run: node ./scripts/verify-driver-pod-03-evidence.mjs
 */

import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const driverRoot = path.join(__dirname, '..');
const repoRoot = path.join(driverRoot, '..', '..');

function readDriver(rel) {
  return fs.readFileSync(path.join(driverRoot, rel), 'utf8');
}

function readRepo(rel) {
  return fs.readFileSync(path.join(repoRoot, rel), 'utf8');
}

let passed = 0;
function check(name, fn) {
  try {
    fn();
    passed += 1;
    console.log(`PASS  ${name}`);
  } catch (e) {
    console.error(`FAIL  ${name}`);
    console.error(e);
    process.exitCode = 1;
  }
}

const podScreen = readDriver('src/app/pod/[id].tsx');
const workApi = readDriver('src/features/driverApi/work.ts');
const pkg = readDriver('package.json');
const request = readRepo('backend/app/Http/Requests/Driver/SubmitDriverPodRequest.php');

check('1. Driver POD screen contains receiver fields', () => {
  assert.match(podScreen, /testID="pod-receiver-name"/);
  assert.match(podScreen, /testID="pod-receiver-role"/);
  assert.match(podScreen, /Receiver Name/);
});

check('2. Condition options exist on POD screen', () => {
  assert.match(podScreen, /pod-condition-/);
  assert.match(podScreen, /CONDITION_OPTIONS/);
  assert.match(podScreen, /good/);
});

check('3. Delivery notes field exists on POD screen', () => {
  assert.match(podScreen, /testID="pod-delivery-notes"/);
  assert.match(podScreen, /Delivery Notes/);
});

check('4. GPS snapshot integration exists using expo-location', () => {
  assert.match(podScreen, /expo-location/);
  assert.match(podScreen, /getCurrentPositionAsync/);
  assert.match(podScreen, /Location unavailable\. Evidence will be submitted without GPS confirmation/);
});

check('5. Payload in workApi includes new evidence fields', () => {
  assert.match(workApi, /receiver_name/);
  assert.match(workApi, /receiver_role/);
  assert.match(workApi, /condition_status/);
  assert.match(workApi, /delivery_notes/);
  assert.match(workApi, /latitude/);
  assert.match(workApi, /longitude/);
});

check('6. Forbidden fields are prohibited on backend request', () => {
  assert.match(request, /'driver_id' => \['prohibited'\]/);
  assert.match(request, /'shipment_id' => \['prohibited'\]/);
  assert.match(request, /'assignment_id' => \['prohibited'\]/);
});

check('7. Package.json contains expo-location dependency', () => {
  assert.match(pkg, /expo-location/);
});

if (process.exitCode) {
  console.error(`\nPOD-03 verification failed after ${passed} checks.`);
} else {
  console.log(`\nPOD-03 verification passed (${passed} checks).`);
}
