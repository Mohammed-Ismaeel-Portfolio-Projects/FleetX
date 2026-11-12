/**
 * Dispatcher POD Evidence Console Integration Verification Script.
 * Run: node ./scripts/verify-dispatcher-pod-integration.mjs
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

const drawerComp = readDash('src/features/admin/shipments/components/ShipmentDetailsDrawer.tsx');
const consoleComp = readDash('src/features/admin/shipments/components/DeliveryEvidenceConsole.tsx');
const sigRenderer = readDash('src/components/SignatureRenderer.tsx');
const apiRoute = readRepo('backend/routes/api.php');

check('1. Shipment drawer contains Delivery Evidence section', () => {
  assert.match(drawerComp, /DeliveryEvidenceConsole/);
  assert.match(drawerComp, /shipmentId={shipment\.id}/);
});

check('2. API request for delivery-proof exists in console and backend router', () => {
  assert.match(consoleComp, /\/shipments\/.*\/delivery-proof/);
  assert.match(apiRoute, /v1\.shipments\.delivery-proof/);
});

check('3. Receiver information renders in console', () => {
  assert.match(consoleComp, /Receiver Name/);
  assert.match(consoleComp, /Receiver Role/);
});

check('4. Photo viewer with full image view exists', () => {
  assert.match(consoleComp, /Photo Evidence/);
  assert.match(consoleComp, /View Full Image/);
});

check('5. Signature renderer component exists and renders SVG strokes', () => {
  assert.match(sigRenderer, /SignatureRenderer/);
  assert.match(sigRenderer, /<svg/);
  assert.match(sigRenderer, /<path/);
});

check('6. Certificate download action exists', () => {
  assert.match(consoleComp, /data-testid="pod-download-cert"/);
  assert.match(consoleComp, /\/delivery-proof\/certificate/);
});

if (process.exitCode) {
  console.error(`\nDispatcher POD Integration verification failed after ${passed} checks.`);
} else {
  console.log(`\nDispatcher POD Integration verification passed (${passed} checks).`);
}
