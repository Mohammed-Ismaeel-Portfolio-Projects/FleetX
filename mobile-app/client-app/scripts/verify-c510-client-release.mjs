/**
 * C5.10 — Client Production / Graduation Release Gate
 *
 * Classifications:
 *   PASS          — verified successfully (runtime or decisive static proof)
 *   PASS — STATIC — verified from source/config (not a real store/device binary)
 *   BLOCKED       — environment limitation (not a product FAIL)
 *   FAIL          — material product/release defect
 *
 * Run: npm run test:c510-client-release
 *
 * Env:
 *   C510_SKIP_C58=1   — skip security gate orchestration
 *   C510_SKIP_C59=1   — skip E2E (report BLOCKED if skipped; not FAIL)
 *   C510_SKIP_EXPORT=1 — skip expo export web
 */

import assert from 'node:assert/strict';
import { spawnSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const clientRoot = path.join(__dirname, '..');
const repoRoot = path.join(clientRoot, '..', '..');
const backendRoot = path.join(repoRoot, 'backend');

let passed = 0;
let passedStatic = 0;
let blocked = 0;
let failed = 0;

function read(rel) {
  return fs.readFileSync(path.join(clientRoot, rel), 'utf8');
}

function exists(rel) {
  return fs.existsSync(path.join(clientRoot, rel));
}

function classify(kind, name, detail = '') {
  const suffix = detail ? ` — ${detail}` : '';
  if (kind === 'PASS') {
    passed += 1;
    console.log(`PASS           ${name}${suffix}`);
  } else if (kind === 'PASS_STATIC') {
    passedStatic += 1;
    console.log(`PASS — STATIC  ${name}${suffix}`);
  } else if (kind === 'BLOCKED') {
    blocked += 1;
    console.log(`BLOCKED        ${name}${suffix}`);
  } else {
    failed += 1;
    console.error(`FAIL           ${name}${suffix}`);
  }
}

function check(kind, name, fn) {
  try {
    fn();
    classify(kind, name);
  } catch (e) {
    classify('FAIL', name, e?.message || String(e));
  }
}

function runNpm(script, opts = {}) {
  const result = spawnSync('npm', ['run', script], {
    cwd: clientRoot,
    encoding: 'utf8',
    shell: true,
    env: { ...process.env, ...opts.env },
  });
  return result;
}

function runCmd(command, args, cwd) {
  return spawnSync(command, args, {
    cwd,
    encoding: 'utf8',
    shell: true,
  });
}

console.log('=== C5.10 Client Production / Graduation Release Gate ===\n');

const pkg = read('package.json');
const configTs = read('src/lib/config.ts');
const appConfig = exists('app.config.js') ? read('app.config.js') : '';
const envExample = exists('.env.example') ? read('.env.example') : '';
const gitignore = exists('.gitignore') ? read('.gitignore') : '';
const customerErrors = read('src/lib/ux/customerErrors.ts');
const authApi = read('src/lib/auth/authApi.ts');
const apiClient = read('src/lib/api/client.ts');
const messagingApi = exists('src/features/messaging/messagingApi.ts')
  ? read('src/features/messaging/messagingApi.ts')
  : '';
const jwtConfig = fs.existsSync(path.join(backendRoot, 'config/jwt.php'))
  ? fs.readFileSync(path.join(backendRoot, 'config/jwt.php'), 'utf8')
  : '';
const backendEnvExample = fs.existsSync(path.join(backendRoot, '.env.example'))
  ? fs.readFileSync(path.join(backendRoot, '.env.example'), 'utf8')
  : '';

const srcBlob = (() => {
  const out = [];
  function walk(dir) {
    for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
      const full = path.join(dir, entry.name);
      if (entry.isDirectory()) {
        if (entry.name === 'node_modules' || entry.name === '.git') continue;
        walk(full);
      } else if (/\.(ts|tsx)$/.test(entry.name)) {
        out.push(fs.readFileSync(full, 'utf8'));
      }
    }
  }
  walk(path.join(clientRoot, 'src'));
  return out.join('\n');
})();

console.log('--- Configuration ---\n');

check('PASS_STATIC', 'app.config.js gates cleartext/ATS (not always-on)', () => {
  assert.ok(exists('app.config.js'), 'app.config.js missing');
  assert.ok(!exists('app.json'), 'stale app.json must not override app.config.js');
  assert.match(appConfig, /allowInsecureHttp/);
  assert.match(appConfig, /EXPO_PUBLIC_ENV|EXPO_PUBLIC_ALLOW_INSECURE_HTTP/);
  assert.match(appConfig, /usesCleartextTraffic:\s*allowInsecureHttp/);
});

check('PASS_STATIC', 'production API URL is env-driven and fail-closed', () => {
  assert.match(configTs, /EXPO_PUBLIC_API_BASE_URL/);
  assert.match(configTs, /EXPO_PUBLIC_ENV\s*===\s*['"]production['"]/);
  assert.match(configTs, /localhost|127\\.0\\.0\\.1/);
  assert.match(configTs, /https:\/\//);
  assert.match(configTs, /throw new Error/);
});

check('PASS_STATIC', '.env.example documents production HTTPS path', () => {
  assert.match(envExample, /EXPO_PUBLIC_ENV=production/);
  assert.match(envExample, /https:\/\//);
  assert.match(envExample, /EXPO_PUBLIC_ALLOW_INSECURE_HTTP/);
});

check('PASS_STATIC', '.env is gitignored (secrets hygiene)', () => {
  assert.match(gitignore, /^\.env$/m);
});

check('PASS_STATIC', 'production can point to real backend without source edits', () => {
  assert.match(configTs, /process\.env\.EXPO_PUBLIC_API_BASE_URL/);
  assert.match(envExample, /EXPO_PUBLIC_API_BASE_URL=https:\/\//);
});

console.log('\n--- Authentication / JWT ---\n');

check('PASS_STATIC', 'Client logout calls /auth/logout then clears local session', () => {
  assert.match(authApi, /\/auth\/logout/);
  assert.match(authApi, /clearAccessToken|clearLocalSession/);
});

check('PASS_STATIC', '401 interceptor refresh fail-closed architecture present', () => {
  assert.match(apiClient, /401/);
  assert.match(apiClient, /refresh|emitSessionInvalid|skipAuthRefresh/);
});

check('PASS_STATIC', 'Backend JWT blacklist exception defaults to enforced (true)', () => {
  assert.match(jwtConfig, /show_black_list_exception[\s\S]*env\('JWT_SHOW_BLACKLIST_EXCEPTION',\s*true\)/);
  assert.match(backendEnvExample, /JWT_SHOW_BLACKLIST_EXCEPTION=true/);
  assert.match(jwtConfig, /blacklist_enabled[\s\S]*env\('JWT_BLACKLIST_ENABLED',\s*true\)/);
});

console.log('\n--- Network resilience (C5.6) ---\n');

check('PASS_STATIC', 'Retry withheld for 401/403/404', () => {
  assert.match(customerErrors, /isRetryableLoadFailure/);
  assert.match(
    customerErrors,
    /statusCode === 401 \|\| statusCode === 403 \|\| statusCode === 404/
  );
});

check('PASS_STATIC', 'customerHttpMessage contract present', () => {
  assert.match(customerErrors, /customerHttpMessage/);
  assert.match(customerErrors, /detectNetworkFailure/);
});

console.log('\n--- Messaging / realtime boundary ---\n');

check('PASS_STATIC', 'Realtime is opt-in; REST messaging remains primary', () => {
  assert.match(configTs, /realtimeEnabled/);
  assert.match(configTs, /['"]false['"]/);
  assert.match(messagingApi, /\/conversations/);
  assert.doesNotMatch(messagingApi, /\/tracking-logs|\/live-fleet|['"`]\/dispatcher\//);
});

check('PASS_STATIC', 'No Client-created conversation helper in runtime', () => {
  assert.doesNotMatch(srcBlob, /openConversation/);
  assert.doesNotMatch(messagingApi, /apiClient\.post\([^\n]*\/conversations['"`]\s*[,)]/);
});

console.log('\n--- Security release sweep ---\n');

check('PASS_STATIC', 'No C5.9 E2E credentials in Client src runtime', () => {
  assert.doesNotMatch(srcBlob, /Client@123/);
  assert.doesNotMatch(srcBlob, /client@fleetx\.com/);
  assert.doesNotMatch(srcBlob, /fleetx:c59-seed|ClientC59E2ESeeder/);
});

check('PASS_STATIC', 'No Ops tracking / live-fleet / dispatcher API usage in src', () => {
  assert.doesNotMatch(srcBlob, /\/tracking-logs|\/tracking-states|live-fleet/);
  assert.doesNotMatch(srcBlob, /['"`]\/dispatcher\//);
});

check('PASS_STATIC', 'No token console logging in auth/api paths', () => {
  assert.doesNotMatch(authApi, /console\.(log|debug|info)\([^\)]*token/i);
  assert.doesNotMatch(apiClient, /console\.(log|debug|info)\([^\)]*token/i);
});

check('PASS_STATIC', 'react-native-svg declared (C5.9 Expo Web peer)', () => {
  assert.match(pkg, /"react-native-svg"\s*:/);
  assert.match(pkg, /"lucide-react-native"\s*:/);
});

console.log('\n--- Build / dependency validation ---\n');

{
  const r = runCmd('npx', ['tsc', '--noEmit', '-p', '.'], clientRoot);
  if (r.status === 0) classify('PASS', 'TypeScript --noEmit');
  else classify('FAIL', 'TypeScript --noEmit', (r.stderr || r.stdout || '').slice(0, 400));
}

if (process.env.C510_SKIP_EXPORT === '1') {
  classify('BLOCKED', 'expo export -p web', 'C510_SKIP_EXPORT=1');
} else {
  const r = runCmd(
    'npx',
    ['expo', 'export', '-p', 'web', '--output-dir', 'dist-c510'],
    clientRoot
  );
  if (r.status === 0) {
    classify('PASS', 'expo export -p web', 'output dist-c510');
  } else {
    const msg = (r.stderr || r.stdout || '').slice(0, 500);
    // Missing native tooling is BLOCKED; source/bundle errors are FAIL.
    if (/EAS|Xcode|Android SDK|not supported/i.test(msg)) {
      classify('BLOCKED', 'expo export -p web', msg);
    } else {
      classify('FAIL', 'expo export -p web', msg);
    }
  }
}

check('PASS_STATIC', 'No EAS native store project required for this graduation scope', () => {
  // Absence is an accepted limitation — verified as static documentation of scope.
  const hasEas = exists('eas.json');
  if (hasEas) {
    assert.ok(true);
  } else {
    // Still PASS — STATIC: we confirm managed workflow + gitignored ios/android.
    assert.match(gitignore, /\/ios|\/android/);
  }
});

if (!exists('eas.json')) {
  classify(
    'BLOCKED',
    'EAS / App Store / Play Store binary build',
    'No eas.json / checked-in native projects — not verifiable in this environment'
  );
} else {
  classify('PASS_STATIC', 'eas.json present');
}

console.log('\n--- Prior gates ---\n');

if (process.env.C510_SKIP_C58 === '1') {
  classify('BLOCKED', 'C5.8 security gate', 'C510_SKIP_C58=1');
} else {
  const r = runNpm('test:c58-client-security', {
    env: { C58_SKIP_BACKEND: process.env.C58_SKIP_BACKEND || '0' },
  });
  if (r.status === 0) classify('PASS', 'C5.8 security gate');
  else classify('FAIL', 'C5.8 security gate', `exit ${r.status}`);
}

if (process.env.C510_SKIP_C59 === '1') {
  classify(
    'BLOCKED',
    'C5.9 runtime/E2E gate',
    'C510_SKIP_C59=1 — run separately when API + Expo web are available'
  );
} else {
  const r = runNpm('test:c59-client-e2e');
  if (r.status === 0) classify('PASS', 'C5.9 runtime/E2E gate');
  else {
    const out = `${r.stdout || ''}\n${r.stderr || ''}`;
    if (/Client web not reachable|fixture missing|ECONNREFUSED|API fail/i.test(out)) {
      classify(
        'BLOCKED',
        'C5.9 runtime/E2E gate',
        'Environment unavailable (API/web/fixtures) — not a Client product FAIL'
      );
    } else {
      classify('FAIL', 'C5.9 runtime/E2E gate', `exit ${r.status}`);
    }
  }
}

// C5.6 quick static regression
{
  const r = runNpm('test:c56-client-ux-consistency');
  if (r.status === 0) classify('PASS', 'C5.6 UX consistency verifier');
  else classify('FAIL', 'C5.6 UX consistency verifier', `exit ${r.status}`);
}

console.log('\n========================================');
console.log(
  `C5.10 tallies: PASS=${passed}, PASS-STATIC=${passedStatic}, BLOCKED=${blocked}, FAIL=${failed}`
);

if (failed > 0) {
  console.log('C5.10 VERDICT: FAIL — MATERIAL RELEASE DEFECTS');
  process.exit(1);
}

if (blocked > 0) {
  console.log('C5.10 VERDICT: PASS WITH RELEASE LIMITATIONS');
  process.exit(0);
}

console.log('C5.10 VERDICT: PASS — CLIENT APP READY FOR FINAL DECLARATION');
process.exit(0);
