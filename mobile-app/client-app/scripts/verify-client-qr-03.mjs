/**
 * CLIENT-QR-03 Client QR Display verification.
 * Run: node ./scripts/verify-client-qr-03.mjs
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

const details = readClient('src/app/shipment-details/[id].tsx');
const api = readClient('src/features/shipments/shipmentsApi.ts');
const types = readClient('src/features/shipments/types.ts');
const card = readClient('src/features/shipments/ShipmentVerificationQrCard.tsx');
const pkg = readClient('package.json');
const routes = readRepo('backend/routes/api.php');
const action = readRepo('backend/app/Actions/Shipment/GetClientShipmentVerificationQrAction.php');

check('package.json includes react-native-qrcode-svg', () => {
  assert.match(pkg, /react-native-qrcode-svg/);
});

check('client types include verification contract', () => {
  assert.match(types, /ClientVerificationEvent/);
  assert.match(types, /ClientVerificationQr/);
  assert.match(types, /pickup_verify/);
  assert.match(types, /delivery_verify/);
});

check('client shipmentsApi connects to verification-qr endpoint', () => {
  assert.match(api, /getClientShipmentVerificationQr/);
  assert.match(api, /\/client\/shipments\/\$\{encodeURIComponent\(id\)\}\/verification-qr/);
});

check('ShipmentVerificationQrCard component implements dynamic headers & states', () => {
  assert.match(card, /react-native-qrcode-svg/);
  assert.match(card, /Pickup Verification/);
  assert.match(card, /Delivery Verification/);
  assert.match(card, /Preparing verification code/);
  assert.match(card, /Unable to load verification code/);
  assert.match(card, /Verification Code Expired/);
  assert.match(card, /testID="client-qr-card"/);
  assert.match(card, /testID="qr-header-title"/);
  assert.match(card, /testID="qr-expiry"/);
});

check('shipment-details integrates ShipmentVerificationQrCard and focus refresh', () => {
  assert.match(details, /ShipmentVerificationQrCard/);
  assert.match(details, /getClientShipmentVerificationQr/);
  assert.match(details, /loadVerificationQr/);
  assert.match(details, /useFocusEffect/);
});

check('client never generates fake QR codes or mutates payload', () => {
  assert.equal(card.includes('generateQr'), false);
  assert.equal(details.includes('generateQr'), false);
  assert.equal(card.includes('Math.random'), false);
});

check('backend contract route and action remain aligned', () => {
  assert.match(routes, /shipments\/\{shipment\}\/verification-qr/);
  assert.match(action, /forCustomer/);
  assert.match(action, /ensureForAssignment/);
});

if (process.exitCode) {
  console.error(`\nCLIENT-QR-03 verification failed after ${passed} checks.`);
} else {
  console.log(`\nCLIENT-QR-03 verification passed (${passed} checks).`);
}
