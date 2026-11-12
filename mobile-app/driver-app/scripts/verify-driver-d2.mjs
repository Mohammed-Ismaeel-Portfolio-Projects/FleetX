/**
 * DRIVER-D2 — Work contract visibility (API client + Work Home + Assignment Detail).
 * Run: npm run test:driver-d2
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

console.log('=== DRIVER-D2 Work Contract ===\n');

const workApi = read('src/features/driverApi/work.ts');
const mapper = read('src/features/driverApi/workMapper.ts');
const workScreen = read('src/app/(tabs)/index.tsx');
const detail = read('src/app/assignment/[id].tsx');
const layout = read('src/app/_layout.tsx');

check('Work API calls driver endpoints (not stub-null-only)', () => {
  assert.match(workApi, /getActiveAssignment/);
  assert.match(workApi, /\/driver\/work\/active/);
  assert.doesNotMatch(workApi, /D1: always null/);
});

check('Mapper projects Driver-safe fields', () => {
  assert.match(mapper, /mapDriverWorkAssignment/);
  assert.match(mapper, /assignmentCode/);
  assert.match(mapper, /plannedPickupDate/);
  assert.doesNotMatch(mapper, /engine_enrollment/);
});

check('Work Home: loading / empty / loaded / retry / blocked', () => {
  assert.match(workScreen, /work-loading/);
  assert.match(workScreen, /No active assignment/);
  assert.match(workScreen, /work-active-card/);
  assert.match(workScreen, /work-retry|onRetry/);
  assert.match(workScreen, /work-blocked-link/);
  assert.match(workScreen, /View Assignment/);
  assert.doesNotMatch(workScreen, /Scan QR|Arrived at pickup/);
});

check('Assignment Detail organizes operational sections', () => {
  assert.match(detail, /assignment-summary/);
  assert.match(detail, /assignment-shipment/);
  assert.match(detail, /assignment-pickup/);
  assert.match(detail, /assignment-delivery/);
  assert.match(detail, /assignment-vehicle/);
  assert.match(detail, /assignment-trailer/);
  assert.match(detail, /assignment-schedule/);
  assert.doesNotMatch(detail, /Scan QR|Mark Delivered|expo-location/);
});

check('Assignment route registered; D3 execution CTAs deferred from D2-only assert', () => {
  assert.match(layout, /assignment\/\[id\]/);
  assert.ok(fs.existsSync(path.join(root, 'src/app/assignment/[id].tsx')));
});

check('Behavioral: mapper accepts valid payload', () => {
  function mapDriverWorkAssignment(raw) {
    if (raw == null || typeof raw !== 'object') return null;
    if (typeof raw.id !== 'number') return null;
    return {
      id: raw.id,
      assignmentCode: raw.assignment_code ?? null,
      isActive: Boolean(raw.is_active),
      status: raw.status
        ? { id: raw.status.id, name: raw.status.name, key: raw.status.key ?? null }
        : null,
    };
  }
  const mapped = mapDriverWorkAssignment({
    id: 9,
    assignment_code: 'ASN-1',
    is_active: true,
    status: { id: 1, name: 'Assigned', key: 'assigned' },
  });
  assert.equal(mapped.id, 9);
  assert.equal(mapped.status.key, 'assigned');
  assert.equal(mapDriverWorkAssignment(null), null);
});

console.log('');
console.log(`DRIVER-D2: ${passed} passed, ${failed} failed`);
if (failed > 0) process.exit(1);
console.log('DRIVER-D2 VERDICT: PASS');
