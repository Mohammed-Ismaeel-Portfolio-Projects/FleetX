/**
 * POD-04 Dispatcher Evidence Console Verification Script.
 * Run: node ./scripts/verify-dispatcher-pod-04.mjs
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

const consoleComp = readDash('src/features/admin/shipments/components/DeliveryEvidenceConsole.tsx');
const sigRenderer = readDash('src/components/SignatureRenderer.tsx');
const drawerComp = readDash('src/features/admin/shipments/components/ShipmentDetailsDrawer.tsx');
const apiRoute = readRepo('backend/routes/api.php');

check('1. DeliveryEvidenceConsole component exists with testIDs and layout', () => {
  assert.match(consoleComp, /data-testid="pod-console"/);
  assert.match(consoleComp, /Verified Delivery/);
  assert.match(consoleComp, /Receiver Name/);
  assert.match(consoleComp, /Cargo Condition/);
});

check('2. SignatureRenderer component exists and renders SVG paths', () => {
  assert.match(sigRenderer, /SignatureRenderer/);
  assert.match(sigRenderer, /<svg/);
  assert.match(sigRenderer, /<path/);
});

check('3. ShipmentDetailsDrawer incorporates DeliveryEvidenceConsole', () => {
  assert.match(drawerComp, /DeliveryEvidenceConsole/);
  assert.match(drawerComp, /shipmentId={shipment\.id}/);
});

check('4. Delivery proof endpoint exists in backend router', () => {
  assert.match(apiRoute, /delivery-proof/);
  assert.match(apiRoute, /v1\.shipments\.delivery-proof/);
});

check('5. Open Map and Download Certificate buttons exist', () => {
  assert.match(consoleComp, /data-testid="pod-open-map"/);
  assert.match(consoleComp, /data-testid="pod-download-cert"/);
  assert.match(consoleComp, /Certificate generation coming soon/);
});

if (process.exitCode) {
  console.error(`\nDispatcher POD-04 verification failed after ${passed} checks.`);
} else {
  console.log(`\nDispatcher POD-04 verification passed (${passed} checks).`);
}
