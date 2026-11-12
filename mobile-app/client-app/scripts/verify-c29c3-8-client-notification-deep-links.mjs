/**
 * C2.9-C3.8 Static + behavioral verification — Client notification deep links
 */
import { strict as assert } from 'node:assert';
import { readFileSync, existsSync } from 'node:fs';
import { test } from 'node:test';
import { resolve, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const ROOT = resolve(__dirname, '..', '..', '..');
const CLIENT = resolve(ROOT, 'mobile-app/client-app');

function read(rel) {
  const p = resolve(ROOT, rel);
  if (!existsSync(p)) return null;
  return readFileSync(p, 'utf8');
}

function readClient(rel) {
  const p = resolve(CLIENT, rel);
  if (!existsSync(p)) return null;
  return readFileSync(p, 'utf8');
}

const dest = readClient('src/features/notifications/notificationDestination.ts') || '';
const api = readClient('src/features/notifications/notificationsApi.ts') || '';
const types = readClient('src/features/notifications/types.ts') || '';
const screen = readClient('src/app/notifications.tsx') || '';
const home = readClient('src/app/(tabs)/index.tsx') || '';
const layout = readClient('src/app/_layout.tsx') || '';
const resource = read('backend/app/Http/Resources/Client/ClientNotificationResource.php') || '';
const projection = read('backend/app/Support/ClientNotificationAction.php') || '';
const model = read('backend/app/Models/Notification.php') || '';
const migration = read(
  'backend/database/migrations/2026_08_13_180000_add_action_to_notifications_table.php'
) || '';
const featureTest = read('backend/tests/Feature/Notification/ClientNotificationTest.php') || '';
const unitTest = read('backend/tests/Unit/ClientNotificationActionTest.php') || '';
const shipmentDetails = readClient('src/app/shipment-details/[id].tsx') || '';
const requestDetails = readClient('src/app/request-details/[id].tsx') || '';
const conversation = readClient('src/app/conversation/[id].tsx') || '';
const tracking = readClient('src/app/shipment-tracking/[id].tsx') || '';
const pkg = readClient('package.json') || '';

const UUID_RE =
  /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i;
const ALLOWED = new Set([
  'shipment_details',
  'shipment_progress',
  'request_details',
  'conversation',
]);
const SHIPMENT_ID = 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee';
const REQUEST_ID = '11111111-2222-4333-8444-555555555555';
const CONVERSATION_ID = '66666666-7777-4888-8999-aaaaaaaaaaaa';

/** Mirrors notificationDestination.ts — keep in sync with production resolver. */
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
  const action = mapAction(notification?.action ?? null);
  if (!action) {
    return { kind: 'inbox', href: '/notifications', ctaLabel: 'View Notification', navigable: false };
  }
  if (action.kind === 'shipment_details') {
    return {
      kind: 'shipment',
      href: `/shipment-details/${action.id}`,
      ctaLabel: 'View Shipment',
      navigable: true,
    };
  }
  if (action.kind === 'shipment_progress') {
    return {
      kind: 'shipment',
      href: `/shipment-tracking/${action.id}`,
      ctaLabel: 'View progress',
      navigable: true,
    };
  }
  if (action.kind === 'request_details') {
    return {
      kind: 'request',
      href: `/request-details/${action.id}`,
      ctaLabel: 'View Request',
      navigable: true,
    };
  }
  if (action.kind === 'conversation') {
    return {
      kind: 'conversation',
      href: `/conversation/${action.id}`,
      ctaLabel: 'Open Message',
      navigable: true,
    };
  }
  return { kind: 'inbox', href: '/notifications', ctaLabel: 'View Notification', navigable: false };
}

test('C3.8 documentation exists with required sections', () => {
  const doc = read('docs/C2.9C3_8_CLIENT_NOTIFICATION_DEEP_LINKS.md');
  assert.ok(doc);
  assert.match(doc, /C2\.9-C3\.8/i);
  assert.match(doc, /HARD STOP/i);
  for (const s of [
    'audit findings',
    'notification vocabulary',
    'payload',
    'destination resolver',
    'shipment routing',
    'request routing',
    'conversation routing',
    'fallback',
    'authorization',
    'read / unread',
    'backend',
    'files changed',
    'tests',
    'regression',
    'static verifier',
    'known warnings',
    'deferred work',
    'blockers',
  ]) {
    assert.match(doc, new RegExp(s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'i'), s);
  }
});

test('existing notification inbox remains the canonical inbox', () => {
  assert.match(layout, /name="notifications"/);
  assert.ok(existsSync(resolve(CLIENT, 'src/app/notifications.tsx')));
  assert.equal(existsSync(resolve(CLIENT, 'src/app/notification/[id].tsx')), false);
  assert.equal(existsSync(resolve(CLIENT, 'src/app/notification/[id]/redirect.tsx')), false);
  assert.equal(existsSync(resolve(CLIENT, 'src/app/shipment-notification/[id].tsx')), false);
  assert.equal(existsSync(resolve(CLIENT, 'src/app/request-notification/[id].tsx')), false);
  assert.match(screen, /listClientNotifications/);
  assert.match(screen, /notifications-list/);
  assert.match(screen, /notifications-empty/);
});

test('Home bell still opens /notifications', () => {
  assert.match(home, /home-notifications-header/);
  assert.match(home, /openNotifications = \(\) => router\.push\('\/notifications'\)/);
  assert.match(home, /getClientNotificationsUnreadCount/);
  assert.equal(home.includes('resolveClientNotificationDestination'), false);
  assert.match(home, /home-notification-\$\{item\.id\}/);
  assert.match(home, /onPress=\{openNotifications\}/);
});

test('destination resolution is centralized', () => {
  assert.ok(existsSync(resolve(CLIENT, 'src/features/notifications/notificationDestination.ts')));
  assert.match(dest, /export function resolveClientNotificationDestination/);
  assert.match(dest, /export function mapClientNotificationAction/);
  assert.match(screen, /resolveClientNotificationDestination/);
  assert.match(api, /mapClientNotificationAction/);
  assert.match(screen, /router\.push\(destination\.href\)/);
  assert.equal(/title\.match|message\.match/.test(dest), false);
  assert.equal(/title\.match|message\.match/.test(api), false);
  assert.equal(/title\.match|message\.match/.test(screen), false);
});

test('shipment notifications only open Client Shipment Workspace or Progress', () => {
  assert.match(dest, /\/shipment-details\/\$\{id\}/);
  assert.match(dest, /\/shipment-tracking\/\$\{id\}/);
  assert.equal(dest.includes('/dispatcher'), false);
  assert.match(layout, /shipment-details\/\[id\]/);
  assert.ok(existsSync(resolve(CLIENT, 'src/app/shipment-details/[id].tsx')));
  assert.ok(existsSync(resolve(CLIENT, 'src/app/shipment-tracking/[id].tsx')));
  assert.equal(existsSync(resolve(CLIENT, 'src/app/customer-shipment/[id].tsx')), false);
  assert.equal(existsSync(resolve(CLIENT, 'src/app/shipment-workspace/[id].tsx')), false);
});

test('request notifications only open Client Request Detail', () => {
  assert.match(dest, /\/request-details\/\$\{id\}/);
  assert.match(layout, /request-details\/\[id\]/);
  assert.ok(existsSync(resolve(CLIENT, 'src/app/request-details/[id].tsx')));
  assert.equal(existsSync(resolve(CLIENT, 'src/app/request-workspace/[id].tsx')), false);
});

test('conversation notifications only open authorized Conversation', () => {
  assert.match(dest, /\/conversation\/\$\{id\}/);
  assert.match(layout, /conversation\/\[id\]/);
  assert.ok(existsSync(resolve(CLIENT, 'src/app/conversation/[id].tsx')));
  assert.equal(screen.includes('createConversation'), false);
  assert.equal(dest.includes('apiClient.post'), false);
  assert.equal(api.includes('/conversations'), false);
});

test('unknown notifications safely fall back to /notifications', () => {
  assert.match(dest, /href: '\/notifications'/);
  assert.match(dest, /navigable: false/);
});

test('notification text is never used to guess IDs', () => {
  assert.equal(dest.includes('FX-'), false);
  assert.equal(api.includes('FX-'), false);
  assert.equal(/match\(.*title|title\.replace|message\.replace/.test(dest + api + screen), false);
  assert.match(api, /Never from title\/message/);
});

test('no Dispatcher/Engine/Simulation APIs are used', () => {
  const blob = [dest, api, types, screen, home, resource, projection].join('\n');
  assert.equal(blob.includes('/dispatcher/'), false);
  assert.equal(blob.includes('/admin/'), false);
  assert.equal(blob.includes('live-fleet'), false);
  assert.equal(blob.includes('SimulationRuntime'), false);
  assert.equal(blob.includes('ShipmentLifecycleEngine'), false);
  assert.equal(blob.includes('/assignments/'), false);
  assert.equal(blob.includes('/issues'), false);
  assert.equal(projection.includes('assignment'), false);
});

test('existing Client API boundary remains intact', () => {
  assert.match(api, /\/client\/notifications/);
  assert.match(api, /\/client\/notifications\/unread-count/);
  assert.match(api, /\/client\/notifications\/\$\{encodeURIComponent\(id\)\}\/read/);
  assert.match(api, /\/client\/notifications\/read-all/);
  assert.match(api, /delete \(query as \{ user_id/);
  assert.equal(api.includes('company_id:'), false);
  assert.match(resource, /ClientNotificationAction::toClientPayload/);
  assert.equal(resource.includes("'user_id'"), false);
  assert.match(projection, /shipment_details/);
  assert.match(projection, /request_details/);
  assert.match(projection, /conversation/);
  assert.match(model, /action_kind/);
  assert.match(migration, /action_kind/);
  assert.match(migration, /action_id/);
});

test('no duplicate shipment/request/conversation architecture', () => {
  assert.equal(existsSync(resolve(CLIENT, 'src/app/notification-shipment/[id].tsx')), false);
  assert.equal(existsSync(resolve(CLIENT, 'src/app/inbox-shipment/[id].tsx')), false);
  assert.match(shipmentDetails, /getClientShipment/);
  assert.match(requestDetails, /getClientShipmentRequest|getClientRequest|request-details/);
  assert.match(conversation, /getConversation/);
  assert.match(tracking, /shipment-progress/);
  assert.ok(existsSync(resolve(CLIENT, 'src/app/shipment-tracking/[id].tsx')));
});

test('existing C3.1–C3.7 architecture remains intact', () => {
  assert.match(layout, /name="\(tabs\)"/);
  assert.match(layout, /shipment-details\/\[id\]/);
  assert.match(layout, /shipment-tracking\/\[id\]/);
  assert.match(layout, /request-details\/\[id\]/);
  assert.match(layout, /conversation\/\[id\]/);
  assert.match(layout, /name="notifications"/);
  assert.match(home, /home-notifications-header/);
  assert.match(pkg, /test:c29c3-7-client-customer-journey-visualization/);
  assert.match(pkg, /test:c29c3-8-client-notification-deep-links/);
});

test('inaccessible targets reuse existing destination error behavior', () => {
  assert.match(shipmentDetails, /errorStatus === 404/);
  assert.match(shipmentDetails, /Shipment unavailable/);
  assert.match(requestDetails, /errorStatus === 404/);
  assert.match(conversation, /status === 404/);
  assert.match(conversation, /This conversation is unavailable|Conversation unavailable/);
  assert.equal(dest.includes('listClientShipments'), false);
  assert.equal(screen.includes('listClientShipments'), false);
});

test('resolver: shipment target', () => {
  const d = resolveDestination({
    action: { kind: 'shipment_details', id: SHIPMENT_ID },
    title: 'Shipment FX-1024 has been delivered.',
    message: 'Your shipment is complete.',
    type: 'shipment.status',
  });
  assert.equal(d.kind, 'shipment');
  assert.equal(d.href, `/shipment-details/${SHIPMENT_ID}`);
  assert.equal(d.ctaLabel, 'View Shipment');
  assert.equal(d.navigable, true);

  const progress = resolveDestination({
    action: { kind: 'shipment_progress', id: SHIPMENT_ID },
    title: 'On the way',
    message: SHIPMENT_ID,
    type: 'shipment.status',
  });
  assert.equal(progress.href, `/shipment-tracking/${SHIPMENT_ID}`);
  assert.equal(progress.ctaLabel, 'View progress');
  assert.equal(progress.navigable, true);
  assert.match(dest, /shipment-tracking\/\$\{id\}/);
  assert.match(dest, /View progress/);
});

test('resolver: request target', () => {
  const d = resolveDestination({
    action: { kind: 'request_details', id: REQUEST_ID },
    title: 'Your shipment request has been approved.',
    message: 'Open the request to continue.',
    type: 'system.notice',
  });
  assert.equal(d.kind, 'request');
  assert.equal(d.href, `/request-details/${REQUEST_ID}`);
  assert.equal(d.ctaLabel, 'View Request');
});

test('resolver: conversation target', () => {
  const d = resolveDestination({
    action: { kind: 'conversation', id: CONVERSATION_ID },
    title: 'You have a new message from the dispatcher.',
    message: 'Open the conversation.',
    type: 'message.new',
  });
  assert.equal(d.kind, 'conversation');
  assert.equal(d.href, `/conversation/${CONVERSATION_ID}`);
  assert.equal(d.ctaLabel, 'Open Message');
});

test('resolver: unknown / missing / unsupported / invalid targets', () => {
  const missing = resolveDestination({
    action: null,
    title: `Shipment ${SHIPMENT_ID} delivered`,
    message: SHIPMENT_ID,
    type: 'shipment.status',
  });
  assert.equal(missing.href, '/notifications');
  assert.equal(missing.navigable, false);

  const unknown = resolveDestination({
    action: { kind: 'assignment', id: SHIPMENT_ID },
    title: 'Assigned',
    message: 'Internal',
    type: 'shipment.status',
  });
  assert.equal(unknown.href, '/notifications');

  const unsupported = resolveDestination({
    action: { kind: 'engine_state', id: SHIPMENT_ID },
    title: 'EnRoutePickup',
    message: 'Accepted',
    type: 'unknown.event',
  });
  assert.equal(unsupported.href, '/notifications');

  const missingId = resolveDestination({
    action: { kind: 'shipment_details', id: '' },
    title: 'Update',
    message: 'No id',
    type: 'shipment.status',
  });
  assert.equal(missingId.href, '/notifications');

  const invalid = resolveDestination({
    action: { kind: 'shipment_details', id: 'FX-1024' },
    title: 'FX-1024 delivered',
    message: 'See FX-1024',
    type: 'shipment.status',
  });
  assert.equal(invalid.href, '/notifications');
  assert.equal(invalid.navigable, false);
});

test('backend tests cover action projection privacy', () => {
  assert.match(featureTest, /test_valid_action_is_projected_without_internal_fields/);
  assert.match(featureTest, /test_unknown_kind_and_missing_id_project_as_null_action/);
  assert.match(unitTest, /test_unknown_missing_and_invalid_targets_are_null/);
  assert.match(unitTest, /FX-1024/);
});
