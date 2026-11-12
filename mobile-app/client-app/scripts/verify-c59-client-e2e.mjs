/**
 * C5.9 — Client Runtime / E2E Completion Gate
 *
 * Combines:
 *   0) Optional fixture seed (`php artisan fleetx:c59-seed`)
 *   A) Live API critical-path + failure matrix
 *   B) Expo Web Playwright critical customer journey
 *
 * Run: npm run test:c59-client-e2e
 *
 * Env:
 *   C59_SKIP_SEED=1     — do not run artisan seed (fixture must already exist)
 *   C59_SKIP_UI=1       — API-only (gate FAILS: UI required for C5.9 PASS)
 *   C59_START_WEB=1     — auto-start Expo web on :8092 if missing
 *   C59_API_ONLY=1      — alias of C59_SKIP_UI=1 (still non-PASS for full gate)
 */

import { spawnSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const clientRoot = path.join(__dirname, '..');
const repoRoot = path.join(clientRoot, '..', '..');
const backendRoot = path.join(repoRoot, 'backend');
const fixturePath = path.join(backendRoot, 'storage', 'app', 'c59-client-e2e-fixture.json');

function run(label, command, args, opts = {}) {
  console.log(`\n→ ${label}`);
  const result = spawnSync(command, args, {
    cwd: opts.cwd || clientRoot,
    encoding: 'utf8',
    shell: true,
    env: { ...process.env, ...opts.env },
    stdio: 'inherit',
  });
  if (result.status !== 0) {
    const err = new Error(`${label} failed (exit ${result.status})`);
    err.status = result.status;
    throw err;
  }
}

console.log('=== C5.9 Client Runtime / E2E Gate ===\n');

const skipSeed = process.env.C59_SKIP_SEED === '1';
const skipUi =
  process.env.C59_SKIP_UI === '1' || process.env.C59_API_ONLY === '1';

if (!skipSeed) {
  if (!fs.existsSync(path.join(backendRoot, 'artisan'))) {
    console.error('FAIL  backend/artisan not found — cannot seed C5.9 fixtures');
    process.exit(1);
  }
  try {
    run('fleetx:c59-seed', 'php', ['artisan', 'fleetx:c59-seed'], { cwd: backendRoot });
  } catch (e) {
    console.error(e.message);
    process.exit(1);
  }
} else if (!fs.existsSync(fixturePath)) {
  console.error(`FAIL  C59_SKIP_SEED=1 but fixture missing: ${fixturePath}`);
  process.exit(1);
} else {
  console.log('SKIP  seed (C59_SKIP_SEED=1)');
}

try {
  run('API runtime', 'node', ['./scripts/c59-client-api-runtime.mjs']);
} catch (e) {
  console.error(e.message);
  process.exit(1);
}

if (skipUi) {
  console.error('\nFAIL  C5.9 requires Expo Web UI journey for PASS.');
  console.error('Unset C59_SKIP_UI / C59_API_ONLY, ensure Client web :8092, then re-run.');
  console.error('API portion alone is not sufficient for C5.9 completion.');
  process.exit(1);
}

try {
  run('Web journey', 'node', ['./scripts/c59-client-web-journey.mjs'], {
    env: {
      C59_START_WEB: process.env.C59_START_WEB || '1',
      C59_CLIENT_WEB:
        process.env.C59_CLIENT_WEB || process.env.FLEETX_CLIENT_WEB || 'http://127.0.0.1:8092',
    },
  });
} catch (e) {
  console.error(e.message);
  process.exit(1);
}

console.log('\n========================================');
console.log('C5.9 VERDICT: PASS');
console.log('========================================');
