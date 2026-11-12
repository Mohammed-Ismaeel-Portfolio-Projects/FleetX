/**
 * C5.8 — Client Security Regression Gate
 *
 * Combines:
 *   A) Static Client boundary / allowlist checks
 *   B) Orchestrated Client security-relevant verifiers
 *   C) Backend tenancy/IDOR Feature suites (when Laravel available)
 *
 * Run: npm run test:c58-client-security
 *      node ./scripts/verify-c58-client-security.mjs
 *
 * Env:
 *   C58_SKIP_BACKEND=1  — skip PHPUnit (Client static + npm suite only)
 */

import assert from 'node:assert/strict';
import { spawnSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const clientRoot = path.join(__dirname, '..');
const repoRoot = path.join(clientRoot, '..', '..');
const backendRoot = path.join(repoRoot, 'backend');

function readClient(rel) {
  return fs.readFileSync(path.join(clientRoot, rel), 'utf8');
}

function walkTsFiles(dir, out = []) {
  if (!fs.existsSync(dir)) return out;
  for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
    const full = path.join(dir, entry.name);
    if (entry.isDirectory()) {
      if (entry.name === 'node_modules' || entry.name === '.git') continue;
      walkTsFiles(full, out);
    } else if (/\.(ts|tsx|mjs|js)$/.test(entry.name)) {
      out.push(full);
    }
  }
  return out;
}

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
    console.error(e);
  }
}

function runNpm(script) {
  const result = spawnSync('npm', ['run', script], {
    cwd: clientRoot,
    encoding: 'utf8',
    shell: true,
  });
  if (result.status !== 0) {
    const err = new Error(`npm run ${script} failed (exit ${result.status})`);
    err.stdout = result.stdout;
    err.stderr = result.stderr;
    throw err;
  }
}

function runPhpUnitFilter(filter) {
  const result = spawnSync('php', ['artisan', 'test', `--filter=${filter}`], {
    cwd: backendRoot,
    encoding: 'utf8',
    shell: true,
  });
  if (result.status !== 0) {
    const err = new Error(`php artisan test --filter=${filter} failed (exit ${result.status})`);
    err.stdout = result.stdout;
    err.stderr = result.stderr;
    throw err;
  }
}

console.log('=== C5.8 A — Static Client boundary ===\n');

const srcRoot = path.join(clientRoot, 'src');
const srcFiles = walkTsFiles(srcRoot);
const srcBlob = srcFiles.map((f) => fs.readFileSync(f, 'utf8')).join('\n');

const shipmentsApi = readClient('src/features/shipments/shipmentsApi.ts');
const requestsApi = readClient('src/features/requests/requestsApi.ts');
const notificationsApi = readClient('src/features/notifications/notificationsApi.ts');
const messagingApi = readClient('src/features/messaging/messagingApi.ts');
const trackingMap = readClient('src/features/shipments/shipmentTrackingMap.ts');
const trackingUi = readClient('src/features/shipments/ShipmentTrackingMap.tsx');
const trackingScreen = readClient('src/app/shipment-tracking/[id].tsx');
const pkg = readClient('package.json');

check('Client shipment APIs use /client/shipments only', () => {
  assert.match(shipmentsApi, /\/client\/shipments/);
  assert.match(shipmentsApi, /\/client\/shipments\/\$\{[^}]+\}/);
  assert.match(shipmentsApi, /\/client\/shipments\/\$\{[^}]+\}\/tracking/);
  assert.doesNotMatch(shipmentsApi, /['"`]\/shipments['"`]|['"`]\/shipments\//);
  assert.doesNotMatch(shipmentsApi, /tracking-logs|tracking-states|live-fleet|GetShipmentRoute/i);
});

check('Client request APIs use /client/shipment-requests only', () => {
  assert.match(requestsApi, /\/client\/shipment-requests/);
  assert.doesNotMatch(requestsApi, /['"`]\/shipment-requests['"`]/);
  // dispatcher_notes may appear only on stripTenancy delete lines — never mapped/consumed.
  const dispatcherNotesLines = requestsApi
    .split('\n')
    .filter((line) => line.includes('dispatcher_notes'));
  assert.ok(dispatcherNotesLines.length >= 1, 'expected stripTenancy to delete dispatcher_notes');
  for (const line of dispatcherNotesLines) {
    assert.match(line, /delete\s*\(/, `dispatcher_notes must only be deleted, got: ${line.trim()}`);
  }
  assert.match(requestsApi, /review_feedback|mapClientReviewFeedback/);
});

check('Client notification APIs use /client/notifications only', () => {
  assert.match(notificationsApi, /\/client\/notifications/);
  // Ownership params may appear only in prohibit/delete lists — never assigned into query.
  assert.match(notificationsApi, /Must never send user_id|never send ownership/i);
  assert.match(notificationsApi, /delete\s*\([^)]*user_id/);
  assert.match(notificationsApi, /delete\s*\([^)]*company_id/);
  assert.doesNotMatch(notificationsApi, /query\.(user_id|company_id|customer_company_id|owner_id|recipient_id)\s*=/);
  assert.doesNotMatch(notificationsApi, /params\.(user_id|company_id)\s*=/);
});

check('Messaging is lookup-only (no POST /conversations create)', () => {
  assert.match(messagingApi, /\/conversations/);
  assert.doesNotMatch(messagingApi, /apiClient\.post\([^\n]*\/conversations['"`]\s*[,)]/);
  assert.doesNotMatch(srcBlob, /openConversation/);
  assert.doesNotMatch(srcBlob, /POST\s*\/conversations/);
});

check('Client src does not call Ops tracking / live-fleet / route-engine APIs', () => {
  assert.doesNotMatch(srcBlob, /\/tracking-logs|\/tracking-states|live-fleet|\/shipments\/\$\{[^}]+\}\/route/);
  assert.doesNotMatch(srcBlob, /GetShipmentRouteAction|graphhopper/i);
});

check('Tracking UI/helpers forbid driver/vehicle/ETA/ops leakage', () => {
  assert.doesNotMatch(trackingMap, /\bETA\b|driver_id|vehicle_id|live-fleet|encoded polyline/i);
  assert.doesNotMatch(trackingUi, /\bETA\b|driver_id|vehicle_id|plate|dispatcher/i);
  assert.doesNotMatch(trackingScreen, /\bETA\b|driver_id|vehicle_id|toFixed\(/);
  assert.match(trackingScreen, /getClientShipmentTracking/);
  assert.match(shipmentsApi, /mapClientShipmentTracking|mapClientLastKnownPosition/);
});

check('Auth refresh / session fail-closed architecture still present (C5.2)', () => {
  const refresh = readClient('src/lib/auth/authRefresh.ts');
  const client = readClient('src/lib/api/client.ts');
  assert.match(refresh, /refreshAccessToken|single-flight|session/i);
  assert.match(client, /401|refresh|emitSessionInvalid|_authRetry|skipAuthRefresh/);
  assert.doesNotMatch(refresh, /console\.log\([^\)]*token/i);
});

check('Tenancy probe params are not sent from Client APIs', () => {
  assert.doesNotMatch(shipmentsApi, /customer_company_id\s*:/);
  assert.doesNotMatch(requestsApi, /customer_company_id\s*:/);
  assert.match(requestsApi, /stripTenancy|prohibited|Must never send company/i);
});

console.log('\n=== C5.8 B — Orchestrated Client security verifiers ===\n');

const clientSecurityScripts = [
  'test:c23-auth',
  'test:c52-client-session-durability',
  'test:c51-client-tracking-position',
  'test:c53-client-review-feedback',
  'test:c54-client-filtering',
  'test:c55c3-client-tracking-mapper',
  'test:c55c41-client-tracking-map-hardening',
  'test:c55c43-client-tracking-screen-integration',
  'test:c27d2-client-tracking-hardening',
  'test:c28d2-client-notifications-hardening',
  'test:c56-client-ux-consistency',
];

for (const script of clientSecurityScripts) {
  check(`orchestrate ${script}`, () => {
    console.log(`  → npm run ${script}`);
    runNpm(script);
  });
}

const skipBackend = process.env.C58_SKIP_BACKEND === '1';
const artisanExists = fs.existsSync(path.join(backendRoot, 'artisan'));

console.log('\n=== C5.8 C — Backend tenancy / IDOR / forbidden-field suites ===\n');

if (skipBackend) {
  console.log('SKIP  Backend PHPUnit (C58_SKIP_BACKEND=1)\n');
} else if (!artisanExists) {
  failed += 1;
  console.error('FAIL  Backend artisan not found — cannot run tenancy Feature suites');
} else {
  const phpFilters = [
    'ClientShipmentTrackingTest',
    'ClientShipmentListTest',
    'ClientShipmentShowTest',
    'ClientShipmentRequestListTest',
    'ClientShipmentRequestShowTest',
    'ClientShipmentRequestReviewFeedbackTest',
    'ClientShipmentRequestCreateTest',
    'ClientShipmentRequestUpdateTest',
    'ClientShipmentRequestSubmitTest',
    'ClientShipmentRequestResubmitTest',
    'ClientShipmentRequestLinkedShipmentTest',
    'ClientNotificationTest',
    'MessageClientNotificationTest',
    'MessagingBackendTest',
    'IdentityLinkageTest',
  ];

  for (const filter of phpFilters) {
    check(`phpunit ${filter}`, () => {
      console.log(`  → php artisan test --filter=${filter}`);
      runPhpUnitFilter(filter);
    });
  }
}

console.log('');
console.log(`C5.8 Security Regression Gate: ${passed} passed, ${failed} failed`);
if (failed > 0) process.exit(1);
console.log('\nC5.8 VERDICT: PASS');
