/**
 * C2.5 Honest Navigation Shell — focused static verification.
 * Run: node ./scripts/verify-c25-shell.mjs
 */

import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const root = path.join(__dirname, '..');

function read(rel) {
  return fs.readFileSync(path.join(root, rel), 'utf8');
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

const tabsLayout = read('src/app/(tabs)/_layout.tsx');
const home = read('src/app/(tabs)/index.tsx');
const shipments = read('src/app/(tabs)/shipments.tsx');
const tracking = read('src/app/(tabs)/tracking.tsx');
const messages = read('src/app/(tabs)/messages.tsx');
const profile = read('src/app/(tabs)/profile.tsx');
const notifications = read('src/app/notifications.tsx');
const shipmentDetails = read('src/app/shipment-details/[id].tsx');
const unavailable = read('src/components/unavailable-domain.tsx');

const shellFiles = {
  shipments,
  tracking,
  notifications,
  unavailable,
};

check('five primary tabs: Home, Requests, Shipments, Messages, Profile', () => {
  assert.match(tabsLayout, /name="index"/);
  assert.match(tabsLayout, /name="requests"/);
  assert.match(tabsLayout, /name="shipments"/);
  assert.match(tabsLayout, /name="messages"/);
  assert.match(tabsLayout, /name="profile"/);
  assert.match(tabsLayout, /tabBarLabel: 'Home'/);
  assert.match(tabsLayout, /tabBarLabel: 'Requests'/);
  assert.match(tabsLayout, /tabBarLabel: 'Shipments'/);
  assert.match(tabsLayout, /tabBarLabel: 'Messages'/);
  assert.match(tabsLayout, /tabBarLabel: 'Profile'/);
  assert.equal(/tabBarLabel: 'Tracking'/.test(tabsLayout), false);
  assert.match(tabsLayout, /name="tracking"/);
  assert.match(tabsLayout, /href:\s*null/);
});

check('Home is a live overview hub (C3.3); notifications entry is real; no tracking tab route', () => {
  assert.match(home, /router\.push\('\/notifications'\)/);
  assert.match(home, /listClientShipments/);
  assert.equal(home.includes("router.push('/tracking')"), false);
  assert.equal(home.includes("router.push('/shipments')"), false);
  assert.match(home, /home-open-messages/);
  assert.equal(home.includes('home-shipments-unavailable'), false);
});

check('Messages screen still present (protected)', () => {
  assert.match(messages, /listConversations/);
  assert.match(messages, /dispatcher_customer/);
  assert.match(messages, /messages-screen|roleOk|identityLinked/);
});

check('Shipments tab is Unavailable (not empty list)', () => {
  assert.match(shipments, /UnavailableDomainScreen|shipments-unavailable/);
  assert.match(shipments, /Shipments/);
  assert.equal(shipments.includes('apiClient'), false);
  assert.equal(shipments.includes("'/api"), false);
  assert.equal(/FX-\d+/.test(shipments), false);
  assert.equal(shipments.includes('SafeMap'), false);
});

check('Tracking tab is not a live tracker (C3.2 redirect; no map/GPS/ETA)', () => {
  assert.match(tracking, /Redirect/);
  assert.equal(tracking.includes('SafeMap'), false);
  assert.equal(tracking.includes('Robert Chen'), false);
  assert.equal(tracking.includes('FX-4920'), false);
  assert.equal(tracking.includes('14:45'), false);
  assert.equal(tracking.includes('live-fleet'), false);
  assert.equal(tracking.includes('apiClient'), false);
});

check('Notifications inbox uses client API (no mock archive cards)', () => {
  assert.match(notifications, /listClientNotifications|notificationsApi/);
  assert.equal(notifications.includes('AI DAILY SUMMARY'), false);
  assert.equal(notifications.includes('badgeIndicator'), false);
  assert.equal(notifications.includes('FILTER_PILLS'), false);
  assert.equal(notifications.includes('unreadBadge'), false);
  assert.equal(notifications.includes('/issues'), false);
});

check('Shipment details uses client show API (no mock)', () => {
  assert.match(shipmentDetails, /getClientShipment/);
  assert.match(shipmentDetails, /\/client\/shipments|getClientShipment/);
  assert.equal(shipmentDetails.includes('SafeMap'), false);
  assert.equal(/FX-\d+/.test(shipmentDetails), false);
  assert.equal(shipmentDetails.includes("'/shipments/"), false);
  assert.equal(/\bTrack\b/.test(shipmentDetails), false);
  assert.equal(shipmentDetails.includes('Edit'), false);
});

check('Unavailable helper distinguishes UNAVAILABLE from EMPTY', () => {
  assert.match(unavailable, /not an empty list/i);
  assert.match(unavailable, /FleetX Support/);
  assert.equal(unavailable.includes('Message dispatcher'), false);
  assert.match(unavailable, /identityLinked/);
  assert.match(unavailable, /router\.push\('\/messages'\)/);
});

for (const [name, src] of Object.entries(shellFiles)) {
  check(`no admin/dispatcher dashboard calls in ${name}`, () => {
    assert.equal(src.includes('/dashboard'), false);
    assert.equal(src.includes('live-fleet'), false);
    assert.equal(src.includes('apiClient'), false);
  });
}

check('Profile tab file still exists (not removed from IA)', () => {
  assert.ok(profile.length > 100);
  assert.match(profile, /logout|Logout/i);
});

const forbiddenAcrossShell = [
  'FX-4920',
  'Robert Chen',
  'LIVE ETA',
  'AI DAILY SUMMARY',
  'Central Hub, Singapore',
];

for (const token of forbiddenAcrossShell) {
  check(`shell domain screens lack mock token: ${token}`, () => {
    for (const [name, src] of Object.entries({
      shipments,
      tracking,
      notifications,
      shipmentDetails,
    })) {
      assert.equal(src.includes(token), false, `${name} still contains ${token}`);
    }
  });
}

if (!process.exitCode) {
  console.log(`\nC2.5 shell checks: ${passed} passed`);
}
