import { useEffect, useState } from 'react';
import { Route } from 'lucide-react';
import {
  getCurrentAssignmentEvaluation,
  TrackingPerformanceApiError,
} from '../../tracking/services/driver-performance.service';
import type { CurrentAssignmentEvaluationView } from '../../tracking/types/driver-performance.types';
import {
  formatCurrentEvaluationUiState,
  formatCurrentTripScoreLine,
  formatScoreLabel,
} from '../utils/performanceDisplay';

interface CurrentAssignmentPerformanceBlockProps {
  assignmentId: string | null | undefined;
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
 * Compact current-assignment evaluation for Dispatcher Drivers (DRIVER-EVAL-08C).
 * Fetches only when assignmentId changes — not on list poll.
 */
export default function CurrentAssignmentPerformanceBlock({
  assignmentId,
}: CurrentAssignmentPerformanceBlockProps) {
  const [current, setCurrent] = useState<CurrentAssignmentEvaluationView | null>(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (!assignmentId) {
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
        const result = await getCurrentAssignmentEvaluation(assignmentId);
        if (cancelled) return;
        setCurrent(result);
      } catch (e) {
        if (cancelled) return;
        setCurrent(null);
        setError(
          e instanceof TrackingPerformanceApiError
            ? e.message
            : e instanceof Error
              ? e.message
              : 'Current evaluation unavailable'
        );
      } finally {
        if (!cancelled) setLoading(false);
      }
    })();

    return () => {
      cancelled = true;
    };
  }, [assignmentId]);

  if (!assignmentId) {
    return (
      <div
        className="bg-white/[0.02] border border-white/5 rounded-xl p-4"
        data-testid="current-assignment-performance"
      >
        <h3 className="text-xs font-bold text-slate-400 uppercase tracking-wider mb-2 flex items-center gap-2">
          <Route className="w-4 h-4" />
          Current Assignment Performance
        </h3>
        <p className="text-sm text-slate-400">No active assignment</p>
      </div>
    );
  }

  return (
    <div
      className="bg-white/[0.02] border border-white/5 rounded-xl p-4"
      data-testid="current-assignment-performance"
    >
      <h3 className="text-xs font-bold text-slate-400 uppercase tracking-wider mb-4 flex items-center gap-2">
        <Route className="w-4 h-4" />
        Current Assignment Performance
      </h3>

      {loading && <p className="text-xs text-slate-500 mb-2">Loading current evaluation…</p>}
      {error && (
        <p className="text-xs text-amber-400 mb-2" data-testid="current-assignment-performance-error">
          Current evaluation unavailable
        </p>
      )}

      {!loading && !error && current ? (
        <div className="space-y-2">
          <Field label="State" value={formatCurrentEvaluationUiState(current.uiState)} />
          {current.evaluationAvailable ? (
            <Field label="Status" value={current.status ?? '—'} />
          ) : null}
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
          {current.recentEvents.length > 0 ? (
            <div className="pt-2 border-t border-white/5 space-y-1">
              <p className="text-[10px] text-slate-500 uppercase tracking-wider">Recent events</p>
              {current.recentEvents.slice(0, 5).map((event) => (
                <p key={event.id} className="text-xs text-slate-300">
                  {(event.eventCode ?? 'event').replace(/_/g, ' ')}
                  {event.severity ? ` · ${event.severity}` : ''}
                </p>
              ))}
            </div>
          ) : null}
        </div>
      ) : null}

      {!loading && !error && !current ? (
        <p className="text-sm text-slate-400">Evaluation not available yet</p>
      ) : null}
    </div>
  );
}

