/**
 * POD-05 Dispatcher Dashboard Certificate Verification Script.
 * Run: node ./scripts/verify-dispatcher-pod-05.mjs
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
const apiRoute = readRepo('backend/routes/api.php');
const migration = readRepo('backend/database/migrations/2026_08_22_020000_create_delivery_certificates_table.php');

check('1. Download POD Certificate button exists on Dispatcher Console', () => {
  assert.match(consoleComp, /data-testid="pod-download-cert"/);
  assert.match(consoleComp, /Download POD Certificate/);
});

check('2. Dispatcher console requests certificate PDF blob endpoint', () => {
  assert.match(consoleComp, /\/shipments\/.*\/delivery-proof\/certificate/);
  assert.match(consoleComp, /responseType:\s*'blob'/);
});

check('3. Backend ops certificate route is registered', () => {
  assert.match(apiRoute, /v1\.shipments\.delivery-proof\.certificate/);
});

check('4. delivery_certificates database migration exists', () => {
  assert.match(migration, /delivery_certificates/);
  assert.match(migration, /hash/);
  assert.match(migration, /file_path/);
});

if (process.exitCode) {
  console.error(`\nDispatcher POD-05 verification failed after ${passed} checks.`);
} else {
  console.log(`\nDispatcher POD-05 verification passed (${passed} checks).`);
}
