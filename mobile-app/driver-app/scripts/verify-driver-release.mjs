/**
 * DRIVER-RELEASE — Production release readiness gate (D10).
 * Run: npm run test:driver-release
 */

import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { execSync } from 'node:child_process';
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

console.log('=== DRIVER-RELEASE (D10) ===\n');

check('Production HTTPS gating in config.ts', () => {
  const cfg = read('src/lib/config.ts');
  assert.match(cfg, /https:\/\//);
  assert.match(cfg, /localhost|127\.0\.0\.1/);
  assert.match(cfg, /isProduction/);
});

check('app.config.js disables cleartext in production', () => {
  const app = read('app.config.js');
  assert.match(app, /allowInsecureHttp/);
  assert.match(app, /usesCleartextTraffic/);
  assert.match(app, /NSAllowsArbitraryLoads/);
});

check('Required permissions/plugins declared', () => {
  const app = read('app.config.js');
  assert.match(app, /expo-location/);
  assert.match(app, /expo-camera/);
  assert.match(app, /expo-image-picker/);
  assert.match(app, /expo-secure-store/);
});

check('.env.example documents production variables', () => {
  const env = read('.env.example');
  assert.match(env, /EXPO_PUBLIC_API_BASE_URL/);
  assert.match(env, /EXPO_PUBLIC_ENV/);
});

check('Login has no hardcoded production credentials', () => {
  const login = read('src/app/login.tsx');
  assert.doesNotMatch(login, /driver@fleetx\.com.*Driver@123/);
  assert.match(login, /__DEV__|EXPO_PUBLIC_DRIVER_DEV_PREFILL/);
});

check('Secure token storage module exists', () => {
  assert.ok(fs.existsSync(path.join(root, 'src/lib/auth/tokenStorage.ts')));
  const ts = read('src/lib/auth/tokenStorage.ts');
  assert.match(ts, /SecureStore|secure-store/);
});

check('DR-REL-01: expo-secure-store aligned with Expo SDK 56', () => {
  const pkg = JSON.parse(read('package.json'));
  const expoPkg = JSON.parse(read('node_modules/expo/package.json'));
  const bundled = JSON.parse(read('node_modules/expo/bundledNativeModules.json'));
  const declared = pkg.dependencies['expo-secure-store'];
  assert.match(String(declared), /^~56\./, `expected ~56.x, got ${declared}`);
  assert.doesNotMatch(String(declared), /\^57\.|^57\./);
  assert.match(String(expoPkg.version), /^56\./);
  assert.match(String(bundled['expo-secure-store']), /^~56\./);
  const lock = read('package-lock.json');
  assert.match(lock, /expo-secure-store-56\./);
  assert.doesNotMatch(lock, /expo-secure-store-57\./);
});

check('JWT refresh single-flight exists', () => {
  assert.ok(fs.existsSync(path.join(root, 'src/lib/auth/authRefresh.ts')));
  assert.ok(fs.existsSync(path.join(root, 'src/lib/api/client.ts')));
});

const statusDoc = path.join(root, '../../docs/driver/DRIVER_APP_STATUS.md');

check('DR-DOC: Driver README exists and is not Expo starter template', () => {
  assert.ok(fs.existsSync(path.join(root, 'README.md')));
  const readme = read('README.md');
  assert.doesNotMatch(readme, /Welcome to your Expo app/i);
  assert.doesNotMatch(readme, /npm run reset-project/);
  assert.match(readme, /FLeetX Driver App/);
  assert.match(readme, /D1–D15|D1-D15/);
});

check('DR-DOC: Current Driver status document exists', () => {
  assert.ok(fs.existsSync(statusDoc), 'docs/driver/DRIVER_APP_STATUS.md missing');
  const status = fs.readFileSync(statusDoc, 'utf8');
  assert.match(status, /D1–D15|D1-D15/);
  assert.match(status, /DR-F01/);
  assert.match(status, /DR-F02/);
  assert.match(status, /received_at/);
  assert.match(status, /D16|Explicitly Out of Scope/i);
  assert.match(status, /foreground-only|Foreground-only/i);
});

check('DR-DOC: reset-project quarantined from Driver package', () => {
  const pkg = JSON.parse(read('package.json'));
  assert.doesNotMatch(read('README.md'), /reset-project/);
  assert.equal(pkg.scripts['reset-project'], undefined);
  assert.ok(!fs.existsSync(path.join(root, 'scripts/reset-project.js')));
});

check('DR-DOC: Documentation does not embed secrets', () => {
  const readme = read('README.md');
  const status = fs.readFileSync(statusDoc, 'utf8');
  const secretPatterns = [
    /Bearer\s+eyJ/i,
    /password\s*[:=]\s*['"][^'"]{8,}['"]/i,
    /EXPO_PUBLIC_.*KEY\s*=\s*[a-zA-Z0-9_-]{20,}/,
  ];
  for (const re of secretPatterns) {
    assert.doesNotMatch(readme, re);
    assert.doesNotMatch(status, re);
  }
});

check('DR-REL-02: production API requires HTTPS and rejects localhost', () => {
  const cfg = read('src/lib/config.ts');
  assert.match(cfg, /EXPO_PUBLIC_API_BASE_URL is required when EXPO_PUBLIC_ENV=production/);
  assert.match(cfg, /Production Driver builds require an https:\/\//);
  assert.match(cfg, /localhost|127\.0\.0\.1/);
});

check('DR-REL-02: production realtime cannot be enabled', () => {
  const cfg = read('src/lib/config.ts');
  assert.match(cfg, /resolveRealtimeEnabled/);
  assert.match(cfg, /EXPO_PUBLIC_REALTIME_ENABLED=false/);
  const env = read('.env.example');
  assert.match(env, /EXPO_PUBLIC_REALTIME_ENABLED=false/);
});

check('DR-REL-02: production cleartext HTTP cannot be enabled', () => {
  const app = read('app.config.js');
  assert.match(app, /allowInsecureHttp = isProduction\s*\?\s*false/s);
});

check('DR-REL-02: dev prefill blocked in production builds', () => {
  const login = read('src/app/login.tsx');
  assert.match(login, /AppConfig\.isProduction/);
  assert.match(login, /__DEV__/);
  assert.match(login, /EXPO_PUBLIC_DRIVER_DEV_PREFILL/);
});

check('DR-REL-02: push bootstrap fails closed without EAS project ID', () => {
  const push = read('src/lib/push/pushRegistration.ts');
  assert.match(push, /easProjectId/);
  assert.match(push, /missing EAS project ID/);
  assert.doesNotMatch(push, /throw new Error/);
});

check('DR-REL-02: no sensitive console logging in Driver src', () => {
  const srcFiles = [];
  function walk(dir) {
    for (const ent of fs.readdirSync(dir, { withFileTypes: true })) {
      const p = path.join(dir, ent.name);
      if (ent.isDirectory()) walk(p);
      else if (/\.(ts|tsx)$/.test(ent.name)) srcFiles.push(p);
    }
  }
  walk(path.join(root, 'src'));
  const combined = srcFiles.map((f) => fs.readFileSync(f, 'utf8')).join('\n');
  assert.doesNotMatch(combined, /console\.log\(/);
  const boundary = read('src/lib/ux/DriverRootErrorBoundary.tsx');
  assert.match(boundary, /__DEV__/);
});

check('DR-REL-02: local env files gitignored', () => {
  const ignore = read('.gitignore');
  assert.match(ignore, /^\.env$/m);
});

console.log('\n→ npm run typecheck');
try {
  execSync('npm run typecheck', { cwd: root, stdio: 'inherit' });
  passed += 1;
  console.log('PASS  typecheck');
} catch {
  failed += 1;
  console.error('FAIL  typecheck');
}

console.log('\n→ npm run lint');
try {
  execSync('npm run lint', { cwd: root, stdio: 'pipe' });
  passed += 1;
  console.log('PASS  lint');
} catch (e) {
  failed += 1;
  console.error('FAIL  lint');
  const out = String(e?.stdout || '') + String(e?.stderr || '');
  if (out.trim()) console.error(out.trim());
}

console.log('');
console.log(`DRIVER-RELEASE: ${passed} passed, ${failed} failed`);
if (failed > 0) process.exit(1);
console.log('DRIVER-RELEASE VERDICT: PASS');
