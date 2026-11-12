/**
 * DRIVER-D10 — Final E2E + release gate orchestrator.
 * Run: npm run test:driver-d10
 */

import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const root = path.join(__dirname, '..');
const repoRoot = path.join(root, '..', '..');
const docs = path.join(repoRoot, 'docs', 'driver');

let passed = 0;
let failed = 0;

function check(name, fn) {
  try {
    fn();
    passed += 1;
    console.log(`PASS  ${name}`);
  } catch (e) {
    failed += 1;
    console.error(`FAIL  ${name}`);
    console.error(e?.message || e);
  }
}

function read(rel) {
  return fs.readFileSync(path.join(root, rel), 'utf8');
}

console.log('=== DRIVER-D10 Final Gate ===\n');

const requiredDocs = [
  'D10_DISCOVERY.md',
  'D10_SECURITY_REVIEW.md',
  'D10_E2E_REPORT.md',
  'D10_RELEASE_REPORT.md',
  'D10_PRODUCT_GAP_AUDIT.md',
  'D10_IMPLEMENTATION_REPORT.md',
];

for (const doc of requiredDocs) {
  check(`Doc exists: ${doc}`, () => {
    assert.ok(fs.existsSync(path.join(docs, doc)), `Missing ${doc}`);
  });
}

check('Backend D10 E2E lifecycle test file exists', () => {
  assert.ok(
    fs.existsSync(
      path.join(repoRoot, 'backend', 'tests', 'Feature', 'Driver', 'DriverE2ELifecycleTest.php')
    )
  );
});

check('Backend D10 authorization matrix test exists', () => {
  assert.ok(
    fs.existsSync(
      path.join(repoRoot, 'backend', 'tests', 'Feature', 'Driver', 'DriverAuthorizationMatrixTest.php')
    )
  );
});

check('Backend D10 QR security test exists', () => {
  assert.ok(
    fs.existsSync(
      path.join(repoRoot, 'backend', 'tests', 'Feature', 'Driver', 'DriverD10QrSecurityTest.php')
    )
  );
});

check('Release verifier script exists', () => {
  assert.ok(fs.existsSync(path.join(root, 'scripts', 'verify-driver-release.mjs')));
});

check('Canonical journey screens + API client present', () => {
  const screens = [
    'src/app/login.tsx',
    'src/app/(tabs)/index.tsx',
    'src/app/assignment/[id].tsx',
    'src/app/active-trip/[id].tsx',
    'src/app/verify-trip/[id].tsx',
    'src/app/pod/[id].tsx',
    'src/app/delivery-complete/[id].tsx',
    'src/app/(tabs)/notifications.tsx',
    'src/app/conversation/[id].tsx',
  ];
  for (const s of screens) {
    assert.ok(fs.existsSync(path.join(root, s)), `Missing ${s}`);
  }
  const work = read('src/features/driverApi/work.ts');
  assert.match(work, /acknowledgeAssignment/);
  assert.match(work, /verifyAssignment/);
  assert.match(work, /submitPod/);
  assert.match(work, /completeAssignment/);
});

check('D9 hardening utilities still present', () => {
  assert.ok(fs.existsSync(path.join(root, 'src/lib/safety/drivingMotion.ts')));
  assert.ok(fs.existsSync(path.join(root, 'src/lib/driver/assignmentState.ts')));
});

check('No Client/Ops API paths in Driver src', () => {
  const trip = read('src/app/active-trip/[id].tsx');
  const work = read('src/features/driverApi/work.ts');
  const src = trip + work;
  assert.doesNotMatch(src, /['"`]\/client\//);
  assert.doesNotMatch(src, /['"`]\/assignments(?:\/|['"`?])/);
});

check('Package scripts: d10-final chain', () => {
  const pkg = JSON.parse(read('package.json'));
  assert.ok(pkg.scripts['test:driver-d10']);
  assert.ok(pkg.scripts['test:driver-release']);
  assert.ok(pkg.scripts['test:d10-final']);
});

console.log('');
console.log(`DRIVER-D10: ${passed} passed, ${failed} failed`);
if (failed > 0) process.exit(1);
console.log('DRIVER-D10 VERDICT: PASS');
