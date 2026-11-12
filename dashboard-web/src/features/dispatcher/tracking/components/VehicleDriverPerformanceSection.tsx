import { BarChart3, Route } from 'lucide-react';
import type { LiveVehicle } from '../types/tracking.types';
import { useVehicleDriverPerformance } from '../hooks/useVehicleDriverPerformance';
import {
  formatCurrentEvaluationUiState,
  formatNullableScore,
  formatScoreLabel,
  formatTrend,
  isPerformanceUnavailable,
} from '../../drivers/utils/performanceDisplay';

interface VehicleDriverPerformanceSectionProps {
  vehicle: LiveVehicle;
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
 * Compact overall + current assignment evaluation for selected vehicle (DRIVER-EVAL-08B).
 * Presentation only — no local scoring/aggregation.
 */
export default function VehicleDriverPerformanceSection({ vehicle }: VehicleDriverPerformanceSectionProps) {
  const { overall, current, loading, error, hasDriver, hasAssignment } = useVehicleDriverPerformance(
    vehicle.driverId,
    vehicle.assignmentId
  );

  if (!hasDriver) {
    return (
      <div className="bg-white/[0.02] border border-white/5 rounded-xl p-4">
        <h3 className="text-xs font-bold text-slate-400 uppercase tracking-wider mb-2 flex items-center gap-2">
          <BarChart3 className="w-4 h-4" />
          Driver Performance
        </h3>
        <p className="text-sm text-slate-400">No driver assigned</p>
      </div>
    );
  }

  const overallUnavailable =
    !overall || isPerformanceUnavailable(overall.maturity, overall.overallScore ?? overall.score);

  return (
    <div className="space-y-4" data-testid="vehicle-driver-performance">
      <div className="bg-white/[0.02] border border-white/5 rounded-xl p-4">
        <h3 className="text-xs font-bold text-slate-400 uppercase tracking-wider mb-4 flex items-center gap-2">
          <BarChart3 className="w-4 h-4" />
          Overall Driver Performance
        </h3>
        {loading && <p className="text-xs text-slate-500 mb-2">Loading performance…</p>}
        {error && <p className="text-xs text-amber-400 mb-2">{error}</p>}
        {!loading && !error && overallUnavailable ? (
          <p className="text-sm text-slate-400">No evaluation data available yet.</p>
        ) : !overallUnavailable && overall ? (
          <div className="space-y-2">
            <Field
              label="Score"
              value={formatNullableScore(overall.overallScore ?? overall.score, overall.scoreAvailable)}
            />
            <Field label="Label" value={formatScoreLabel(overall.scoreLabel)} />
            <Field label="Maturity" value={overall.maturity ?? '—'} />
            <Field label="Trend" value={formatTrend(overall.trend, overall.trendDelta)} />
            <Field label="Confidence" value={overall.confidence ?? '—'} />
          </div>
        ) : null}
      </div>

      <div className="bg-white/[0.02] border border-white/5 rounded-xl p-4">
        <h3 className="text-xs font-bold text-slate-400 uppercase tracking-wider mb-4 flex items-center gap-2">
          <Route className="w-4 h-4" />
          Current Assignment Performance
        </h3>
        {!hasAssignment ? (
          <p className="text-sm text-slate-400">No active assignment</p>
        ) : loading && !current ? (
          <p className="text-xs text-slate-500">Loading evaluation…</p>
        ) : current ? (
          <div className="space-y-2">
            <Field label="State" value={formatCurrentEvaluationUiState(current.uiState)} />
            <Field label="Status" value={current.status ?? '—'} />
            <Field
              label="Trip score"
              value={
                current.scoreAvailable
                  ? formatNullableScore(current.overallScore, true)
                  : current.uiState === 'evaluating'
                    ? 'In progress — not final'
                    : '—'
              }
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
        ) : !error ? (
          <p className="text-sm text-slate-400">No evaluation data</p>
        ) : null}
      </div>
    </div>
  );
}
