/**
 * CLIENT-QR-05 Demo Hardening & Operational Polish Verification Script.
 * Run: node ./scripts/verify-client-qr-05-demo-polish.mjs
 */

import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const clientRoot = path.join(__dirname, '..');
const repoRoot = path.join(clientRoot, '..', '..');

function readClient(rel) {
  return fs.readFileSync(path.join(clientRoot, rel), 'utf8');
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

const card = readClient('src/features/shipments/ShipmentVerificationQrCard.tsx');
const driverVerify = readRepo('mobile-app/driver-app/src/app/verify-trip/[id].tsx');
const drawer = readRepo('dashboard-web/src/features/admin/shipments/components/ShipmentDetailsDrawer.tsx');

check('1. Client QR Card UX Messages & Badges', () => {
  assert.match(card, /Pickup Verification/);
  assert.match(card, /Delivery Verification/);
  assert.match(card, /Driver has arrived\. Show this QR code to confirm cargo pickup\./);
  assert.match(card, /Driver completed delivery\. Show this QR code to confirm delivery\./);
  assert.match(card, /No verification code available yet/);
  assert.match(card, /Verification code expired/);
  assert.match(card, /Generate a new secure code/);
});

check('2. Driver Scanner UX Messages & Guidance', () => {
  assert.match(driverVerify, /Waiting for customer QR code/);
  assert.match(driverVerify, /Scanning verification code/);
  assert.match(driverVerify, /Pickup verified successfully/);
  assert.match(driverVerify, /Delivery verified successfully/);
  assert.match(driverVerify, /Continue to Proof of Delivery/);
});

check('3. Dispatcher Read-Only Operational Visibility', () => {
  assert.match(drawer, /Actual Pickup/);
  assert.match(drawer, /Actual Delivery/);
  assert.match(drawer, /Status/);
});

check('4. No Fake QR Tokens or Architectural Violations', () => {
  assert.equal(card.includes('generateQr'), false);
  assert.equal(card.includes('Math.random'), false);
  assert.equal(driverVerify.includes('fakeToken'), false);
});

if (process.exitCode) {
  console.error(`\nCLIENT-QR-05 verification failed after ${passed} checks.`);
} else {
  console.log(`\nCLIENT-QR-05 verification passed (${passed} checks).`);
}
