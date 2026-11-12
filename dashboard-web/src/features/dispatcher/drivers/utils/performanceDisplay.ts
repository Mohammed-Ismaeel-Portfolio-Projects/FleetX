/**
 * Pure display helpers for driver performance (DRIVER-EVAL-06).
 * No scoring or aggregation — formatting only.
 */

export function formatScoreLabel(label: string | null | undefined): string {
  if (!label) return '—';
  return label.replace(/_/g, ' ');
}

export function formatTrend(trend: string | null | undefined, delta: number | null | undefined): string {
  if (!trend || trend === 'insufficient_data') {
    return 'Insufficient data for trend';
  }
  const deltaText = delta == null ? '' : ` (${delta > 0 ? '+' : ''}${delta})`;
  return `${trend}${deltaText}`;
}

export function categoryDisplayName(key: string): string {
  return key.replace(/_/g, ' ');
}

export function isPerformanceUnavailable(maturity: string | null | undefined, score: number | null | undefined): boolean {
  return maturity === 'unavailable' || score == null;
}

/** Human label for current-assignment evaluation ui_state (DRIVER-EVAL-08A/08B). */
export function formatCurrentEvaluationUiState(uiState: string | null | undefined): string {
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

/** Never render null overall_score as 0 for in-progress evaluations. */
export function formatNullableScore(score: number | null | undefined, available: boolean): string {
  if (!available || score == null) return '—';
  return String(score);
}

/**
 * True only for backend numeric identity (e.g. driver_id=42).
 * Rejects mock codes like DRV-001 and empty/null strings.
 */
export function isAuthoritativeNumericId(value: string | number | null | undefined): boolean {
  if (value == null || value === '') return false;
  const trimmed = String(value).trim();
  if (!/^\d+$/.test(trimmed)) return false;
  return Number(trimmed) > 0;
}

/** Trip score line for current AssignmentEvaluation (DRIVER-EVAL-08B/08C). Never invents scores. */
export function formatCurrentTripScoreLine(input: {
  evaluationAvailable?: boolean;
  uiState: string | null | undefined;
  scoreAvailable: boolean;
  overallScore: number | null | undefined;
}): string {
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

/** Map API category_scores object without inventing scores. */
export function mapCategoryScores(
  raw: Record<string, { score?: number | null; sample_count?: number; evaluable_sample_count?: number }> | null | undefined
): Record<string, { score: number | null; sampleCount: number; evaluableSampleCount: number }> | null {
  if (!raw || typeof raw !== 'object') return null;
  const out: Record<string, { score: number | null; sampleCount: number; evaluableSampleCount: number }> = {};
  for (const [key, value] of Object.entries(raw)) {
    out[key] = {
      score: value?.score ?? null,
      sampleCount: Number(value?.sample_count ?? 0),
      evaluableSampleCount: Number(value?.evaluable_sample_count ?? 0),
    };
  }
  return out;
}
