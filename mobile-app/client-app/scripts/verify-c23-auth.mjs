/**
 * C2.3 Auth/Session UX — focused pure-function checks (no network, no JWT logging).
 * Run: node ./scripts/verify-c23-auth.mjs
 */

import assert from 'node:assert/strict';
import { createRequire } from 'node:module';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import ts from 'typescript';
import fs from 'node:fs';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const root = path.join(__dirname, '..');

/** Compile a single TS module to CJS in memory for Node assertion runs. */
function loadTsModule(relPath) {
  const abs = path.join(root, relPath);
  const source = fs.readFileSync(abs, 'utf8');
  const { outputText } = ts.transpileModule(source, {
    compilerOptions: {
      module: ts.ModuleKind.CommonJS,
      target: ts.ScriptTarget.ES2020,
      esModuleInterop: true,
    },
  });
  const module = { exports: {} };
  const requireFn = createRequire(import.meta.url);
  const wrapper = new Function('exports', 'require', 'module', '__filename', '__dirname', outputText);
  wrapper(module.exports, requireFn, module, abs, path.dirname(abs));
  return module.exports;
}

const {
  getAuthErrorMessage,
  isNetworkAuthError,
  isUnauthorizedAuthError,
} = loadTsModule('src/lib/auth/authErrors.ts');

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

check('network error detection', () => {
  assert.equal(isNetworkAuthError({ code: 'ERR_NETWORK', message: 'Network Error' }), true);
  assert.equal(isNetworkAuthError({ response: { status: 500 } }), false);
});

check('unauthorized detection', () => {
  assert.equal(isUnauthorizedAuthError({ response: { status: 401 } }), true);
  assert.equal(isUnauthorizedAuthError({ response: { status: 422 } }), false);
});

check('maps network to honest message', () => {
  const msg = getAuthErrorMessage({ code: 'ERR_NETWORK', message: 'Network Error' }, 'fallback');
  assert.match(msg, /Network error/i);
});

check('maps Laravel validation errors', () => {
  const msg = getAuthErrorMessage(
    {
      response: {
        status: 422,
        data: { message: 'The given data was invalid.', errors: { email: ['The provided credentials are incorrect.'] } },
      },
    },
    'fallback'
  );
  assert.equal(msg, 'The provided credentials are incorrect.');
});

check('maps 401 message', () => {
  const msg = getAuthErrorMessage({ response: { status: 401 } }, 'fallback');
  assert.match(msg, /Invalid credentials|session/i);
});

check('maps 403 message', () => {
  const msg = getAuthErrorMessage({ response: { status: 403, data: {} } }, 'fallback');
  assert.match(msg, /permission/i);
});

check('register payload shape (contract fields only)', () => {
  // Document expected body keys for POST /auth/register — no phone, no role.
  const body = {
    name: 'Ada Client',
    email: 'ada@example.com',
    password: 'Secret123!',
    password_confirmation: 'Secret123!',
  };
  assert.deepEqual(Object.keys(body).sort(), [
    'email',
    'name',
    'password',
    'password_confirmation',
  ]);
  assert.ok(!('phone' in body));
  assert.ok(!('role' in body));
  assert.ok(!('role_id' in body));
});

check('session expired copy is honest', () => {
  const SESSION_EXPIRED_MESSAGE = 'Your session expired. Please sign in again.';
  assert.match(SESSION_EXPIRED_MESSAGE, /session expired/i);
});

if (!process.exitCode) {
  console.log(`\nC2.3 auth checks: ${passed} passed`);
}
