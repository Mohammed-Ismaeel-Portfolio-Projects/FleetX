/**
 * C2.9-C2.2 Static Verification — Dispatcher Workflow Architecture & Navigation Plan
 * Design-only: confirms architecture doc; no production implementation introduced.
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

test('C2.2 architecture document exists', () => {
  const doc = read('docs/C2.9C2_2_DISPATCHER_WORKFLOW_ARCHITECTURE.md');
  assert.ok(doc);
  assert.match(doc, /C2\.9-C2\.2/i);
  assert.match(doc, /DESIGN ONLY/i);
  assert.match(doc, /Production code changed.*NONE/i);
});

test('recommended workflow is documented', () => {
  const doc = read('docs/C2.9C2_2_DISPATCHER_WORKFLOW_ARCHITECTURE.md');
  assert.match(doc, /Recommended Dispatcher Workflow/i);
  assert.match(doc, /Request/i);
  assert.match(doc, /Convert/i);
  assert.match(doc, /Assign/i);
  assert.match(doc, /Waiting Assignment/i);
  assert.match(doc, /In Transit/i);
  assert.match(doc, /Completed/i);
});

test('Request to Shipment to Assignment relationships are documented', () => {
  const doc = read('docs/C2.9C2_2_DISPATCHER_WORKFLOW_ARCHITECTURE.md');
  assert.match(doc, /Request → Shipment → Assignment|Information Architecture/i);
  assert.match(doc, /ShipmentOrder/);
  assert.match(doc, /Assignment/);
  assert.match(doc, /Request ≠ Shipment|Request detail/i);
});

test('Dispatcher navigation is defined', () => {
  const doc = read('docs/C2.9C2_2_DISPATCHER_WORKFLOW_ARCHITECTURE.md');
  assert.match(doc, /Navigation Architecture/i);
  assert.match(doc, /\/dispatcher\/requests/);
  assert.match(doc, /Keep existing nav|one new Requests/i);
  assert.ok(existsSync(resolve(ROOT, 'dashboard-web/src/layouts/DispatcherLayout.tsx')));
});

test('lifecycle interaction is defined', () => {
  const doc = read('docs/C2.9C2_2_DISPATCHER_WORKFLOW_ARCHITECTURE.md');
  assert.match(doc, /Shipment Lifecycle UI Model/i);
  assert.match(doc, /Waiting Assignment/);
  assert.match(doc, /Assigned/);
  assert.match(doc, /In Transit/);
  assert.match(doc, /Delivered/);
  assert.match(doc, /Completed/);
  assert.match(doc, /next legal transition|next allowed|Mark In Transit/i);
});

test('future implementation phases are defined', () => {
  const doc = read('docs/C2.9C2_2_DISPATCHER_WORKFLOW_ARCHITECTURE.md');
  assert.match(doc, /C2\.9-C2\.3/);
  assert.match(doc, /C2\.9-C2\.4/);
  assert.match(doc, /C2\.9-C2\.5/);
  assert.match(doc, /C2\.9-C2\.6/);
  assert.match(doc, /C2\.9-C2\.7/);
  assert.match(doc, /C2\.9-C2\.8/);
  assert.match(doc, /C2\.9-C2\.9/);
  assert.match(doc, /Request Intake/i);
  assert.match(doc, /Dispatcher Assignment/i);
  assert.match(doc, /Lifecycle Actions/i);
});

test('no production implementation was introduced by C2.2', () => {
  const doc = read('docs/C2.9C2_2_DISPATCHER_WORKFLOW_ARCHITECTURE.md');
  assert.match(doc, /Do not rebuild|DESIGN ONLY|no production/i);
  assert.equal(
    existsSync(resolve(ROOT, 'dashboard-web/src/features/dispatcher/requests')),
    false
  );
  const layout = read('dashboard-web/src/layouts/DispatcherLayout.tsx');
  assert.equal(/\/dispatcher\/requests/.test(layout), false);
  assert.ok(existsSync(resolve(ROOT, 'docs/C2.9C2_1_EXISTING_DISPATCHER_OPERATIONAL_WORKSPACE_AUDIT.md')));
});
