/**
 * PRICING-03 — Admin Pricing UI connected to backend APIs.
 * Run: node ./scripts/test-admin-pricing-03.mjs
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
    'src/features/admin/pricing/components/ActivePolicyPanel.tsx',
    'src/features/admin/pricing/components/PolicyRulesReadOnly.tsx',
    'src/features/admin/pricing/components/PricingEstimatorForm.tsx',
    'src/features/admin/pricing/components/PricingEstimateResult.tsx',
    'src/features/admin/pricing/services/pricing.service.ts',
    'src/features/admin/pricing/utils/buildPricingEstimatePayload.ts',
  ].map((rel) => read(rel)).join('\n');
}

test('admin pricing route remains /admin/pricing without disconnected stub copy', () => {
  const app = read('src/app/App.tsx');
  assert.match(app, /path="pricing"/);
  assert.match(app, /AdminPricingPage/);
  assert.match(app, /features\/admin\/pricing\/pages\/PricingPage/);

  const sidebar = read('src/components/dashboard/layout/Sidebar.tsx');
  assert.match(sidebar, /href: '\/admin\/pricing'/);

  const page = read('src/features/admin/pricing/pages/PricingPage.tsx');
  assert.equal(/Freight pricing is not connected to a backend/.test(page), false);
  assert.equal(/Unavailable — not connected/.test(page), false);
  assert.equal(/prototype shell only/.test(page), false);
  assert.equal(/No pricing API exists/.test(page), false);
  assert.equal(/No pricing backend exists/.test(page), false);
});

test('active policy API is called and metadata is displayed', () => {
  const service = read('src/features/admin/pricing/services/pricing.service.ts');
  assert.match(service, /getActivePricingPolicy/);
  assert.match(service, /'\/admin\/pricing\/policies\/active'/);
  assert.match(service, /getPricingPolicies/);
  assert.match(service, /'\/admin\/pricing\/policies'/);
  assert.match(service, /getPricingPolicy/);
  assert.match(service, /`\/admin\/pricing\/policies\/\$\{id\}`/);

  const page = read('src/features/admin/pricing/pages/PricingPage.tsx');
  assert.match(page, /pricingService\.getActivePricingPolicy/);

  const panel = read('src/features/admin/pricing/components/ActivePolicyPanel.tsx');
  assert.match(panel, /policy\.name/);
  assert.match(panel, /policy\.status/);
  assert.match(panel, /policy\.currency/);
  assert.match(panel, /policy\.priority/);
  assert.match(panel, /policy\.effective_from/);
  assert.match(panel, /policy\.effective_until/);
  assert.match(panel, /policy\.tax_rate/);
  assert.match(panel, /policy\.tax_inclusive/);
  assert.match(panel, /policy\.minimum_fare/);
});

test('policy rule sections display backend data with honest empty states', () => {
  const rules = read('src/features/admin/pricing/components/PolicyRulesReadOnly.tsx');
  assert.match(rules, /policy\.base_rates/);
  assert.match(rules, /policy\.distance_tiers/);
  assert.match(rules, /policy\.weight_config/);
  assert.match(rules, /policy\.waiting_config/);
  assert.match(rules, /policy\.fees/);
  assert.match(rules, /policy\.priority_multipliers/);
  assert.match(rules, /policy\.discounts/);
  assert.match(rules, /policy\.service_catalog/);
  assert.match(rules, /policy\.variance_rules/);
  assert.match(rules, /No waiting-charge configuration configured/);
  assert.match(rules, /No base rates configured/);
  assert.match(rules, /No variance rules configured/);
});

test('calculate button calls POST /pricing/calculate with backend-supported payload', () => {
  const service = read('src/features/admin/pricing/services/pricing.service.ts');
  assert.match(service, /calculatePricing/);
  assert.match(service, /post<ApiEnvelope<PricingEstimateResponse>>/);
  assert.match(service, /'\/pricing\/calculate'/);

  const form = read('src/features/admin/pricing/components/PricingEstimatorForm.tsx');
  const page = read('src/features/admin/pricing/pages/PricingPage.tsx');
  assert.match(page, /buildPricingEstimatePayload/);
  assert.match(page, /pricingService\.calculatePricing/);
  assert.match(form, /Calculate Estimate/);

  const payload = read('src/features/admin/pricing/utils/buildPricingEstimatePayload.ts');
  assert.match(payload, /pickup_latitude/);
  assert.match(payload, /pickup_longitude/);
  assert.match(payload, /delivery_latitude/);
  assert.match(payload, /delivery_longitude/);
  assert.match(payload, /cargo_type/);
  assert.match(payload, /trailer_type/);
  assert.match(payload, /cargo_weight/);
  assert.match(payload, /shipment_priority/);
  assert.match(payload, /customer_company_id/);
  assert.match(payload, /customer_type/);
  assert.match(payload, /distance_km/);
  assert.match(payload, /pricing_policy_id/);
  assert.equal(/tax_rate:|base_rate:|final_total:/.test(payload), false);
});

test('backend calculation response is displayed without frontend recalculation', () => {
  const result = read('src/features/admin/pricing/components/PricingEstimateResult.tsx');
  assert.match(result, /estimate\.subtotal/);
  assert.match(result, /estimate\.total/);
  assert.match(result, /estimate\.tax\.amount/);
  assert.match(result, /estimate\.charges\.freight/);
  assert.match(result, /estimate\.discount\.amount/);
  assert.match(result, /estimate\.applied_rules/);
  assert.equal(/reduce\(|sum\(|total \+=|subtotal \+=/.test(result), false);

  const moduleSource = pricingModule();
  assert.equal(/PricingEngineService|pricingEngineService/.test(moduleSource), false);
  assert.equal(/FreightRateResolver|WeightSurchargeCalculator|TaxCalculator/.test(moduleSource), false);
});

test('policy and calculation errors are handled accurately', () => {
  const panel = read('src/features/admin/pricing/components/ActivePolicyPanel.tsx');
  assert.match(panel, /Unable to load the active pricing policy/);
  assert.match(panel, /You do not have permission to view pricing policy configuration/);
  assert.match(panel, /No active pricing policy is currently in effect/);
  assert.match(panel, /status === 403/);
  assert.match(panel, /status === 404/);

  const page = read('src/features/admin/pricing/pages/PricingPage.tsx');
  assert.match(page, /PricingApiError/);
  assert.match(page, /flattenPricingErrors/);
  assert.match(page, /setCalculateError/);
});

test('calculator is stateless and does not call operational mutation endpoints', () => {
  const moduleSource = pricingModule();
  assert.equal(/\/shipments'|\/assignments'|\/monthly-invoices\/.+\/finalize|\/monthly-invoices\/.+\/void|shipment-requests\/.+\/convert/.test(moduleSource), false);
  assert.equal(/Create Shipment|Apply to Shipment|Create Invoice|Publish Policy|Save Pricing/.test(moduleSource), false);
  assert.match(moduleSource, /does not create shipments, invoices, or pricing snapshots/);
  assert.match(moduleSource, /Simulation Override/);
});
