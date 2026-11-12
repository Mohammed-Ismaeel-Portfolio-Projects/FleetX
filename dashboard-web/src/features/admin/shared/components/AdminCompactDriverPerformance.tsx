import { useEffect, useState } from 'react';
import { BarChart3, Route } from 'lucide-react';
import {
  getCurrentAssignmentEvaluation,
  getOverallPerformanceCompact,
  TrackingPerformanceApiError,
} from '../../../dispatcher/tracking/services/driver-performance.service';
import type {
  CurrentAssignmentEvaluationView,
  OverallPerformanceCompact,
} from '../../../dispatcher/tracking/types/driver-performance.types';
import {
  formatCurrentEvaluationUiState,
  formatCurrentTripScoreLine,
  formatNullableScore,
  formatScoreLabel,
  formatTrend,
  isAuthoritativeNumericId,
  isPerformanceUnavailable,
} from '../../../dispatcher/drivers/utils/performanceDisplay';

interface AdminCompactDriverPerformanceProps {
  driverId: string | number | null | undefined;
  /** Only pass when authoritative (never mock ASN-* codes). */
  assignmentId?: string | number | null;
  /** When false, only overall is shown. */
  includeCurrentAssignment?: boolean;
}

function Field({ label, value }: { label: string; value: string }) {
  return (
    <div className="flex items-center justify-between gap-3">
      <span className="text-xs text-slate-400">{label}</span>
      <span className="text-xs font-bold text-white text-right capitalize">{value}</span>
    </div>
  );
}

/**
 * Compact Admin driver performance (DRIVER-EVAL-08D).
 * Selected context only — skips non-authoritative / mock driver IDs.
 */
export default function AdminCompactDriverPerformance({
  driverId,
  assignmentId = null,
  includeCurrentAssignment = true,
}: AdminCompactDriverPerformanceProps) {
  const hasDriver = isAuthoritativeNumericId(driverId);
  const hasAssignment = isAuthoritativeNumericId(assignmentId);

  const [overall, setOverall] = useState<OverallPerformanceCompact | null>(null);
  const [current, setCurrent] = useState<CurrentAssignmentEvaluationView | null>(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (!hasDriver) {
      setOverall(null);
      setCurrent(null);
      setError(null);
      setLoading(false);
      return;
    }

    let cancelled = false;
    setLoading(true);
    setError(null);

    void (async () => {
      try {
        const [overallResult, currentResult] = await Promise.all([
          getOverallPerformanceCompact(String(driverId)),
          includeCurrentAssignment && hasAssignment
            ? getCurrentAssignmentEvaluation(String(assignmentId))
            : Promise.resolve(null),
        ]);
        if (cancelled) return;
        setOverall(overallResult);
        setCurrent(currentResult);
      } catch (e) {
        if (cancelled) return;
        setOverall(null);
        setCurrent(null);
        setError(
          e instanceof TrackingPerformanceApiError
            ? e.message
            : e instanceof Error
              ? e.message
              : 'Performance unavailable'
        );
      } finally {
        if (!cancelled) setLoading(false);
      }
    })();

    return () => {
      cancelled = true;
    };
  }, [driverId, assignmentId, hasDriver, hasAssignment, includeCurrentAssignment]);

  if (!hasDriver) {
    return null;
  }

  const overallUnavailable =
    !overall || isPerformanceUnavailable(overall.maturity, overall.overallScore ?? overall.score);

  return (
    <div className="space-y-3" data-testid="admin-compact-driver-performance">
      <div className="rounded-xl border border-white/5 bg-[#0A0E17] p-4">
        <h3 className="mb-3 flex items-center gap-2 text-[10px] font-bold uppercase tracking-widest text-slate-500">
          <BarChart3 className="h-4 w-4" />
          Driver Performance
        </h3>
        {loading && <p className="text-xs text-slate-500">Loading performance…</p>}
        {error && <p className="text-xs text-amber-400">Performance unavailable</p>}
        {!loading && !error && overallUnavailable ? (
          <p className="text-sm text-slate-400">No evaluation data available</p>
        ) : !overallUnavailable && overall ? (
          <div className="space-y-2">
            <Field
              label="Overall score"
              value={formatNullableScore(overall.overallScore ?? overall.score, overall.scoreAvailable)}
            />
            <Field label="Label" value={formatScoreLabel(overall.scoreLabel)} />
            <Field label="Maturity" value={overall.maturity ?? '—'} />
            <Field label="Trend" value={formatTrend(overall.trend, overall.trendDelta)} />
            <Field label="Confidence" value={overall.confidence ?? '—'} />
          </div>
        ) : null}
      </div>

      {includeCurrentAssignment ? (
        <div className="rounded-xl border border-white/5 bg-[#0A0E17] p-4">
          <h3 className="mb-3 flex items-center gap-2 text-[10px] font-bold uppercase tracking-widest text-slate-500">
            <Route className="h-4 w-4" />
            Current Assignment Performance
          </h3>
          {!hasAssignment ? (
            <p className="text-sm text-slate-400">No active assignment</p>
          ) : loading && !current ? (
            <p className="text-xs text-slate-500">Loading current evaluation…</p>
          ) : current ? (
            <div className="space-y-2">
              <Field label="State" value={formatCurrentEvaluationUiState(current.uiState)} />
              <Field
                label="Trip score"
                value={formatCurrentTripScoreLine({
                  evaluationAvailable: current.evaluationAvailable,
                  uiState: current.uiState,
                  scoreAvailable: current.scoreAvailable,
                  overallScore: current.overallScore,
                })}
              />
              {current.scoreAvailable ? (
                <Field label="Label" value={formatScoreLabel(current.scoreLabel)} />
              ) : null}
              <Field label="Evidence" value={current.evidenceQuality ?? '—'} />
              <Field label="Events" value={String(current.eventCount)} />
            </div>
          ) : !error ? (
            <p className="text-sm text-slate-400">Evaluation not available yet</p>
          ) : null}
        </div>
      ) : null}
    </div>
  );
}
