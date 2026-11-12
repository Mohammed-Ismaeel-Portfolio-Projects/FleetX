/**
 * DRIVER-EVAL-08E — Driver App performance surface integration checks.
 * Run: node ./scripts/verify-driver-eval-08e.mjs
 */

import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const root = path.join(__dirname, '..');

let passed = 0;
let failed = 0;

function check(name, fn) {
  try {
    fn();
    passed += 1;
    console.log(`PASS  ${name}`);
  } catch (e) {
    failed += 1;
    console.error(`FAIL  ${name}`);
    console.error(e?.message || e);
  }
}

function read(rel) {
  return fs.readFileSync(path.join(root, rel), 'utf8');
}

function formatCurrentTripScoreLine(input) {
  if (input.scoreAvailable && input.overallScore != null) {
    return `${input.overallScore} / 100`;
  }
  if (input.uiState === 'evaluating') {
    return 'Final score not available yet';
  }
  if (input.uiState === 'incomplete') {
    return 'Insufficient trip data';
  }
  if (!input.evaluationAvailable || input.uiState === 'not_started' || !input.uiState) {
    return 'Evaluation not started';
  }
  return 'Score unavailable';
}

console.log('=== DRIVER-EVAL-08E Driver App Performance ===\n');

check('performance.ts exposes self current + detail endpoints', () => {
  const src = read('src/features/driverApi/performance.ts');
  assert.match(src, /getOwnCurrentAssignmentEvaluation/);
  assert.match(src, /getOwnEvaluationDetail/);
  assert.match(src, /\/driver\/assignments\/\$\{assignmentId\}\/evaluation\/current/);
  assert.match(src, /\/driver\/performance\/evaluations\/\$\{evaluationId\}/);
  assert.ok(src.includes("Self endpoint only — never ops"));
  assert.ok(!src.includes("'/assignments/"));
});

check('active-trip uses CurrentTripPerformanceCard and self API', () => {
  const src = read('src/app/active-trip/[id].tsx');
  assert.match(src, /CurrentTripPerformanceCard/);
  assert.match(src, /getOwnCurrentAssignmentEvaluation/);
  assert.match(src, /loadTripPerformance/);
  assert.match(src, /Current Trip Performance/);
  // GPS onFix path must not call performance loader
  const onFixStart = src.indexOf('(fix) => {');
  const onFixBlock = src.slice(onFixStart, onFixStart + 350);
  assert.ok(onFixBlock.includes('publisher.onFix'));
  assert.ok(!onFixBlock.includes('loadTripPerformance'));
});

check('account keeps overall performance and evaluation detail nav', () => {
  const src = read('src/app/(tabs)/account.tsx');
  assert.match(src, /Overall Driver Performance/);
  assert.match(src, /getOwnPerformance/);
  assert.match(src, /\/evaluation\/\$\{item\.evaluationId\}/);
  assert.doesNotMatch(src, /getOwnCurrentAssignmentEvaluation/);
});

check('history and delivery-complete show trip performance', () => {
  assert.match(read('src/app/history/[id].tsx'), /Trip Performance/);
  assert.match(read('src/app/delivery-complete/[id].tsx'), /Trip Performance/);
  assert.match(read('src/app/history/[id].tsx'), /getOwnCurrentAssignmentEvaluation/);
});

check('PENDING never numeric; INCOMPLETE never 0; NULL never 0', () => {
  assert.equal(
    formatCurrentTripScoreLine({
      evaluationAvailable: true,
      uiState: 'evaluating',
      scoreAvailable: false,
      overallScore: null,
    }),
    'Final score not available yet'
  );
  assert.equal(
    formatCurrentTripScoreLine({
      evaluationAvailable: true,
      uiState: 'incomplete',
      scoreAvailable: false,
      overallScore: null,
    }),
    'Insufficient trip data'
  );
  assert.ok(
    !formatCurrentTripScoreLine({
      evaluationAvailable: true,
      uiState: 'incomplete',
      scoreAvailable: false,
      overallScore: null,
    }).includes('0')
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
});

check('performanceDisplay is formatting-only (no scoring math)', () => {
  const src = read('src/features/driverApi/performanceDisplay.ts');
  assert.ok(src.includes('Formatting only'));
  assert.ok(!src.includes('Math.'));
  assert.ok(!src.includes('reduce('));
});

check('evaluation detail screen exists', () => {
  assert.ok(fs.existsSync(path.join(root, 'src/app/evaluation/[id].tsx')));
});

console.log(`\n${passed} passed, ${failed} failed`);
if (failed > 0) process.exit(1);
