/**
 * ADMIN-02 — Admin Assignment Management surface removal.
 * Run: node ./scripts/test-admin-02-remove-assignments.mjs
 */
import { strict as assert } from 'node:assert';
import { readFileSync, existsSync } from 'node:fs';
import { test } from 'node:test';
import { resolve, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const ROOT = resolve(__dirname, '..');
const REPO = resolve(ROOT, '..');

function read(rel, base = ROOT) {
  const p = resolve(base, rel);
  assert.ok(existsSync(p), `missing ${rel}`);
  return readFileSync(p, 'utf8');
}

test('admin sidebar does not expose Assignments navigation', () => {
  const sidebar = read('src/components/dashboard/layout/Sidebar.tsx');
  assert.equal(sidebar.includes("label: 'Assignments'"), false);
  assert.equal(sidebar.includes('/admin/assignments'), false);
});

test('admin app router does not register /admin/assignments', () => {
  const app = read('src/app/App.tsx');
  assert.equal(app.includes('features/admin/assignments'), false);
  assert.equal(/path="assignments"/.test(app), false);
});

test('admin assignments feature folder is removed', () => {
  assert.equal(
    existsSync(resolve(ROOT, 'src/features/admin/assignments/pages/AssignmentsPage.tsx')),
    false
  );
});

test('admin analytics pending-assignments drilldown no longer links to admin assignments', () => {
  const drilldown = read('src/features/dispatcher/analytics/utils/analyticsDrilldown.ts');
  assert.equal(drilldown.includes('/admin/assignments'), false);
  assert.match(drilldown, /case 'pending-assignments':[\s\S]*?return null;/);
});

test('dispatcher assignment workflow remains intact', () => {
  const workspace = read('src/features/dispatcher/requests/components/RequestAssignmentWorkspace.tsx');
  assert.match(workspace, /RequestAssignmentWorkspace/);
  assert.match(workspace, /\/assignments/);

  const requestDetails = read('src/features/dispatcher/requests/pages/RequestDetailsPage.tsx');
  assert.match(requestDetails, /RequestAssignmentWorkspace/);

  const resources = read('src/features/dispatcher/requests/services/assignmentResources.service.ts');
  assert.match(resources, /'\/assignments'/);
});

test('assignment backend routes and controller remain', () => {
  const routes = read('backend/routes/api.php', REPO);
  assert.match(routes, /AssignmentController/);
  assert.match(routes, /\/assignments/);

  assert.equal(
    existsSync(resolve(REPO, 'backend/app/Http/Controllers/AssignmentController.php')),
    true
  );
});
