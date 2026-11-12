/**
 * POD-04 Client App Evidence View Verification Script.
 * Run: node ./scripts/verify-client-pod-04.mjs
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

const apiFile = readClient('src/features/shipments/shipmentsApi.ts');
const podCard = readClient('src/features/shipments/ProofOfDeliveryCard.tsx');
const sigRenderer = readClient('src/components/SignatureRenderer.tsx');
const detailsScreen = readClient('src/app/shipment-details/[id].tsx');
const apiRoute = readRepo('backend/routes/api.php');

check('1. getClientShipmentDeliveryProof exists in shipmentsApi.ts', () => {
  assert.match(apiFile, /getClientShipmentDeliveryProof/);
  assert.match(apiFile, /\/client\/shipments\/.*\/delivery-proof/);
});

check('2. ProofOfDeliveryCard component exists with testID and evidence layout', () => {
  assert.match(podCard, /testID="client-pod-card"/);
  assert.match(podCard, /PROOF OF DELIVERY/);
  assert.match(podCard, /Receiver/);
  assert.match(podCard, /Condition/);
});

check('3. SignatureRenderer component exists and uses react-native-svg', () => {
  assert.match(sigRenderer, /SignatureRenderer/);
  assert.match(sigRenderer, /react-native-svg/);
  assert.match(sigRenderer, /<Path/);
});

check('4. shipment-details/[id].tsx incorporates ProofOfDeliveryCard', () => {
  assert.match(detailsScreen, /ProofOfDeliveryCard/);
  assert.match(detailsScreen, /shipmentId={shipmentId}/);
});

check('5. Backend client delivery-proof route registered', () => {
  assert.match(apiRoute, /v1\.client\.shipments\.delivery-proof/);
});

if (process.exitCode) {
  console.error(`\nClient POD-04 verification failed after ${passed} checks.`);
} else {
  console.log(`\nClient POD-04 verification passed (${passed} checks).`);
}
