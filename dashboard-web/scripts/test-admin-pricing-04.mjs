/**
 * PRICING-04 — Admin Pricing Policy governance UI + backend API wiring.
 * Run: node ./scripts/test-admin-pricing-04.mjs
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

function pricingModule() {
  return [
    'src/features/admin/pricing/pages/PricingPage.tsx',
    'src/features/admin/pricing/components/PolicyGovernancePanel.tsx',
    'src/features/admin/pricing/components/PolicyEditorForm.tsx',
    'src/features/admin/pricing/services/pricing.service.ts',
    'src/features/admin/pricing/components/PricingEstimatorForm.tsx',
    'src/features/admin/pricing/components/PricingEstimateResult.tsx',
  ].map((rel) => read(rel)).join('\n');
}

test('governance service exposes create update activate archive endpoints', () => {
  const service = read('src/features/admin/pricing/services/pricing.service.ts');
  assert.match(service, /createPricingPolicy/);
  assert.match(service, /updatePricingPolicy/);
  assert.match(service, /activatePricingPolicy/);
  assert.match(service, /archivePricingPolicy/);
  assert.match(service, /post<ApiEnvelope<PricingPolicy>>\(\s*'\s*\/admin\/pricing\/policies'/);
  assert.match(service, /put<ApiEnvelope<PricingPolicy>>/);
  assert.match(service, /\/activate`/);
  assert.match(service, /\/archive`/);
});

test('pricing page includes policy governance panel and preserves calculator', () => {
  const page = read('src/features/admin/pricing/pages/PricingPage.tsx');
  assert.match(page, /PolicyGovernancePanel/);
  assert.match(page, /pricingService\.calculatePricing/);
  assert.match(page, /PricingEstimatorForm/);
  assert.match(page, /PricingEstimateResult/);
  assert.equal(/read-only\. No editing in this workspace/.test(page), false);
});

test('governance panel supports draft create edit activate archive flows', () => {
  const panel = read('src/features/admin/pricing/components/PolicyGovernancePanel.tsx');
  assert.match(panel, /Create Draft Policy/);
  assert.match(panel, /createPricingPolicy/);
  assert.match(panel, /updatePricingPolicy/);
  assert.match(panel, /activatePricingPolicy/);
  assert.match(panel, /archivePricingPolicy/);
  assert.match(panel, /policy\.status === 'draft'/);
  assert.match(panel, /Activate/);
  assert.match(panel, /Archive/);
});

test('policy editor submits backend payload without calculated totals', () => {
  const editor = read('src/features/admin/pricing/components/PolicyEditorForm.tsx');
  const defaults = read('src/features/admin/pricing/utils/buildDefaultPolicyPayload.ts');
  assert.match(editor, /base_rates/);
  assert.match(editor, /distance_tiers/);
  assert.match(defaults, /base_rates/);
  assert.match(defaults, /distance_tiers/);
  assert.equal(/final_total|subtotal|tax\.amount/.test(defaults), false);
});

test('frontend does not recalculate pricing or use legacy PricingEngineService', () => {
  const moduleSource = pricingModule();
  assert.equal(/PricingEngineService|pricingEngineService/.test(moduleSource), false);
  assert.equal(/reduce\(|sum\(|total \+=|subtotal \+=/.test(
    read('src/features/admin/pricing/components/PricingEstimateResult.tsx'),
  ), false);
});

test('governance UI does not call operational mutation endpoints', () => {
  const moduleSource = pricingModule();
  assert.equal(/\/shipments'|\/assignments'|\/monthly-invoices\/.+\/finalize|shipment-requests\/.+\/convert/.test(moduleSource), false);
  assert.equal(/Create Shipment|Create Invoice|Apply to Shipment/.test(moduleSource), false);
});

test('PRICING-03 calculator and read APIs remain wired', () => {
  const service = read('src/features/admin/pricing/services/pricing.service.ts');
  assert.match(service, /getActivePricingPolicy/);
  assert.match(service, /getPricingPolicies/);
  assert.match(service, /calculatePricing/);
  assert.match(service, /'\/pricing\/calculate'/);
});
