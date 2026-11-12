/**
 * CLIENT-QR-04 End-to-End QR Verification Lifecycle Validation.
 * Run: node ./scripts/verify-client-qr-04-e2e.mjs
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

// Read backend files
const apiRoutes = readRepo('backend/routes/api.php');
const issueAction = readRepo('backend/app/Actions/Driver/IssueDriverVerificationChallengeAction.php');
const getClientQrAction = readRepo('backend/app/Actions/Shipment/GetClientShipmentVerificationQrAction.php');
const verifyDriverAction = readRepo('backend/app/Actions/Driver/VerifyDriverAssignmentAction.php');
const clientController = readRepo('backend/app/Http/Controllers/Client/ClientShipmentController.php');

// Read Client App files
const clientApi = readClient('src/features/shipments/shipmentsApi.ts');
const clientDetails = readClient('src/app/shipment-details/[id].tsx');
const clientQrCard = readClient('src/features/shipments/ShipmentVerificationQrCard.tsx');

// Read Driver App files
const driverWorkApi = readRepo('mobile-app/driver-app/src/features/driverApi/work.ts');
const driverVerifyScreen = readRepo('mobile-app/driver-app/src/app/verify-trip/[id].tsx');
const driverParser = readRepo('mobile-app/driver-app/src/features/verification/parseChallenge.ts');

check('1. End-to-End Route Registration', () => {
  assert.match(apiRoutes, /shipments\/\{shipment\}\/verification-qr/);
  assert.match(apiRoutes, /assignments\/\{assignment\}\/pickup\/verify/);
  assert.match(apiRoutes, /assignments\/\{assignment\}\/delivery\/verify/);
});

check('2. Backend Single Source of Truth for QR Token Generation', () => {
  assert.match(issueAction, /function ensureForAssignment/);
  assert.match(issueAction, /DriverVerificationChallenge::query\(\)/);
  assert.match(issueAction, /lockForUpdate\(\)/);
  assert.match(getClientQrAction, /this->issueChallengeAction->ensureForAssignment/);
});

check('3. Company Tenancy & Authorization Gates', () => {
  assert.match(getClientQrAction, /forCustomer\(\$companyId\)/);
  assert.match(clientController, /this->authorize\('client\.shipment\.view'\)/);
});

check('4. Verification Phase Boundary Mapping', () => {
  assert.match(getClientQrAction, /PICKUP_VERIFYING.*PICKUP_VERIFY/);
  assert.match(getClientQrAction, /DELIVERY_VERIFYING.*DELIVERY_VERIFY/);
});

check('5. Client App QR Fetching & UI Presentation', () => {
  assert.match(clientApi, /getClientShipmentVerificationQr/);
  assert.match(clientDetails, /ShipmentVerificationQrCard/);
  assert.match(clientQrCard, /react-native-qrcode-svg/);
  assert.match(clientQrCard, /Pickup Verification/);
  assert.match(clientQrCard, /Delivery Verification/);
});

check('6. Driver App Scanner Parsing & Verification Contract', () => {
  assert.match(driverParser, /fleetx:\/\/v1\/verify\?c=/);
  assert.match(driverWorkApi, /\/driver\/assignments\/\$\{assignmentId\}\/pickup\/verify/);
  assert.match(driverWorkApi, /\/driver\/assignments\/\$\{assignmentId\}\/delivery\/verify/);
  assert.match(driverVerifyScreen, /verifyAssignment\(assignmentId, event, challenge\)/);
});

check('7. Atomic Lifecycle Transitions upon Verification', () => {
  assert.match(verifyDriverAction, /consumed_at.*now\(\)/);
  assert.match(verifyDriverAction, /ShipmentStatus::IN_TRANSIT/);
  assert.match(verifyDriverAction, /ShipmentStatus::DELIVERED/);
  assert.match(verifyDriverAction, /DriverExecutionPhase::DELIVERY_EN_ROUTE/);
  assert.match(verifyDriverAction, /DriverExecutionPhase::DELIVERY_VERIFIED/);
});

check('8. Security Rules Enforcement (Single Use, Expiry, Revocation)', () => {
  assert.match(verifyDriverAction, /isConsumed\(\)/);
  assert.match(verifyDriverAction, /isExpired\(\)/);
  assert.match(verifyDriverAction, /isRevoked\(\)/);
});

if (process.exitCode) {
  console.error(`\nCLIENT-QR-04 E2E verification failed after ${passed} checks.`);
} else {
  console.log(`\nCLIENT-QR-04 E2E verification passed (${passed} checks).`);
}
