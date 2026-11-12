/**
 * C2.9-C1.2 Static Verification — Assignment Authorization Gates
 */
import { strict as assert } from 'node:assert';
import { readFileSync, existsSync } from 'node:fs';
import { test } from 'node:test';
import { resolve, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const ROOT = resolve(__dirname, '..', '..', '..');

function read(rel) {
  const p = resolve(ROOT, rel);
  if (!existsSync(p)) return null;
  return readFileSync(p, 'utf8');
}

test('C1.2 implementation doc exists', () => {
  const doc = read('docs/C2.9C1_2_ASSIGNMENT_AUTHORIZATION_GATES_IMPLEMENTATION.md');
  assert.ok(doc);
  assert.match(doc, /C2\.9-C1\.2/i);
});

test('expected assignment gates defined in AppServiceProvider', () => {
  const provider = read('backend/app/Providers/AppServiceProvider.php');
  for (const gate of [
    'assignment.view',
    'assignment.create',
    'assignment.update',
    'assignment.delete',
    'assignment.restore',
    'assignment.force-delete',
    'assignment.change-status',
  ]) {
    assert.match(provider, new RegExp(`Gate::define\\('${gate.replace('.', '\\.')}'`));
  }
});

test('dispatcher authorized on operational assignment gates', () => {
  const provider = read('backend/app/Providers/AppServiceProvider.php');
  const opsGates = ['assignment.view', 'assignment.create', 'assignment.update', 'assignment.delete', 'assignment.change-status'];
  for (const gate of opsGates) {
    const block = provider.split(`Gate::define('${gate}'`)[1]?.split('});')[0] ?? '';
    assert.match(block, /admin.*dispatcher|dispatcher.*admin/s, `${gate} should allow admin and dispatcher`);
  }
});

test('AssignmentPolicy delegates to assignment gates', () => {
  const policy = read('backend/app/Policies/AssignmentPolicy.php');
  assert.match(policy, /assignment\.view/);
  assert.match(policy, /assignment\.create/);
  assert.match(policy, /assignment\.change-status/);
});

test('client gates remain separate from assignment ops gates', () => {
  const provider = read('backend/app/Providers/AppServiceProvider.php');
  assert.match(provider, /client\.shipmentRequest\./);
  assert.match(provider, /client\.shipment\.view/);
});

test('assignment authorization tests exist', () => {
  const testFile = read('backend/tests/Feature/Assignment/AssignmentAuthorizationTest.php');
  assert.ok(testFile);
  assert.match(testFile, /dispatcher/i);
  assert.match(testFile, /403|Forbidden/);
  assert.match(testFile, /401|Unauthorized/);
});

test('C1.2 assignment authorization unchanged after C1.3/C1.4 integration', () => {
  const provider = read('backend/app/Providers/AppServiceProvider.php');
  assert.match(provider, /Gate::define\('assignment\.change-status'/);
  const policy = read('backend/app/Policies/AssignmentPolicy.php');
  assert.match(policy, /assignment\.create/);
});

test('no forbidden UI or notification work introduced', () => {
  assert.equal(
    existsSync(resolve(ROOT, 'backend/app/Notifications/ShipmentOrderConvertedNotification.php')),
    false
  );
});

test('Gate before admin bypass preserved', () => {
  const provider = read('backend/app/Providers/AppServiceProvider.php');
  assert.match(provider, /Gate::before/);
  assert.match(provider, /admin/);
});

test('conversion and shipment order gates unchanged', () => {
  const provider = read('backend/app/Providers/AppServiceProvider.php');
  assert.match(provider, /shipmentOrder\.convert/);
  assert.match(provider, /shipment\.create/);
});
