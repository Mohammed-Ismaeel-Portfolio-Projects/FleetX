/**
 * C2.9-C3.9 Static Verification — Client Profile & Account Cleanup
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

const profile = readClient('src/app/(tabs)/profile.tsx') || '';
const identity = readClient('src/features/profile/profileIdentity.ts') || '';
const config = readClient('src/lib/config.ts') || '';
const authCtx = readClient('src/lib/auth/AuthContext.tsx') || '';
const authApi = readClient('src/lib/auth/authApi.ts') || '';
const tabs = readClient('src/app/(tabs)/_layout.tsx') || '';
const home = readClient('src/app/(tabs)/index.tsx') || '';
const appConfig = readClient('app.config.js') || readClient('app.json') || '';
const pkg = readClient('package.json') || '';

/** Mirrors profileIdentity helpers for focused behavioral checks. */
function deriveProfileInitials(name) {
  const trimmed = (name ?? '').trim();
  if (!trimmed) return 'F';
  const parts = trimmed.split(/\s+/).filter(Boolean);
  if (parts.length === 1) return parts[0].charAt(0).toUpperCase();
  return `${parts[0].charAt(0)}${parts[parts.length - 1].charAt(0)}`.toUpperCase();
}

test('C3.9 documentation exists with required sections', () => {
  const doc = read('docs/C2.9C3_9_CLIENT_PROFILE_ACCOUNT_CLEANUP.md');
  assert.ok(doc);
  assert.match(doc, /C2\.9-C3\.9/i);
  assert.match(doc, /HARD STOP/i);
  for (const s of [
    'Existing Profile architecture',
    'Audit findings',
    'Removed',
    'information hierarchy',
    'Identity',
    'Company',
    'Notification',
    'Logout',
    'Security',
    'Accessibility',
    'Historical verifier',
    'Tests',
    'Known',
    'Deferred',
  ]) {
    assert.match(doc, new RegExp(s, 'i'), s);
  }
  assert.match(doc, /No backend changes/i);
});

test('real authenticated identity is used; fake phone and AR avatar absent', () => {
  assert.match(profile, /useAuth\(/);
  assert.match(profile, /user\?\.name/);
  assert.match(profile, /user\?\.email/);
  assert.match(profile, /identityLinked/);
  assert.match(profile, /deriveProfileInitials/);
  assert.match(identity, /export function deriveProfileInitials/);
  assert.equal(profile.includes('+1 (555)'), false);
  assert.equal(profile.includes('012-3456'), false);
  assert.equal(profile.includes('"AR"'), false);
  assert.equal(profile.includes("'AR'"), false);
  assert.equal(/>AR</.test(profile), false);
});

test('fake verification badge and edit pencil are absent', () => {
  assert.equal(profile.includes('BadgeCheck'), false);
  assert.equal(profile.includes('Verified'), false);
  assert.equal(profile.includes('Pencil'), false);
});

test('unsupported decorative settings are removed', () => {
  const forbidden = [
    'Saved Addresses',
    '88 Maple',
    'World Trade Co',
    'Biometric',
    'Fingerprint',
    '2FA',
    'Login History',
    'Change Password',
    'AI Insights',
    'Sparkles',
    'Shipment Alerts',
    'Location Access',
    'Dark Mode',
    'English (US)',
    'Help Center',
    'Privacy Policy',
    'Operational',
    'Version 4.2.0',
    'RenderSwitch',
    'setBiometrics',
    'setAiInsights',
  ];
  for (const f of forbidden) {
    assert.equal(profile.includes(f), false, f);
  }
  assert.equal(profile.includes('>On</'), false);
});

test('notifications action opens existing /notifications only', () => {
  assert.match(profile, /router\.push\('\/notifications'\)/);
  assert.match(profile, /profile-open-notifications/);
  assert.equal(profile.includes('listClientNotifications'), false);
  assert.equal(profile.includes('getClientNotificationsUnreadCount'), false);
  assert.equal(profile.includes('unreadBadge'), false);
  assert.match(home, /home-notifications-header/);
  assert.match(home, /router\.push\('\/notifications'\)/);
});

test('logout uses existing AuthProvider architecture', () => {
  assert.match(profile, /logout/);
  assert.match(profile, /await logout\(\)/);
  assert.match(profile, /profile-logout/);
  assert.match(profile, /loggingOut/);
  assert.match(profile, /router\.replace\('\/login'\)/);
  assert.match(authCtx, /logoutInFlight/);
  assert.match(authApi, /\/auth\/logout/);
  assert.match(authApi, /clearAccessToken/);
  assert.equal(profile.includes('clearAccessToken'), false);
  assert.equal(profile.includes('setAccessToken'), false);
  assert.equal(profile.includes('SecureStore'), false);
});

test('Profile remains the fifth primary tab; no Settings tab', () => {
  assert.match(tabs, /name="profile"/);
  assert.match(tabs, /name="index"/);
  assert.match(tabs, /name="requests"/);
  assert.match(tabs, /name="shipments"/);
  assert.match(tabs, /name="messages"/);
  assert.equal(tabs.includes('name="settings"'), false);
  assert.equal(existsSync(resolve(CLIENT, 'src/app/(tabs)/settings.tsx')), false);
});

test('Client / Dispatcher / Engine boundary preserved', () => {
  const blob = [profile, identity, config].join('\n');
  assert.equal(blob.includes('/dispatcher/'), false);
  assert.equal(blob.includes('/admin/'), false);
  assert.equal(blob.includes('/assignments/'), false);
  assert.equal(blob.includes('live-fleet'), false);
  assert.equal(blob.includes('SimulationRuntime'), false);
  assert.equal(blob.includes('/companies/'), false);
  assert.equal(blob.includes('ShipmentLifecycleEngine'), false);
});

test('app version comes from real configuration when shown', () => {
  assert.match(profile, /AppConfig\.appVersion/);
  assert.match(config, /appVersion/);
  assert.match(config, /expoConfig\?\.version/);
  assert.match(appConfig, /version:\s*['"]1\.0\.0['"]|"version":\s*"1\.0\.0"/);
  assert.equal(profile.includes('4.2.0'), false);
  assert.equal(profile.includes('Operational'), false);
});

test('accessibility metadata present on interactive controls', () => {
  assert.match(profile, /accessibilityLabel/);
  assert.match(profile, /accessibilityRole="button"/);
  assert.match(profile, /accessibilityHint/);
  assert.match(profile, /accessibilityState/);
  assert.match(profile, /Sign out|Signing out/);
  assert.match(profile, /minHeight: 48|minHeight: 52/);
});

test('identity helpers handle names safely', () => {
  assert.equal(deriveProfileInitials('John Smith'), 'JS');
  assert.equal(deriveProfileInitials('John'), 'J');
  assert.equal(deriveProfileInitials(''), 'F');
  assert.equal(deriveProfileInitials(null), 'F');
  assert.match(identity, /FleetX Customer/);
  assert.match(identity, /formatCustomerRoleLabel/);
});

test('package script registered', () => {
  assert.match(pkg, /test:c29c3-9-client-profile-account-cleanup/);
});
