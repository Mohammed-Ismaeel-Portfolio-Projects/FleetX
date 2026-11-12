/**
 * INT-006 / INT-029 Client POD contract — focused static verification.
 * Run: node ./scripts/verify-int006-client-pod.mjs
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
const routes = readRepo('backend/routes/api.php');
const controller = readRepo('backend/app/Http/Controllers/Client/ClientShipmentController.php');
const action = readRepo('backend/app/Actions/Shipment/GetClientShipmentPodAction.php');
const showResource = readRepo('backend/app/Http/Resources/Client/ClientShipmentShowResource.php');
const storage = readRepo('backend/app/Support/DriverPodStorage.php');

check('client POD endpoints exist', () => {
  assert.match(routes, /shipments\/\{shipment\}\/pod\/status/);
  assert.match(routes, /shipments\/\{shipment\}\/pod/);
  assert.match(controller, /function podStatus/);
  assert.match(controller, /function pod/);
  assert.match(action, /forCustomer/);
});

check('private local disk is unchanged', () => {
  assert.match(storage, /public const DISK = 'local'/);
  assert.equal(controller.includes('Storage::url'), false);
  assert.equal(action.includes('Storage::url'), false);
  assert.equal(controller.includes("disk('public')"), false);
});

check('show resource still omits delivery_proof', () => {
  assert.equal(showResource.includes('delivery_proof'), false);
  assert.equal(showResource.includes('image_url'), false);
  assert.equal(showResource.includes('driver-pod'), false);
});

check('client API uses dedicated POD contract', () => {
  assert.match(api, /function getClientShipmentPodStatus/);
  assert.match(api, /function getClientShipmentPodFile/);
  assert.match(api, /\/client\/shipments\/\$\{encodeURIComponent\(id\)\}\/pod\/status/);
  assert.match(api, /\/client\/shipments\/\$\{encodeURIComponent\(id\)\}\/pod/);
  assert.match(api, /responseType: 'arraybuffer'/);
});

check('client UI does not dump storage internals', () => {
  assert.match(details, /getClientShipmentPodStatus/);
  assert.match(details, /getClientShipmentPodFile/);
  assert.match(details, /shipment-details-pod/);
  assert.match(details, /shipment-details-pod-unavailable/);
  assert.equal(details.includes('delivery_proof'), false);
  assert.equal(details.includes('image_url'), false);
  assert.equal(details.includes('driver-pod'), false);
  assert.equal(details.includes("disk"), false);
  assert.equal(types.includes('delivery_proof'), false);
  assert.equal(types.includes('image_url'), false);
});

check('availability drives the download action', () => {
  assert.match(details, /podState === 'available'/);
  assert.match(details, /Proof of delivery is not available yet/);
  assert.match(details, /Proof of Delivery/);
});

check('no public storage URL usage in client POD path', () => {
  assert.equal(api.includes('Storage::url'), false);
  assert.equal(api.includes('/storage/'), false);
  assert.equal(details.includes('/storage/'), false);
});

if (process.exitCode) {
  console.error(`\nINT-006 client POD verification failed after ${passed} checks.`);
} else {
  console.log(`\nINT-006 client POD verification passed (${passed} checks).`);
}
