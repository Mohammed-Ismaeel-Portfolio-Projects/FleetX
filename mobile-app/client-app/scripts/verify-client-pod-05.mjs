/**
 * POD-05 Client App Certificate Download Verification Script.
 * Run: node ./scripts/verify-client-pod-05.mjs
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
const apiRoute = readRepo('backend/routes/api.php');

check('1. downloadClientShipmentCertificate exists in shipmentsApi.ts', () => {
  assert.match(apiFile, /downloadClientShipmentCertificate/);
  assert.match(apiFile, /\/client\/shipments\/.*\/delivery-proof\/certificate/);
});

check('2. Download Delivery Certificate button exists on ProofOfDeliveryCard', () => {
  assert.match(podCard, /testID="client-pod-download-cert"/);
  assert.match(podCard, /Download Delivery Certificate/);
});

check('3. Client certificate download handler handles loading and error states', () => {
  assert.match(podCard, /setCertDownloading/);
  assert.match(podCard, /setCertNotice/);
});

check('4. Backend client certificate route registered', () => {
  assert.match(apiRoute, /v1\.client\.shipments\.delivery-proof\.certificate/);
});

if (process.exitCode) {
  console.error(`\nClient POD-05 verification failed after ${passed} checks.`);
} else {
  console.log(`\nClient POD-05 verification passed (${passed} checks).`);
}
