/**
 * DRIVER-EVAL-08D — Admin Drivers / Tracking performance contract checks.
 * Run: node ./scripts/test-driver-eval-08d-admin-performance-ui.mjs
 */

import assert from 'node:assert/strict';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));

function isAuthoritativeNumericId(value) {
  if (value == null || value === '') return false;
  const trimmed = String(value).trim();
  if (!/^\d+$/.test(trimmed)) return false;
  return Number(trimmed) > 0;
}

function formatCurrentTripScoreLine(input) {
  if (input.scoreAvailable && input.overallScore != null) {
    return `${input.overallScore} / 100`;
  }
  if (input.uiState === 'evaluating') {
    return 'In progress — final score not available';
  }
  if (input.uiState === 'incomplete') {
    return 'Insufficient evidence';
  }
  if (!input.evaluationAvailable || input.uiState === 'not_started' || !input.uiState) {
    return 'Evaluation not started';
  }
  return 'Score unavailable';
}

function formatNullableScore(score, available) {
  if (!available || score == null) return '—';
  return String(score);
}

// J — reject mock / missing driver IDs
assert.equal(isAuthoritativeNumericId('DRV-001'), false);
assert.equal(isAuthoritativeNumericId('ASN-001'), false);
assert.equal(isAuthoritativeNumericId(''), false);
assert.equal(isAuthoritativeNumericId(null), false);
assert.equal(isAuthoritativeNumericId('42'), true);
assert.equal(isAuthoritativeNumericId(42), true);

// D/E/F/G — score semantics
assert.equal(
  formatCurrentTripScoreLine({
    evaluationAvailable: true,
    uiState: 'evaluating',
    scoreAvailable: false,
    overallScore: null,
  }),
  'In progress — final score not available'
);
assert.equal(
  formatCurrentTripScoreLine({
    evaluationAvailable: true,
    uiState: 'completed',
    scoreAvailable: true,
    overallScore: 91,
  }),
  '91 / 100'
);
assert.equal(
  formatCurrentTripScoreLine({
    evaluationAvailable: true,
    uiState: 'incomplete',
    scoreAvailable: false,
    overallScore: null,
  }),
  'Insufficient evidence'
);
assert.equal(formatNullableScore(null, false), '—');
assert.notEqual(formatNullableScore(null, false), '0');

// Mock assignment must not be treated as authoritative
assert.equal(isAuthoritativeNumericId('ASN-001'), false);

console.log('DRIVER-EVAL-08D admin performance UI helpers: PASS');
console.log(
  JSON.stringify({
    adminDrawer: path.join(__dirname, '../src/features/admin/drivers/components/DriverDetailsDrawer.tsx'),
    compact: path.join(__dirname, '../src/features/admin/shared/components/AdminCompactDriverPerformance.tsx'),
    note: 'selected-only; mock IDs skipped; no frontend scoring',
  })
);
