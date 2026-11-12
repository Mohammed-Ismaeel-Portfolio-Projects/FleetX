/**
 * C2.4 Honest Home — focused static verification.
 * Run: node ./scripts/verify-c24-home.mjs
 *
 * Ensures Home source does not present mock operational data as live,
 * and still wires identity + Messages CTAs.
 * C3.3: Home is a live client overview (real APIs). Anti-mock tokens remain forbidden.
 */

import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const homePath = path.join(__dirname, '../src/app/(tabs)/index.tsx');
const source = fs.readFileSync(homePath, 'utf8');

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

const forbidden = [
  'FX-4920',
  'FX-1620',
  'FX-3844',
  'Robert Chen',
  '14:45',
  '75%',
  '$42.5k',
  'Today Overview',
  'Live Activity',
  'FleetX AI Recommendation',
  'Track Fleet',
  'AI Estimator',
  'Chicago → Seattle',
  'Miami → Atlanta',
  'badgeIndicator',
  '/dashboard',
  'apiClient',
  'fetch(',
];

for (const token of forbidden) {
  check(`no forbidden mock/ops token: ${token}`, () => {
    assert.equal(source.includes(token), false, `Home still contains: ${token}`);
  });
}

check('uses useAuth for identity', () => {
  assert.match(source, /useAuth/);
  assert.match(source, /identityLinked/);
  assert.match(source, /home-identity/);
  assert.match(source, /home-greeting/);
});

check('unlinked banner present', () => {
  assert.match(source, /home-unlinked-banner/);
  assert.match(source, /Company not linked/);
});

check('Messages CTAs present and protected conditions', () => {
  assert.match(source, /home-open-messages/);
  assert.match(source, /home-messages-card/);
  assert.match(source, /router\.push\('\/messages'\)/);
  assert.match(source, /identityLinked/);
  assert.match(source, /roleOk/);
});

check('Home uses real identity + live overview (C3.3); no mock ops placeholders', () => {
  assert.match(source, /listClientShipments/);
  assert.match(source, /home-active-shipments|home-empty/);
  assert.equal(source.includes('home-shipments-unavailable'), false);
  assert.equal(source.includes('home-attention-unavailable'), false);
  assert.equal(source.includes('home-notifications-unavailable'), false);
});

check('does not route Home into mock shipment-detail or tracking surfaces', () => {
  assert.match(source, /router\.push\('\/notifications'\)/);
  assert.match(source, /home-notifications-header/);
  assert.equal(source.includes("router.push('/shipment-details')"), false);
  assert.equal(source.includes("router.push('/tracking')"), false);
  assert.equal(source.includes("router.push('/shipments')"), false);
});

check('primary destinations remain tab/header entry points (no debug QA strip; C3.10)', () => {
  assert.match(source, /home-shipments-summary|Open shipments|View Shipments|openShipments/);
  assert.match(source, /home-requests-summary|openRequests|View Requests/);
  assert.match(source, /openMessages|Open messages/);
  assert.equal(source.includes('home-qa-shipments'), false);
  assert.equal(source.includes('home-qa-requests'), false);
  assert.equal(source.includes('home-qa-messages'), false);
  assert.equal(source.includes('home-qa-tracking'), false);
  assert.equal(source.includes('home-qa-create'), false);
});

check('no fake notification unread badge', () => {
  assert.equal(source.includes('badgeIndicator'), false);
  assert.equal(/\bunread\s*\d+/i.test(source), false);
});

if (!process.exitCode) {
  console.log(`\nC2.4 home checks: ${passed} passed`);
}
