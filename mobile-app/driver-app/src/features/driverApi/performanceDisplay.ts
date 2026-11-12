/**
 * Driver App presentation helpers for performance (DRIVER-EVAL-08E).
 * Formatting only — never scores, aggregates, or invents values.
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

export function formatCurrentEvaluationUiState(uiState: string | null | undefined): string {
  switch (uiState) {
    case 'evaluating':
      return 'Evaluation in progress';
    case 'completed':
      return 'Completed';
    case 'incomplete':
      return 'Insufficient trip data';
    case 'unavailable':
      return 'Unavailable';
    case 'not_started':
    default:
      return 'Evaluation not started';
  }
}

/** Never coerce null → 0. */
export function formatNullableScore(score: number | null | undefined, available: boolean): string {
  if (!available || score == null) return '—';
  return String(score);
}

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

export function friendlyEventCode(code: string | null | undefined): string {
  if (!code) return 'Event';
  return code.replace(/_/g, ' ');
}
