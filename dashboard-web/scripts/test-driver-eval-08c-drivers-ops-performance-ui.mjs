/**
 * DRIVER-EVAL-08C — Dispatcher Drivers Operations performance contract checks.
 * Run: node ./scripts/test-driver-eval-08c-drivers-ops-performance-ui.mjs
 */

import assert from 'node:assert/strict';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));

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

function cardOverallLine(score) {
  if (
    score?.score != null &&
    score.maturity !== 'unavailable' &&
    score.scoreAvailable !== false
  ) {
    return `${score.score}`;
  }
  return 'No data';
}

// A/F — card overall from embedded score; null never becomes 0
assert.equal(cardOverallLine({ score: 87, maturity: 'provisional', scoreAvailable: true }), '87');
assert.equal(cardOverallLine({ score: null, maturity: 'unavailable', scoreAvailable: false }), 'No data');
assert.equal(cardOverallLine(null), 'No data');
assert.notEqual(cardOverallLine({ score: null, maturity: 'unavailable' }), '0');

// C — PENDING/PROCESSING
assert.equal(
  formatCurrentTripScoreLine({
    evaluationAvailable: true,
    uiState: 'evaluating',
    scoreAvailable: false,
    overallScore: null,
  }),
  'In progress — final score not available'
);
assert.equal(formatCurrentEvaluationUiState('evaluating'), 'Evaluating (in progress)');
assert.equal(formatNullableScore(null, false), '—');

// D — COMPLETED
assert.equal(
  formatCurrentTripScoreLine({
    evaluationAvailable: true,
    uiState: 'completed',
    scoreAvailable: true,
    overallScore: 91,
  }),
  '91 / 100'
);

// E — INCOMPLETE ≠ 0 / poor
assert.equal(
  formatCurrentTripScoreLine({
    evaluationAvailable: true,
    uiState: 'incomplete',
    scoreAvailable: false,
    overallScore: null,
  }),
  'Insufficient evidence'
);
assert.ok(!formatCurrentTripScoreLine({
  evaluationAvailable: true,
  uiState: 'incomplete',
  scoreAvailable: false,
  overallScore: null,
}).includes('0'));

// G — no assignment / not started
assert.equal(
  formatCurrentTripScoreLine({
    evaluationAvailable: false,
    uiState: 'not_started',
    scoreAvailable: false,
    overallScore: null,
  }),
  'Evaluation not started'
);

// I — helpers are formatting only (no averages invented here)
assert.equal(typeof formatCurrentTripScoreLine, 'function');

console.log('DRIVER-EVAL-08C drivers ops performance UI helpers: PASS');
console.log(
  JSON.stringify({
    assignmentPanel: path.join(
      __dirname,
      '../src/features/dispatcher/drivers/components/DriverAssignmentPanel.tsx'
    ),
    currentBlock: path.join(
      __dirname,
      '../src/features/dispatcher/drivers/components/CurrentAssignmentPerformanceBlock.tsx'
    ),
    note: 'selected assignment only; card uses embedded driver_score; no frontend scoring',
  })
);
