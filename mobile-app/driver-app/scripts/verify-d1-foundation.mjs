/**
 * D1 Foundation gate — orchestrates DRIVER-AUTH, DRIVER-BOUNDARY, DRIVER-UX + messaging.
 * Run: npm run test:d1-foundation
 */

import { spawnSync } from 'node:child_process';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const root = path.join(__dirname, '..');

const steps = [
  ['test:driver-auth', 'DRIVER-AUTH'],
  ['test:driver-boundary', 'DRIVER-BOUNDARY'],
  ['test:driver-ux', 'DRIVER-UX'],
  ['test:messaging-merge', 'messaging-merge'],
  ['test:m8-isolation', 'm8-isolation'],
];

let failed = 0;

console.log('=== D1 Foundation Gate ===\n');

for (const [script, label] of steps) {
  console.log(`→ npm run ${script} (${label})`);
  const r = spawnSync(
    process.platform === 'win32' ? 'npm.cmd' : 'npm',
    ['run', script],
    { cwd: root, encoding: 'utf8', shell: true }
  );
  if (r.stdout) process.stdout.write(r.stdout);
  if (r.stderr) process.stderr.write(r.stderr);
  if (r.status !== 0) {
    failed += 1;
    console.error(`FAIL  ${label}`);
  } else {
    console.log(`PASS  ${label}\n`);
  }
}

if (failed > 0) {
  console.error(`\nD1 Foundation Gate: ${failed} step(s) failed`);
  process.exit(1);
}

console.log('D1 FOUNDATION GATE: PASS');
