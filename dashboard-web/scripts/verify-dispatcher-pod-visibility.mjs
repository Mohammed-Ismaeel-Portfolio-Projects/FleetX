/**
 * Dispatcher POD Visibility Verification Script.
 * Run: node ./scripts/verify-dispatcher-pod-visibility.mjs
 */

import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const dashRoot = path.join(__dirname, '..');
const repoRoot = path.join(dashRoot, '..');

function readDash(rel) {
  return fs.readFileSync(path.join(dashRoot, rel), 'utf8');
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

const dispatcherPage = readDash('src/features/dispatcher/shipments/pages/ShipmentsPage.tsx');
const dispatcherDrawer = readDash('src/features/dispatcher/shipments/components/ShipmentDetailsDrawer.tsx');
const adminDrawer = readDash('src/features/admin/shipments/components/ShipmentDetailsDrawer.tsx');
const consoleComp = readDash('src/features/admin/shipments/components/DeliveryEvidenceConsole.tsx');
const apiRoute = readRepo('backend/routes/api.php');

check('1. Dispatcher ShipmentsPage uses ShipmentDetailsDrawer', () => {
  assert.match(dispatcherPage, /ShipmentDetailsDrawer/);
});

check('2. Dispatcher ShipmentDetailsDrawer renders DeliveryEvidenceConsole', () => {
  assert.match(dispatcherDrawer, /DeliveryEvidenceConsole/);
  assert.match(dispatcherDrawer, /shipmentId={shipment\.id}/);
});

check('3. Admin ShipmentDetailsDrawer renders DeliveryEvidenceConsole', () => {
  assert.match(adminDrawer, /DeliveryEvidenceConsole/);
  assert.match(adminDrawer, /shipmentId={shipment\.id}/);
});

check('4. DeliveryEvidenceConsole executes API GET /shipments/{id}/delivery-proof', () => {
  assert.match(consoleComp, /\/shipments\/.*\/delivery-proof/);
  assert.match(apiRoute, /v1\.shipments\.delivery-proof/);
});

check('5. POD Evidence Console displays receiver, condition, location & photo preview', () => {
  assert.match(consoleComp, /Receiver Name/);
  assert.match(consoleComp, /Cargo Condition/);
  assert.match(consoleComp, /Delivery Location Snapshot/);
  assert.match(consoleComp, /Photo Evidence/);
});

check('6. POD Certificate download trigger exists', () => {
  assert.match(consoleComp, /data-testid="pod-download-cert"/);
  assert.match(consoleComp, /\/delivery-proof\/certificate/);
});

if (process.exitCode) {
  console.error(`\nDispatcher POD Visibility verification failed after ${passed} checks.`);
} else {
  console.log(`\nDispatcher POD Visibility verification passed (${passed} checks).`);
}
