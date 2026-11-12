/**
 * DISPATCHER-ASSIGNMENT-02 — assigned_by contract verification (static).
 */
import { strict as assert } from 'node:assert';
import { existsSync, readFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { test } from 'node:test';

const __dirname = dirname(fileURLToPath(import.meta.url));
const ROOT = resolve(__dirname, '../..');
const WEB = resolve(ROOT, 'dashboard-web');
const BACKEND = resolve(ROOT, 'backend');

function read(rel, base = WEB) {
  const p = resolve(base, rel);
  if (!existsSync(p)) return '';
  return readFileSync(p, 'utf8');
}

test('backend create request prohibits client assigned_by; controller uses auth id', () => {
  const form = read('app/Http/Requests/Assignment/CreateAssignmentRequest.php', BACKEND);
  const controller = read('app/Http/Controllers/AssignmentController.php', BACKEND);
  assert.match(form, /'assigned_by'\s*=>\s*'prohibited'/);
  assert.doesNotMatch(form, /'assigned_by'\s*=>\s*'required/);
  assert.match(controller, /\$data\['assigned_by'\]\s*=\s*auth\(\)->id\(\)/);
});

test('frontend assignment payload omits assigned_by and has no hardcoded dispatcher id', () => {
  const types = read('src/features/dispatcher/requests/types/assignment.types.ts');
  const service = read('src/features/dispatcher/requests/services/assignmentResources.service.ts');
  const workspace = read('src/features/dispatcher/requests/components/RequestAssignmentWorkspace.tsx');
  assert.doesNotMatch(types, /assigned_by/);
  assert.doesNotMatch(service, /assigned_by/);
  assert.doesNotMatch(workspace, /assigned_by/);
  assert.doesNotMatch(workspace, /assigned_by:\s*1/);
  assert.match(service, /post<.*>\(\s*'\/assignments'/);
  assert.match(workspace, /createAssignment\(/);
  assert.match(workspace, /assignment-error|submitError/);
});
