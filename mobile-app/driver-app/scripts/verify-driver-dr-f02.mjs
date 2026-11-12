/**
 * DR-F02 — Root Error Boundary / crash recovery shell.
 * Run: npm run test:driver-dr-f02
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

console.log('=== DR-F02 Root Error Boundary ===\n');

const boundary = read('src/lib/ux/DriverRootErrorBoundary.tsx');
const layout = read('src/app/_layout.tsx');
const pkg = JSON.parse(read('package.json'));

check('A. Real Error Boundary component exists', () => {
  assert.ok(fs.existsSync(path.join(root, 'src/lib/ux/DriverRootErrorBoundary.tsx')));
  assert.match(boundary, /class DriverRootErrorBoundary extends Component/);
});

check('B. Implements React boundary mechanism', () => {
  assert.match(boundary, /getDerivedStateFromError/);
  assert.match(boundary, /componentDidCatch/);
});

check('C. Boundary mounted at Driver application root', () => {
  assert.match(layout, /AuthProvider/);
  assert.match(layout, /DriverRootErrorBoundary/);
  assert.match(layout, /<AuthProvider>\s*\n\s*<DriverRootErrorBoundary>/s);
  assert.match(layout, /<DriverRootErrorBoundary>[\s\S]*<Stack>/);
});

check('D. Retry / reset mechanism exists', () => {
  assert.match(boundary, /Try again/);
  assert.match(boundary, /driver-crash-retry/);
  assert.match(boundary, /treeKey/);
  assert.match(boundary, /handleRetry/);
});

check('E. Sign-out recovery uses existing auth mechanism', () => {
  assert.match(boundary, /useAuth/);
  assert.match(boundary, /logout/);
  assert.match(boundary, /Sign out/);
  assert.match(boundary, /driver-crash-sign-out/);
  assert.doesNotMatch(boundary, /clearAccessToken|SecureStore|clearLocalSession/);
});

check('F. Production recovery UI does not expose raw error details', () => {
  assert.match(boundary, /Something went wrong/);
  assert.doesNotMatch(boundary, /this\.state\.error|error\.message|error\.stack/);
  const recoveryStart = boundary.indexOf('function DriverCrashRecovery');
  const recoveryEnd = boundary.indexOf('export class DriverRootErrorBoundary');
  const recoveryUi = boundary.slice(recoveryStart, recoveryEnd);
  assert.doesNotMatch(recoveryUi, /componentStack|error\.message|error\.stack|\{error\}/);
});

check('G. No backend/API/schema changes in Driver app', () => {
  const backendPaths = ['backend', '../backend'];
  for (const rel of backendPaths) {
    assert.ok(!fs.existsSync(path.join(root, rel)), `unexpected backend path: ${rel}`);
  }
  assert.doesNotMatch(boundary, /\/driver\/|SimulationRuntime|Schema::/);
});

check('H. No crash SDK / Sentry dependency introduced', () => {
  const deps = { ...pkg.dependencies, ...pkg.devDependencies };
  assert.doesNotMatch(boundary, /@sentry|sentry-expo|bugsnag|crashlytics/i);
  for (const key of Object.keys(deps)) {
    assert.doesNotMatch(key, /sentry|bugsnag|crashlytics/i);
  }
});

check('I. No SimulationRuntime dependency', () => {
  assert.doesNotMatch(boundary + layout, /SimulationRuntime|TrackingSimulator/);
});

check('J. No navigation loop / forced reload pattern', () => {
  assert.doesNotMatch(boundary, /location\.reload|Updates\.reloadAsync|router\.replace\(\s*['"`]\/\(tabs\)/);
  assert.doesNotMatch(boundary, /setInterval|while\s*\(/);
});

check('Accessibility labels on recovery actions', () => {
  assert.match(boundary, /accessibilityLabel="Try again"/);
  assert.match(boundary, /accessibilityLabel="Sign out"/);
});

check('Package script test:driver-dr-f02 exists', () => {
  assert.ok(pkg.scripts['test:driver-dr-f02']);
});

console.log('');
console.log(`DR-F02: ${passed} passed, ${failed} failed`);
if (failed > 0) process.exit(1);
console.log('DR-F02 VERDICT: PASS');
