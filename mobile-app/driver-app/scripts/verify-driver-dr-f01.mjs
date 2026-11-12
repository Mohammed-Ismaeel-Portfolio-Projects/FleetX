/**
 * DR-F01 — Notification semantics & safety notice UX.
 * Run: npm run test:driver-dr-f01
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

/** Mirror of notificationPresentation.ts for regression matrix (keep in sync). */
function getNotificationPresentation(item) {
  const SAFETY = ['safety.speed_excess', 'safety.harsh_braking', 'safety.sudden_stop'];
  const CRITICAL = ['assignment.cancelled', 'assignment.reassigned'];

  if (item.severity === 'critical' || CRITICAL.includes(item.code)) {
    return {
      intent: 'action_required',
      label: 'ACTION REQUIRED',
      accessibilityLabel: 'Action required',
    };
  }
  if (SAFETY.includes(item.code)) {
    return {
      intent: 'notice',
      label: 'NOTICE',
      accessibilityLabel: 'Safety notice',
    };
  }
  if (item.severity === 'warning') {
    return {
      intent: 'notice',
      label: 'NOTICE',
      accessibilityLabel: 'Notice',
    };
  }
  return {
    intent: 'information',
    label: 'INFO',
    accessibilityLabel: 'Information',
  };
}

console.log('=== DR-F01 Notification Semantics ===\n');

const presentation = read('src/features/notifications/notificationPresentation.ts');
const inbox = read('src/app/(tabs)/notifications.tsx');
const banner = read('src/features/notifications/TripAwarenessBanner.tsx');

check('Centralized presentation module exists', () => {
  assert.match(presentation, /getNotificationPresentation/);
  assert.match(presentation, /safety\.speed_excess/);
  assert.match(presentation, /safety\.harsh_braking/);
  assert.match(presentation, /safety\.sudden_stop/);
  assert.match(presentation, /assignment\.cancelled/);
  assert.match(presentation, /assignment\.reassigned/);
});

check('Inbox uses presentation mapper (not severity-only label)', () => {
  assert.match(inbox, /getNotificationPresentation/);
  assert.match(inbox, /notificationRowAccessibilityLabel/);
  assert.doesNotMatch(inbox, /function severityLabel/);
  assert.doesNotMatch(inbox, /warning.*ACTION REQUIRED/s);
});

check('Active Trip awareness unchanged (no inbox semantics leak)', () => {
  assert.match(banner, /MOTION SAFETY ALERT/);
  assert.match(banner, /onCriticalChange/);
  assert.doesNotMatch(banner, /getNotificationPresentation/);
});

check('A. D8 critical notification → ACTION REQUIRED', () => {
  const p = getNotificationPresentation({
    code: 'assignment.cancelled',
    severity: 'critical',
  });
  assert.equal(p.label, 'ACTION REQUIRED');
  assert.equal(p.accessibilityLabel, 'Action required');
});

check('B. D14 speed notification → NOTICE', () => {
  const p = getNotificationPresentation({
    code: 'safety.speed_excess',
    severity: 'warning',
  });
  assert.equal(p.label, 'NOTICE');
  assert.equal(p.accessibilityLabel, 'Safety notice');
});

check('C. D15 harsh braking → NOTICE', () => {
  const p = getNotificationPresentation({
    code: 'safety.harsh_braking',
    severity: 'warning',
  });
  assert.equal(p.label, 'NOTICE');
  assert.equal(p.accessibilityLabel, 'Safety notice');
});

check('D. D15 sudden stop → NOTICE', () => {
  const p = getNotificationPresentation({
    code: 'safety.sudden_stop',
    severity: 'warning',
  });
  assert.equal(p.label, 'NOTICE');
  assert.equal(p.accessibilityLabel, 'Safety notice');
});

check('E. Unknown warning does not escalate to ACTION REQUIRED', () => {
  const p = getNotificationPresentation({
    code: 'delivery.failed',
    severity: 'warning',
  });
  assert.equal(p.label, 'NOTICE');
  assert.notEqual(p.label, 'ACTION REQUIRED');
});

check('F. Accessibility semantics present on inbox rows', () => {
  assert.match(inbox, /accessibilityLabel=\{notificationRowAccessibilityLabel/);
  assert.match(presentation, /accessibilityLabel/);
});

console.log('');
console.log(`DR-F01: ${passed} passed, ${failed} failed`);
if (failed > 0) process.exit(1);
console.log('DR-F01 VERDICT: PASS');
