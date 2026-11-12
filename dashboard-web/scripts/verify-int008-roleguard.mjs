/**
 * INT-008 — RoleGuard must not exist as a live authorization mechanism.
 */
import { strict as assert } from 'node:assert';
import { existsSync, readdirSync, readFileSync, statSync } from 'node:fs';
import { dirname, join, relative, resolve } from 'node:path';
import { test } from 'node:test';
import { fileURLToPath } from 'node:url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const ROOT = resolve(__dirname, '..');
const SRC = resolve(ROOT, 'src');

function walk(dir) {
  const files = [];
  for (const entry of readdirSync(dir)) {
    const full = join(dir, entry);
    const stat = statSync(full);
    if (stat.isDirectory()) {
      files.push(...walk(full));
    } else if (/\.(ts|tsx|js|jsx|mjs)$/.test(entry)) {
      files.push(full);
    }
  }
  return files;
}

test('INT-008 RoleGuard.tsx is deleted', () => {
  assert.equal(existsSync(resolve(SRC, 'core/auth/RoleGuard.tsx')), false);
});

test('INT-008 barrel does not export RoleGuard', () => {
  const barrel = readFileSync(resolve(SRC, 'core/auth/index.ts'), 'utf8');
  assert.equal(/RoleGuard/.test(barrel), false);
  assert.match(barrel, /ProtectedRoute/);
});

test('INT-008 no production RoleGuard imports', () => {
  const hits = [];
  for (const file of walk(SRC)) {
    const text = readFileSync(file, 'utf8');
    if (/\bRoleGuard\b/.test(text)) {
      hits.push(relative(ROOT, file));
    }
  }
  assert.deepEqual(hits, []);
});

test('INT-008 ProtectedRoute remains the dashboard auth boundary', () => {
  const app = readFileSync(resolve(SRC, 'app/App.tsx'), 'utf8');
  assert.match(app, /import \{ ProtectedRoute, AuthProvider \} from ['"]\.\.\/core\/auth['"]/);
  assert.match(app, /<ProtectedRoute allowedRoles=\{\['admin'\]\}/);
  assert.match(app, /<ProtectedRoute allowedRoles=\{\['dispatcher'\]\}/);
  assert.equal(/\bRoleGuard\b/.test(app), false);
});
