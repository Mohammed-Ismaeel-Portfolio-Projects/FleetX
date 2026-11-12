/**
 * DRIVER-UX — D1 IA + UX foundation + production config safety.
 * Run: npm run test:driver-ux
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

console.log('=== DRIVER-UX (D1) ===\n');

const tabs = read('src/app/(tabs)/_layout.tsx');
const work = read('src/app/(tabs)/index.tsx');
const account = read('src/app/(tabs)/account.tsx');
const screenState = read('src/lib/ux/ScreenState.tsx');
const config = read('src/lib/config.ts');
const appConfig = read('app.config.js');
const envExample = read('.env.example');

check('IA tabs: Work / Messages / Account', () => {
  assert.match(tabs, /tabBarLabel:\s*'Work'/);
  assert.match(tabs, /tabBarLabel:\s*'Messages'/);
  assert.match(tabs, /tabBarLabel:\s*'Account'/);
  assert.ok(fs.existsSync(path.join(root, 'src/app/(tabs)/account.tsx')));
});

check('Debug Home session cards removed from Work', () => {
  assert.doesNotMatch(work, /home-session|User\.id:|Operational messaging foundation/);
  assert.match(work, /work-title|Work/);
  assert.match(work, /No active assignment/);
});

check('Account holds profile/session/logout', () => {
  assert.match(account, /account-title/);
  assert.match(account, /account-logout/);
  assert.match(account, /buildAccountView/);
  assert.doesNotMatch(account, /driver@fleetx/);
});

check('ScreenState primitives exist', () => {
  assert.match(screenState, /export function ScreenLoading/);
  assert.match(screenState, /export function ScreenEmpty/);
  assert.match(screenState, /export function ScreenError/);
  assert.match(screenState, /export function ScreenBlocked/);
  assert.match(screenState, /minHeight:\s*44/);
});

check('Work uses loading / empty / error / retry foundations', () => {
  assert.match(work, /ScreenLoading/);
  assert.match(work, /ScreenEmpty/);
  assert.match(work, /ScreenError/);
  assert.match(work, /work-retry|onRetry/);
});

check('Production API URL HTTPS gating', () => {
  assert.match(config, /EXPO_PUBLIC_ENV === 'production'/);
  assert.match(config, /https:\/\//);
  assert.match(config, /localhost/);
  assert.match(config, /appRole:\s*'driver'/);
});

check('app.config.js gates cleartext / ATS', () => {
  assert.match(appConfig, /allowInsecureHttp/);
  assert.match(appConfig, /usesCleartextTraffic:\s*allowInsecureHttp/);
  assert.match(appConfig, /EXPO_PUBLIC_ENV/);
  assert.equal(fs.existsSync(path.join(root, 'app.json')), false);
});

check('.env.example documents production / insecure HTTP', () => {
  assert.match(envExample, /EXPO_PUBLIC_ENV/);
  assert.match(envExample, /ALLOW_INSECURE_HTTP|EXPO_PUBLIC_ALLOW_INSECURE_HTTP/);
});

check('Messaging conversation route still registered', () => {
  const layout = read('src/app/_layout.tsx');
  assert.match(layout, /conversation\/\[id\]/);
  assert.ok(fs.existsSync(path.join(root, 'src/app/conversation/[id].tsx')));
  assert.ok(fs.existsSync(path.join(root, 'src/app/(tabs)/messages.tsx')));
});

console.log('');
console.log(`DRIVER-UX: ${passed} passed, ${failed} failed`);
if (failed > 0) process.exit(1);
console.log('DRIVER-UX VERDICT: PASS');
