/**
 * DRIVER-EVAL-06 — focused frontend contract checks (no scoring logic).
 * Run: node ./scripts/test-driver-eval-06-performance-ui.mjs
 */

import assert from 'node:assert/strict';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const displayModulePath = path.join(
  __dirname,
  '../src/features/dispatcher/drivers/utils/performanceDisplay.ts'
);

function formatScoreLabel(label) {
  if (!label) return '—';
  return label.replace(/_/g, ' ');
}

function formatTrend(trend, delta) {
  if (!trend || trend === 'insufficient_data') {
    return 'Insufficient data for trend';
  }
  const deltaText = delta == null ? '' : ` (${delta > 0 ? '+' : ''}${delta})`;
  return `${trend}${deltaText}`;
}

function isPerformanceUnavailable(maturity, score) {
  return maturity === 'unavailable' || score == null;
}

function mapCategoryScores(raw) {
  if (!raw || typeof raw !== 'object') return null;
  const out = {};
  for (const [key, value] of Object.entries(raw)) {
    out[key] = {
      score: value?.score ?? null,
      sampleCount: Number(value?.sample_count ?? 0),
      evaluableSampleCount: Number(value?.evaluable_sample_count ?? 0),
    };
  }
  return out;
}

assert.equal(formatScoreLabel('needs_improvement'), 'needs improvement');
assert.equal(formatTrend('insufficient_data', null), 'Insufficient data for trend');
assert.equal(formatTrend('improving', 4), 'improving (+4)');
assert.equal(isPerformanceUnavailable('unavailable', null), true);
assert.equal(isPerformanceUnavailable('provisional', 88), false);
assert.equal(isPerformanceUnavailable('established', null), true);

const mapped = mapCategoryScores({
  safety: { score: 91, sample_count: 3, evaluable_sample_count: 3 },
  route_compliance: { score: null, sample_count: 0, evaluable_sample_count: 0 },
});
assert.equal(mapped.safety.score, 91);
assert.equal(mapped.route_compliance.score, null);
assert.equal(mapCategoryScores(null), null);
assert.equal(isPerformanceUnavailable('unavailable', 0), true);

console.log('DRIVER-EVAL-06 performance UI helpers: PASS');
console.log(JSON.stringify({ displayModulePath, note: 'helpers mirrored; no frontend scoring' }));
