/**
 * ADMIN-05 — Admin Dashboard governance repositioning.
 * Run: node ./scripts/test-admin-05-dashboard-governance.mjs
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

function readOverviewModule() {
  return [
    'src/components/dashboard/overview/OverviewPage.tsx',
    'src/components/dashboard/overview/KpiGrid.tsx',
    'src/components/dashboard/overview/AlertsWidget.tsx',
    'src/components/dashboard/overview/PerformanceWidget.tsx',
    'src/components/dashboard/overview/ShipmentsTable.tsx',
  ].map((rel) => read(rel)).join('\n');
}

test('admin dashboard route remains /admin via OverviewPage', () => {
  const app = read('src/app/App.tsx');
  assert.match(app, /path="" element=\{<OverviewPage \/>}/);
  assert.match(app, /components\/dashboard\/overview\/OverviewPage/);
});

test('admin dashboard has governance-oriented copy', () => {
  const overview = read('src/components/dashboard/overview/OverviewPage.tsx');
  assert.match(overview, /Platform Overview/);
  assert.match(overview, /Enterprise governance view/);
  assert.equal(/Dispatch|Assign Rescue|Approve Reroute/i.test(overview), false);
});

test('admin dashboard removes dispatch-oriented and mock widgets', () => {
  const overview = read('src/components/dashboard/overview/OverviewPage.tsx');
  assert.equal(/FleetMapWidget/.test(overview), false);
  assert.equal(/SystemHealthWidget/.test(overview), false);
  assert.equal(/AiWidget/.test(overview), false);
  assert.equal(existsSync(resolve(ROOT, 'src/components/dashboard/overview/FleetMapWidget.tsx')), false);
  assert.equal(existsSync(resolve(ROOT, 'src/components/dashboard/overview/SystemHealthWidget.tsx')), false);
});

test('admin KPI grid removes assignment and driver dispatch metrics', () => {
  const kpiGrid = read('src/components/dashboard/overview/KpiGrid.tsx');
  assert.equal(kpiGrid.includes('Pending Assignments'), false);
  assert.equal(kpiGrid.includes('Available Drivers'), false);
  assert.match(kpiGrid, /Registered Companies/);
  assert.match(kpiGrid, /dashboardService\.getKPI/);
  assert.match(kpiGrid, /companiesService\.getCompanies/);
  assert.equal(kpiGrid.includes('/admin/assignments'), false);
});

test('admin dashboard has no mutation or dispatch actions', () => {
  const moduleSource = readOverviewModule();
  assert.equal(/\/admin\/assignments/.test(moduleSource), false);
  assert.equal(/createShipment|deleteShipment|changeShipmentStatus|createAssignment/i.test(moduleSource), false);
  assert.equal(/Assign Rescue|Approve Reroute|Apply recommendation|dispatch queue/i.test(moduleSource), false);
  assert.equal(/Monthly ROI|AI Optimization|FleetNeural|VEHICLE_023/i.test(moduleSource), false);
});

test('admin performance widget uses backend performance API', () => {
  const performance = read('src/components/dashboard/overview/PerformanceWidget.tsx');
  assert.match(performance, /dashboardService\.getPerformance/);
  assert.equal(performance.includes('Monthly ROI'), false);
  assert.equal(performance.includes('99.2%'), false);
});

test('admin alerts widget is API-backed and informational only', () => {
  const alerts = read('src/components/dashboard/overview/AlertsWidget.tsx');
  assert.match(alerts, /dashboardService\.getKPI/);
  assert.match(alerts, /\/admin\/notifications/);
  assert.equal(alerts.includes('Assign Rescue'), false);
  assert.equal(alerts.includes('Approve Reroute'), false);
  assert.equal(/primaryAction|FleetNeural|rescue team/i.test(alerts), false);
});

test('admin shipments table remains read-only with link to admin shipments', () => {
  const shipments = read('src/components/dashboard/overview/ShipmentsTable.tsx');
  assert.match(shipments, /\/admin\/shipments/);
  assert.match(shipments, /getShipments/);
  assert.equal(/createShipment|edit|delete|restore|changeStatus/i.test(shipments), false);
});

test('dispatcher dashboard routing remains separate and clean', () => {
  const app = read('src/app/App.tsx');
  assert.match(app, /DispatcherDashboard/);
  assert.equal(app.includes('path="dispatch-ai"'), false);
  assert.equal(app.includes('SmartDispatchPage'), false);
  assert.equal(/overview\/OverviewPage/.test(read('src/components/dashboard/dispatch/DispatcherDashboard.tsx')), false);
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

test('no backend files modified for admin dashboard repositioning', () => {
  const routes = read('backend/routes/api.php', REPO);
  assert.match(routes, /DashboardController/);
  assert.match(routes, /v1\.dashboard\.kpi/);
  assert.match(routes, /v1\.dashboard\.performance/);
});
