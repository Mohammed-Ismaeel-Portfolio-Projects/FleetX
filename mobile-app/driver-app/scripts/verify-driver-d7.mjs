/**
 * DRIVER-D7 — Outcome, POD, exceptions, completion.
 * Run: npm run test:driver-d7
 */

import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const root = path.join(__dirname, '..');

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

console.log('=== DRIVER-D7 Outcome / POD / Issues ===\n');

const workApi = read('src/features/driverApi/work.ts');
const phase = read('src/features/driverApi/executionPhase.ts');
const trip = read('src/app/active-trip/[id].tsx');
const pod = read('src/app/pod/[id].tsx');
const complete = read('src/app/delivery-complete/[id].tsx');
const issueSheet = read('src/features/pod/ReportIssueSheet.tsx');
const signature = read('src/features/pod/SignaturePad.tsx');
const layout = read('src/app/_layout.tsx');
const pkg = JSON.parse(read('package.json'));

check('Dependencies: expo-image-picker + expo-file-system present', () => {
  assert.ok(pkg.dependencies['expo-image-picker']);
  assert.ok(pkg.dependencies['expo-file-system']);
});

check('API: driver POD / complete / issues / fail-delivery / outcome only', () => {
  assert.match(workApi, /\/driver\/assignments\/\$\{assignmentId\}\/pod/);
  assert.match(workApi, /\/driver\/assignments\/\$\{assignmentId\}\/complete/);
  assert.match(workApi, /\/driver\/assignments\/\$\{assignmentId\}\/issues/);
  assert.match(workApi, /\/driver\/assignments\/\$\{assignmentId\}\/fail-delivery/);
  assert.match(workApi, /\/driver\/assignments\/\$\{assignmentId\}\/outcome/);
  assert.doesNotMatch(workApi, /['"`]\/issues['"`]/);
  assert.doesNotMatch(workApi, /['"`]\/assignments\/[^d]/);
  assert.doesNotMatch(workApi, /\/client\//);
});

check('Phase helpers: POD + complete CTAs after delivery_verified', () => {
  assert.match(phase, /pod_submitted/);
  assert.match(phase, /kind: 'pod'/);
  assert.match(phase, /kind: 'complete'/);
  assert.match(phase, /Capture proof of delivery/);
  assert.match(phase, /Finish trip/);
});

check('Active Trip: POD / complete / report issue / fail delivery hooks', () => {
  assert.match(trip, /\/pod\//);
  assert.match(trip, /completeAssignment/);
  assert.match(trip, /active-trip-report-issue/);
  assert.match(trip, /ReportIssueSheet/);
  assert.match(trip, /delivery-complete/);
});

check('POD screen: photo, signature, review, submit, success/error', () => {
  assert.match(pod, /pod-screen/);
  assert.match(pod, /pod-capture-photo/);
  assert.match(pod, /SignaturePad/);
  assert.match(pod, /pod-review/);
  assert.match(pod, /pod-submit/);
  assert.match(pod, /pod-success/);
  assert.match(pod, /pod-error|pod-submitting/);
  assert.match(pod, /submitPod/);
  assert.doesNotMatch(pod, /Mark Delivered locally|fake success/i);
});

check('Signature abstraction exists (testable without hardware)', () => {
  assert.match(signature, /signature-pad/);
  assert.match(signature, /SignatureStroke/);
  assert.match(signature, /onChange/);
});

check('Issue sheet: reasons, other description, stationary confirm', () => {
  assert.match(issueSheet, /report-issue-sheet/);
  assert.match(issueSheet, /traffic_light/);
  assert.match(issueSheet, /issue-stationary-confirm/);
  assert.match(issueSheet, /other/);
  assert.match(issueSheet, /failDelivery|reportIssue/);
});

check('Completion screen: server-confirmed return to Work', () => {
  assert.match(complete, /delivery-complete-screen/);
  assert.match(complete, /Delivery completed/);
  assert.match(complete, /delivery-complete-work/);
  assert.match(layout, /pod\/\[id\]/);
  assert.match(layout, /delivery-complete\/\[id\]/);
});

check('No Ops issue / assignment mutation shortcuts', () => {
  const src = workApi + trip + pod + issueSheet;
  assert.doesNotMatch(src, /\/api\/v1\/issues/);
  assert.doesNotMatch(src, /putJson.*\/assignments/);
  assert.doesNotMatch(src, /ChangeAssignmentStatus/);
});

console.log('');
console.log(`DRIVER-D7: ${passed} passed, ${failed} failed`);
if (failed > 0) process.exit(1);
console.log('DRIVER-D7 VERDICT: PASS');
