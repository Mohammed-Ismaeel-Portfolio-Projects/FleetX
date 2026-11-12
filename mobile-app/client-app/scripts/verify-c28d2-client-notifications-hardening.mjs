/**
 * C2.8-D2 — Client Notifications UX hardening (static verification).
 * C2.9-C3.8: CTAs are allowed only through the destination resolver.
 * Run: node ./scripts/verify-c28d2-client-notifications-hardening.mjs
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

const screen = readClient('src/app/notifications.tsx');
const api = readClient('src/features/notifications/notificationsApi.ts');
const home = readClient('src/app/(tabs)/index.tsx');
const docPath = path.join(repoRoot, 'docs/C2.8D2_CLIENT_NOTIFICATIONS_UX_HARDENING.md');

check('request concurrency + stale response protection', () => {
  assert.match(screen, /inFlightRef/);
  assert.match(screen, /requestSeqRef/);
  assert.match(screen, /requestSeq !== requestSeqRef\.current/);
  assert.match(screen, /mountedRef/);
  assert.match(screen, /mode === 'refresh' \|\| mode === 'replace'/);
  assert.match(screen, /requestSeqRef\.current \+= 1/);
});

check('refresh preserves rows on failure; load-more preserves + retry', () => {
  assert.match(screen, /errorSource === 'refresh'/);
  assert.match(screen, /Preserve existing rows on refresh failure|errorSource\('refresh'\)/);
  assert.match(screen, /errorSource === 'append'/);
  assert.match(screen, /appendFailedPage/);
  assert.match(screen, /notifications-load-more-retry/);
  assert.match(screen, /notifications-inline-error/);
  assert.match(screen, /!seen\.has\(row\.id\)/);
});

check('duplicate page / refresh vs append isolation', () => {
  assert.match(screen, /pageToLoad !== pageRef\.current \+ 1/);
  assert.match(screen, /pageRef\.current >= lastPageRef\.current/);
  assert.match(screen, /PAGE_SIZE = 15/);
  assert.match(screen, /inFlightRef\.current \|\| loadingMore \|\| refreshing/);
});

check('duplicate mark-read protection; already-read skips mutation', () => {
  assert.match(screen, /markInFlightRef/);
  assert.match(screen, /markSeqByIdRef/);
  assert.match(screen, /target\.isRead/);
  assert.match(screen, /Already-read must not trigger PATCH|target\.isRead\) return/);
  assert.match(screen, /Number\(updated\.id\) !== Number\(id\)/);
  assert.match(screen, /accessibilityRole="summary"/);
});

check('mark-all concurrency + unread count consistency', () => {
  assert.match(screen, /markAllInFlightRef/);
  assert.match(screen, /markAllSeqRef/);
  assert.match(screen, /unreadSeqRef/);
  assert.match(screen, /applyUnreadCount|Math\.max\(0/);
  assert.match(screen, /markAllClientNotificationsRead/);
  assert.match(screen, /notifications-mark-all-read/);
  assert.match(home, /getClientNotificationsUnreadCount/);
  assert.match(home, /home-notifications-header/);
  assert.equal(home.includes('home-notifications-unavailable'), false);
});

check('403 no misleading retry; 401 delegated; empty ≠ unavailable', () => {
  assert.match(screen, /statusCode === 401/);
  assert.match(screen, /canRetryForbidden = errorStatus !== 403/);
  assert.match(screen, /!isForbidden \? \(/);
  assert.match(screen, /notifications-empty/);
  assert.match(screen, /No notifications yet/);
  assert.match(screen, /empty inbox, not an unavailable/);
  assert.match(screen, /notifications-forbidden/);
  assert.match(screen, /Redirect href="\/login"/);
});

check('no fake data / destinations only via resolver (C3.8); security boundary', () => {
  assert.equal(screen.includes('AI DAILY SUMMARY'), false);
  assert.equal(screen.includes('Track Shipment'), false);
  assert.equal(screen.includes('/issues'), false);
  assert.equal(screen.includes('live-fleet'), false);
  assert.equal(api.includes('/issues'), false);
  assert.match(api, /\/client\/notifications/);
  assert.match(api, /delete \(query as \{ user_id/);
  assert.match(api, /mapClientNotificationAction/);
  assert.match(screen, /resolveClientNotificationDestination/);
  assert.equal(/title\.match|message\.match/.test(screen), false);
});

check('accessibility + structural test IDs', () => {
  assert.match(screen, /notifications-screen/);
  assert.match(screen, /notifications-refresh/);
  assert.match(screen, /notifications-list/);
  assert.match(screen, /notification-item-\$/);
  assert.match(screen, /notification-unread-/);
  assert.match(screen, /notification-mark-read-/);
  assert.match(screen, /notifications-mark-all-read/);
  assert.match(screen, /notifications-retry/);
  assert.match(screen, /notifications-load-more-retry/);
  assert.match(screen, /accessibilityLabel/);
  assert.match(screen, /Unread notification|Read notification/);
  assert.match(screen, /minHeight: 44|minWidth: 44/);
});

check('hardening documentation exists', () => {
  assert.ok(fs.existsSync(docPath));
  const body = fs.readFileSync(docPath, 'utf8');
  assert.match(body, /request lifecycle|Request lifecycle/i);
  assert.match(body, /mark-all|Mark-all/i);
  assert.match(body, /Deferred/i);
});

if (process.exitCode) {
  console.error(`\nC2.8-D2 verification FAILED (${passed} checks passed before failure).`);
} else {
  console.log(`\nC2.8-D2 verification OK — ${passed} checks passed.`);
}
