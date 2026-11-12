/**
 * C5.6 — Client UX Consistency & Error Resilience verifier.
 * Run: node ./scripts/verify-c56-client-ux-consistency.mjs
 *       npm run test:c56-client-ux-consistency
 */

import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const clientRoot = path.join(__dirname, '..');

function readClient(rel) {
  return fs.readFileSync(path.join(clientRoot, rel), 'utf8');
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

const ux = readClient('src/lib/ux/customerErrors.ts');
const screenState = readClient('src/lib/ux/ScreenState.tsx');
const shipmentsApi = readClient('src/features/shipments/shipmentsApi.ts');
const requestsApi = readClient('src/features/requests/requestsApi.ts');
const notificationsApi = readClient('src/features/notifications/notificationsApi.ts');
const messagingApi = readClient('src/features/messaging/messagingApi.ts');
const messages = readClient('src/app/(tabs)/messages.tsx');
const conversation = readClient('src/app/conversation/[id].tsx');
const home = readClient('src/app/(tabs)/index.tsx');
const requestDetails = readClient('src/app/request-details/[id].tsx');
const shipmentDetails = readClient('src/app/shipment-details/[id].tsx');
const tracking = readClient('src/app/shipment-tracking/[id].tsx');
const pkg = readClient('package.json');

check('UX contract module defines customer HTTP semantics', () => {
  assert.match(ux, /export function customerHttpMessage/);
  assert.match(ux, /export function isRetryableLoadFailure/);
  assert.match(ux, /export function screenErrorTitle/);
  assert.match(ux, /export function detectNetworkFailure/);
  assert.match(ux, /networkTitle: 'Connection problem'/);
  assert.match(ux, /accessTitle: 'Access unavailable'/);
  assert.match(ux, /sessionExpired/);
  assert.match(ux, /serverFailure/);
  assert.doesNotMatch(ux, /react-native-maps|ETA|live-fleet/i);
});

check('shared ScreenState primitives exist', () => {
  assert.match(screenState, /export function ScreenLoading/);
  assert.match(screenState, /export function ScreenEmpty/);
  assert.match(screenState, /export function ScreenError/);
  assert.match(screenState, /ClientUxCopy/);
});

check('domain APIs share customerHttpMessage mapping', () => {
  assert.match(shipmentsApi, /customerHttpMessage/);
  assert.match(shipmentsApi, /detectNetworkFailure/);
  assert.match(shipmentsApi, /resource: 'shipment'/);
  assert.match(requestsApi, /customerHttpMessage/);
  assert.match(requestsApi, /resource: 'request'/);
  assert.match(notificationsApi, /customerHttpMessage/);
  assert.match(notificationsApi, /resource: 'notification'/);
  assert.match(messagingApi, /customerHttpMessage/);
  assert.match(messagingApi, /isNetwork/);
  assert.match(messagingApi, /resource: 'conversation'/);
});

check('Messages: status-aware errors, no empty-under-error, retry rules', () => {
  assert.match(messages, /MessagingApiError/);
  assert.match(messages, /isRetryableLoadFailure/);
  assert.match(messages, /screenErrorTitle/);
  assert.match(messages, /ScreenError/);
  assert.match(messages, /error \? \[\] : items/);
  assert.match(messages, /error \? null : \(/);
  assert.match(messages, /statusCode === 401/);
  assert.doesNotMatch(messages, /\bUnauthorized\b|\bNetwork Error\b/);
});

check('Conversation: customer titles (no Unauthorized/Error labels)', () => {
  assert.match(conversation, /ClientUxCopy\.accessTitle/);
  assert.match(conversation, /ClientUxCopy\.networkTitle/);
  assert.match(conversation, /Could not open conversation/);
  assert.match(conversation, /Could not send message/);
  assert.doesNotMatch(conversation, /'Unauthorized'|\"Unauthorized\"/);
  assert.doesNotMatch(conversation, /'Error'|\"Error\"/);
  assert.doesNotMatch(conversation, /Send failed/);
});

check('Detail screens: 404 is not retryable (aligned with tracking)', () => {
  assert.match(tracking, /!isForbidden && !isNotFound/);
  assert.match(requestDetails, /!isForbidden && !isNotFound/);
  assert.match(shipmentDetails, /!isForbidden && !isNotFound/);
});

check('Home uses consistent Could not load fallbacks + section error titles', () => {
  assert.match(home, /Could not load shipments/);
  assert.match(home, /Could not load requests/);
  assert.match(home, /sectionErrorTitle/);
  assert.doesNotMatch(home, /Failed to load shipments|Failed to load requests/);
});

check('C5.1–C5.4 verifier scripts are registered', () => {
  assert.match(pkg, /test:c51-client-tracking-position/);
  assert.match(pkg, /test:c52-client-session-durability/);
  assert.match(pkg, /test:c53-client-review-feedback/);
  assert.match(pkg, /test:c54-client-filtering/);
  assert.match(pkg, /test:c56-client-ux-consistency/);
});

check('C5.6 UX module does not introduce tracking ETA / live maps', () => {
  assert.doesNotMatch(ux, /\bETA\b|live tracking|MapView/i);
  assert.doesNotMatch(screenState, /\bETA\b|MapView|live-fleet/i);
});

console.log('');
console.log(`C5.6 UX consistency checks: ${passed} passed, ${failed} failed`);
if (failed > 0) process.exit(1);
