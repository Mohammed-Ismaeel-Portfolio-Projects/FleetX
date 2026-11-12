/**
 * ADMIN-04 — Remove Admin AI Center surface.
 * Run: node ./scripts/test-admin-04-remove-ai-center.mjs
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

test('admin sidebar does not expose AI Center navigation', () => {
  const sidebar = read('src/components/dashboard/layout/Sidebar.tsx');
  assert.equal(sidebar.includes("label: 'AI Center'"), false);
  assert.equal(sidebar.includes('/admin/ai-center'), false);
  assert.equal(/Sparkles/.test(sidebar), false);
});

test('admin app router does not register /admin/ai-center', () => {
  const app = read('src/app/App.tsx');
  assert.equal(app.includes('ai-control/AiPage'), false);
  assert.equal(/path="ai-center"/.test(app), false);
  assert.equal(/AiPage/.test(app), false);
});

test('admin AI Center page and components are removed', () => {
  const removed = [
    'src/components/dashboard/ai-control/AiPage.tsx',
    'src/components/dashboard/ai-control/AiHeader.tsx',
    'src/components/dashboard/ai-control/SystemLatencyRow.tsx',
    'src/components/dashboard/ai-control/SystemLatencyCard.tsx',
    'src/components/dashboard/ai-control/DecisionStream.tsx',
    'src/components/dashboard/ai-control/EngineControlPanel.tsx',
    'src/components/dashboard/ai-control/RadarMetricsWidget.tsx',
    'src/components/dashboard/ai-control/AiInsightCard.tsx',
    'src/components/dashboard/ai-control/AnomalyAlertsPanel.tsx',
    'src/components/dashboard/overview/AiWidget.tsx',
  ];
  for (const rel of removed) {
    assert.equal(existsSync(resolve(ROOT, rel)), false, `should be deleted: ${rel}`);
  }
});

test('admin dashboard does not render AiWidget or AI Apply actions', () => {
  const overview = read('src/components/dashboard/overview/OverviewPage.tsx');
  assert.equal(/AiWidget/.test(overview), false);
  assert.equal(/ai-control/.test(overview), false);

  const alerts = read('src/components/dashboard/overview/AlertsWidget.tsx');
  assert.equal(alerts.includes('Assign Rescue'), false);
  assert.equal(alerts.includes('Approve Reroute'), false);
  assert.equal(/primaryAction/.test(alerts), false);

  const performance = read('src/components/dashboard/overview/PerformanceWidget.tsx');
  assert.equal(performance.includes('AI Optimization Score'), false);
});

test('breadcrumb no longer maps ai-center route label', () => {
  const breadcrumb = read('src/components/dashboard/layout/Breadcrumb.tsx');
  assert.equal(breadcrumb.includes("'ai-center'"), false);
  assert.equal(breadcrumb.includes('AI Center'), false);
});

test('dispatcher dispatch-ai is completely removed', () => {
  const app = read('src/app/App.tsx');
  assert.equal(app.includes('path="dispatch-ai"'), false);
  assert.equal(app.includes('SmartDispatchPage'), false);

  const layout = read('src/layouts/DispatcherLayout.tsx');
  assert.equal(layout.includes('/dispatcher/dispatch-ai'), false);
  assert.equal(layout.includes('Smart Dispatch AI'), false);
});

test('simulation analytics engines remain in backend', () => {
  assert.equal(
    existsSync(resolve(REPO, 'backend/app/Simulation/Driver/DriverIntelligenceEngine.php')),
    true,
  );
  assert.equal(
    existsSync(resolve(REPO, 'backend/app/Simulation/Analytics/FleetAnalyticsEngine.php')),
    true,
  );
});

test('backend has no dedicated Admin AI Center routes', () => {
  const routes = read('backend/routes/api.php', REPO);
  assert.equal(/ai-center|AiCenter|ai_center/i.test(routes), false);
});

test('no runtime imports reference removed admin ai-control files', () => {
  const runtimeFiles = [
    'src/app/App.tsx',
    'src/components/dashboard/layout/Sidebar.tsx',
    'src/components/dashboard/overview/OverviewPage.tsx',
    'src/layouts/AdminLayout.tsx',
  ];
  const combined = runtimeFiles.map((rel) => read(rel)).join('\n');
  assert.equal(/ai-control/.test(combined), false);
  assert.equal(/AiWidget/.test(combined), false);
  assert.equal(/AiPage/.test(combined), false);
});
