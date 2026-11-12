/**
 * PRICING-05 — Pricing policy temporal governance UI + timeline API.
 * Run: node ./scripts/test-admin-pricing-05.mjs
 */
import { strict as assert } from 'node:assert';
import { readFileSync, existsSync } from 'node:fs';
import { test } from 'node:test';
import { resolve, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const ROOT = resolve(__dirname, '..');

function read(rel) {
  const p = resolve(ROOT, rel);
  assert.ok(existsSync(p), `missing ${rel}`);
  return readFileSync(p, 'utf8');
}

test('timeline API is wired in pricing service', () => {
  const service = read('src/features/admin/pricing/services/pricing.service.ts');
  assert.match(service, /getPricingPolicyTimeline/);
  assert.match(service, /'\/admin\/pricing\/policies\/timeline'/);
});

test('pricing page renders policy timeline panel', () => {
  const page = read('src/features/admin/pricing/pages/PricingPage.tsx');
  assert.match(page, /PolicyTimelinePanel/);
  assert.match(page, /getPricingPolicyTimeline/);
  assert.match(page, /PolicyGovernancePanel/);
  assert.match(page, /pricingService\.calculatePricing/);
});

test('timeline panel displays current upcoming and history sections', () => {
  const panel = read('src/features/admin/pricing/components/PolicyTimelinePanel.tsx');
  assert.match(panel, /timeline\.current/);
  assert.match(panel, /timeline\.upcoming/);
  assert.match(panel, /timeline\.history/);
  assert.match(panel, /temporal_state/);
  assert.match(panel, /effective_from/);
  assert.equal(/reduce\(|sum\(|total \+=/.test(panel), false);
});

test('policy editor exposes effective date fields without calculated outputs', () => {
  const editor = read('src/features/admin/pricing/components/PolicyEditorForm.tsx');
  assert.match(editor, /Effective from/);
  assert.match(editor, /Effective until/);
  assert.match(editor, /effective_from/);
  assert.match(editor, /effective_until/);
  assert.equal(/final_total|subtotal|tax\.amount/.test(editor), false);
});

test('governance table shows temporal state from backend', () => {
  const panel = read('src/features/admin/pricing/components/PolicyGovernancePanel.tsx');
  assert.match(panel, /temporal_state/);
  assert.match(panel, /formatDateTime\(policy\.effective_from\)/);
});

test('PRICING-03 calculator and PRICING-04 governance remain intact', () => {
  const service = read('src/features/admin/pricing/services/pricing.service.ts');
  assert.match(service, /createPricingPolicy/);
  assert.match(service, /activatePricingPolicy/);
  assert.match(service, /calculatePricing/);
  assert.match(service, /getActivePricingPolicy/);
});

test('no frontend pricing engine or operational mutations', () => {
  const moduleSource = [
    'src/features/admin/pricing/pages/PricingPage.tsx',
    'src/features/admin/pricing/components/PolicyTimelinePanel.tsx',
    'src/features/admin/pricing/components/PolicyGovernancePanel.tsx',
  ].map((rel) => read(rel)).join('\n');
  assert.equal(/PricingEngineService|pricingEngineService/.test(moduleSource), false);
  assert.equal(/\/shipments'|\/assignments'|shipment-requests\/.+\/convert/.test(moduleSource), false);
});
