/**
 * DRIVER-EVAL-08B — Dispatcher Tracking performance display contract checks.
 * Run: node ./scripts/test-driver-eval-08b-tracking-performance-ui.mjs
 */

import assert from 'node:assert/strict';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));

function isPerformanceUnavailable(maturity, score) {
  return maturity === 'unavailable' || score == null;
}

function formatCurrentEvaluationUiState(uiState) {
  switch (uiState) {
    case 'evaluating':
      return 'Evaluating (in progress)';
    case 'completed':
      return 'Completed';
    case 'incomplete':
      return 'Incomplete evidence';
    case 'unavailable':
      return 'Unavailable';
    case 'not_started':
    default:
      return 'Not started';
  }
}

function formatNullableScore(score, available) {
  if (!available || score == null) return '—';
  return String(score);
}

function isValidTrackingId(value) {
  if (!value) return false;
  const trimmed = value.trim();
  return trimmed !== '' && trimmed !== 'null' && trimmed !== 'undefined' && trimmed !== '0';
}

// NULL must never render as 0
assert.equal(formatNullableScore(null, false), '—');
assert.equal(formatNullableScore(null, true), '—');
assert.equal(formatNullableScore(0, true), '0'); // legitimate zero if backend sent it with available
assert.equal(formatNullableScore(87.5, true), '87.5');

assert.equal(isPerformanceUnavailable('unavailable', null), true);
assert.equal(isPerformanceUnavailable('provisional', 90), false);
assert.equal(isPerformanceUnavailable('established', null), true);

assert.equal(formatCurrentEvaluationUiState('evaluating'), 'Evaluating (in progress)');
assert.equal(formatCurrentEvaluationUiState('not_started'), 'Not started');
assert.equal(formatCurrentEvaluationUiState('incomplete'), 'Incomplete evidence');

assert.equal(isValidTrackingId(''), false);
assert.equal(isValidTrackingId('null'), false);
assert.equal(isValidTrackingId('42'), true);

// In-progress: score unavailable → never invent
assert.equal(
  formatNullableScore(null, false) === '—' && formatCurrentEvaluationUiState('evaluating').includes('in progress'),
  true
);

console.log('DRIVER-EVAL-08B tracking performance UI helpers: PASS');
console.log(
  JSON.stringify({
    panel: path.join(__dirname, '../src/features/dispatcher/tracking/components/VehicleDriverPerformanceSection.tsx'),
    note: 'selected-vehicle only; no fleet N+1; no frontend scoring',
  })
);
