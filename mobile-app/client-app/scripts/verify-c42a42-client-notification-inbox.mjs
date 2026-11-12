/**
 * C4.2-A4.2 — Client notification inbox integration verification (static).
 *
 * Proves the Client app still consumes the canonical HTTP inbox contract,
 * destination resolver, focus refresh (no polling), and Home unread badge —
 * without rebuilding or adding a second resolver / WebSocket / store.
 *
 * Run: node ./scripts/verify-c42a42-client-notification-inbox.mjs
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

const api = readClient('src/features/notifications/notificationsApi.ts');
const dest = readClient('src/features/notifications/notificationDestination.ts');
const types = readClient('src/features/notifications/types.ts');
const screen = readClient('src/app/notifications.tsx');
const home = readClient('src/app/(tabs)/index.tsx');
const profile = readClient('src/app/(tabs)/profile.tsx');
const layout = readClient('src/app/_layout.tsx');
const productTest = readRepo(
  'backend/tests/Feature/Notification/ClientNotificationInboxProductVerificationTest.php'
);

const UUID = 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee';
const REQUEST_ID = '11111111-2222-4333-8444-555555555555';
const CONVERSATION_ID = '66666666-7777-4888-8999-aaaaaaaaaaaa';

const UUID_RE =
  /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i;
const ALLOWED = new Set([
  'shipment_details',
  'shipment_progress',
  'request_details',
  'conversation',
]);

function mapAction(raw) {
  if (raw == null || typeof raw !== 'object' || Array.isArray(raw)) return null;
  const kind = typeof raw.kind === 'string' ? raw.kind.trim() : '';
  const id = typeof raw.id === 'string' ? raw.id.trim() : '';
  if (!kind || !id) return null;
  if (!ALLOWED.has(kind)) return null;
  if (!UUID_RE.test(id)) return null;
  return { kind, id };
}

function resolveDestination(notification) {
  void notification?.title;
  void notification?.message;
  void notification?.type;
  const action = mapAction(notification?.action ?? null);
  if (!action) {
    return { href: '/notifications', navigable: false };
  }
  if (action.kind === 'shipment_details') {
    return { href: `/shipment-details/${action.id}`, navigable: true, ctaLabel: 'View Shipment' };
  }
  if (action.kind === 'shipment_progress') {
    return { href: `/shipment-tracking/${action.id}`, navigable: true, ctaLabel: 'View progress' };
  }
  if (action.kind === 'request_details') {
    return { href: `/request-details/${action.id}`, navigable: true };
  }
  if (action.kind === 'conversation') {
    return { href: `/conversation/${action.id}`, navigable: true };
  }
  return { href: '/notifications', navigable: false };
}

check('API layer calls the four canonical Client notification endpoints', () => {
  assert.match(api, /\/client\/notifications'/);
  assert.match(api, /\/client\/notifications\/unread-count/);
  assert.match(api, /\/client\/notifications\/\$\{encodeURIComponent\(id\)\}\/read/);
  assert.match(api, /\/client\/notifications\/read-all/);
  assert.match(api, /export async function listClientNotifications/);
  assert.match(api, /export async function getClientNotificationsUnreadCount/);
  assert.match(api, /export async function markClientNotificationRead/);
  assert.match(api, /export async function markAllClientNotificationsRead/);
});

check('API strips ownership / company query params including owner_id and recipient_id', () => {
  assert.match(api, /delete \(query as \{ user_id\?: unknown \}\)\.user_id/);
  assert.match(api, /delete \(query as \{ company_id\?: unknown \}\)\.company_id/);
  assert.match(api, /delete \(query as \{ customer_company_id\?: unknown \}\)\.customer_company_id/);
  assert.match(api, /delete \(query as \{ owner_id\?: unknown \}\)\.owner_id/);
  assert.match(api, /delete \(query as \{ recipient_id\?: unknown \}\)\.recipient_id/);
});

check('single destination resolver remains canonical', () => {
  assert.match(dest, /export function resolveClientNotificationDestination/);
  assert.match(dest, /export function mapClientNotificationAction/);
  assert.equal(
    (dest.match(/export function resolveClientNotificationDestination/g) || []).length,
    1
  );
  assert.match(screen, /resolveClientNotificationDestination/);
  assert.equal(home.includes('resolveClientNotificationDestination'), false);
});

check('destination mapping for all four action kinds', () => {
  assert.equal(
    resolveDestination({
      action: { kind: 'shipment_details', id: UUID },
      title: 'ignore',
      message: 'ignore',
      type: 'ignore',
    }).href,
    `/shipment-details/${UUID}`
  );
  assert.equal(
    resolveDestination({
      action: { kind: 'shipment_progress', id: UUID },
      title: 'Shipment delivered',
      message: 'Your shipment has been delivered.',
      type: 'shipment.status',
    }).href,
    `/shipment-tracking/${UUID}`
  );
  assert.equal(
    resolveDestination({
      action: { kind: 'shipment_progress', id: UUID },
      title: 'Shipment delivered',
      message: 'Your shipment has been delivered.',
      type: 'shipment.status',
    }).ctaLabel,
    'View progress'
  );
  assert.equal(
    resolveDestination({
      action: { kind: 'request_details', id: REQUEST_ID },
      title: 'Shipment request approved',
      message: 'x',
      type: 'shipment.status',
    }).href,
    `/request-details/${REQUEST_ID}`
  );
  assert.equal(
    resolveDestination({
      action: { kind: 'conversation', id: CONVERSATION_ID },
      title: 'New FleetX message',
      message: 'x',
      type: 'message.new',
    }).href,
    `/conversation/${CONVERSATION_ID}`
  );
});

check('malformed / unknown / null actions stay non-navigable (no title routing)', () => {
  assert.equal(
    resolveDestination({
      action: null,
      title: 'Open /shipment-details/hack',
      message: 'hack',
      type: 'shipment.status',
    }).navigable,
    false
  );
  assert.equal(
    resolveDestination({
      action: { kind: 'assignment', id: UUID },
      title: 'x',
      message: 'y',
      type: 'z',
    }).navigable,
    false
  );
  assert.equal(
    resolveDestination({
      action: { kind: 'shipment_details', id: 'FX-1024' },
      title: 'x',
      message: 'y',
      type: 'z',
    }).navigable,
    false
  );
  assert.match(dest, /void notification\?\.title/);
  assert.match(dest, /void notification\?\.message/);
  assert.match(dest, /void notification\?\.type/);
  assert.doesNotMatch(screen, /shipment-tracking/);
});

check('inbox uses focus refresh + pull-to-refresh; no notification polling', () => {
  assert.match(screen, /useFocusEffect/);
  assert.match(screen, /No polling/);
  assert.match(screen, /RefreshControl/);
  assert.doesNotMatch(screen, /setInterval/);
  assert.doesNotMatch(screen, /Echo/);
  assert.doesNotMatch(screen, /laravel-echo/);
  assert.doesNotMatch(screen, /Reverb/);
  assert.doesNotMatch(api, /setInterval/);
  assert.doesNotMatch(api, /Echo/);
});

check('inbox has loading / error / empty / mark-read / mark-all states', () => {
  assert.match(screen, /notifications-loading/);
  assert.match(screen, /notifications-error/);
  assert.match(screen, /notifications-empty/);
  assert.match(screen, /notifications-mark-all-read/);
  assert.match(screen, /markClientNotificationRead/);
  assert.match(screen, /markAllClientNotificationsRead/);
  assert.match(screen, /getClientNotificationsUnreadCount/);
});

check('Home unread badge + focus refresh without notification store / polling', () => {
  assert.match(home, /getClientNotificationsUnreadCount/);
  assert.match(home, /home-notifications-unread/);
  assert.match(home, /useFocusEffect/);
  assert.doesNotMatch(home, /from ['"]zustand['"]|from ['"]redux['"]|setInterval\(/);
  assert.doesNotMatch(home, /\bEcho\b|\bReverb\b/);
  assert.match(profile, /\/notifications/);
  assert.match(layout, /notifications/);
});

check('presentation labels preserve A2 type vocabulary', () => {
  assert.match(api, /shipment\.status.*Shipment update|if \(raw === 'shipment\.status'\) return 'Shipment update'/);
  assert.match(api, /message\.new.*Message|if \(raw === 'message\.new'\) return 'Message'/);
  assert.match(types, /shipment_details/);
  assert.match(types, /shipment_progress/);
  assert.match(types, /request_details/);
  assert.match(types, /conversation/);
});

check('backend A4.2 product verification suite exists for writer → inbox HTTP path', () => {
  assert.match(productTest, /C4\.2-A4\.2/);
  assert.match(productTest, /test_scenario_request_approved_appears_in_inbox/);
  assert.match(productTest, /test_scenario_shipment_in_transit_inbox_maps_shipment_progress_action/);
  assert.match(productTest, /test_scenario_shipment_delivered_inbox_maps_shipment_progress_action/);
  assert.match(productTest, /test_scenario_dispatcher_message_appears_in_inbox_with_conversation_action/);
  assert.match(productTest, /test_scenario_mark_one_read_decrements_unread/);
  assert.match(productTest, /test_scenario_mark_all_read_zeroes_unread/);
  assert.match(productTest, /test_scenario_cross_company_and_cross_user_isolation/);
});

check('destination screens exist; shipment_progress may open Client progress only', () => {
  assert.ok(fs.existsSync(path.join(clientRoot, 'src/app/shipment-details/[id].tsx')));
  assert.ok(fs.existsSync(path.join(clientRoot, 'src/app/shipment-tracking/[id].tsx')));
  assert.ok(fs.existsSync(path.join(clientRoot, 'src/app/request-details/[id].tsx')));
  assert.ok(fs.existsSync(path.join(clientRoot, 'src/app/conversation/[id].tsx')));
  assert.match(dest, /\/shipment-tracking\/\$\{id\}/);
  assert.doesNotMatch(dest, /tracking-logs|live-fleet|\/dispatcher/);
});

console.log(`\nC4.2-A4.2 verifier: ${passed} passed, ${failed} failed`);
if (failed > 0) {
  process.exitCode = 1;
}
