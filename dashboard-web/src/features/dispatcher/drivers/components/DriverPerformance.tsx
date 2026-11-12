import { useEffect, useState } from 'react';
import { Bar, BarChart, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';
import { BarChart3 } from 'lucide-react';
import type { Driver, EvaluationDetail, EvaluationHistoryItem } from '../types/driver-operations.types';
import {
  getDriverEvaluationDetail,
  getDriverEvaluations,
  getDriverPerformance,
} from '../services/driver-operations.service';
import {
  categoryDisplayName,
  formatScoreLabel,
  formatTrend,
  isPerformanceUnavailable,
} from '../utils/performanceDisplay';

interface DriverPerformanceProps {
  driver: Driver | null;
}

function Field({ label, value }: { label: string; value: string }) {
  return (
    <div className="flex items-center justify-between gap-3">
      <span className="text-xs text-slate-400">{label}</span>
      <span className="text-sm font-bold text-white capitalize">{value}</span>
    </div>
  );
}

export default function DriverPerformance({ driver }: DriverPerformanceProps) {
  const [history, setHistory] = useState<EvaluationHistoryItem[]>([]);
  const [detail, setDetail] = useState<EvaluationDetail | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);
  const [perf, setPerf] = useState(driver?.score ?? null);

  useEffect(() => {
    if (!driver) {
      setHistory([]);
      setDetail(null);
      setPerf(null);
      return;
    }

    let cancelled = false;
    setLoading(true);
    setError(null);
    setDetail(null);

    void (async () => {
      try {
        const [summary, evaluations] = await Promise.all([
          getDriverPerformance(driver.id),
          getDriverEvaluations(driver.id, 1, 8),
        ]);
        if (cancelled) return;
        setPerf(summary);
        setHistory(evaluations.items);
      } catch (e) {
        if (cancelled) return;
        setPerf(driver.score);
        setError(e instanceof Error ? e.message : 'Could not load performance');
      } finally {
        if (!cancelled) setLoading(false);
      }
    })();

    return () => {
      cancelled = true;
    };
  }, [driver]);

  if (!driver) {
    return (
      <div className="bg-[#0E1320] border border-white/5 rounded-2xl shadow-lg p-8 flex flex-col items-center justify-center h-full">
        <BarChart3 className="w-16 h-16 text-slate-600 mb-4" />
        <p className="text-sm text-slate-400">Select a driver to view performance</p>
      </div>
    );
  }

  const unavailable = !perf || isPerformanceUnavailable(perf.maturity, perf.score);
  const chartData = perf?.categoryScores
    ? Object.entries(perf.categoryScores).map(([key, value]) => ({
        name: categoryDisplayName(key),
        score: value.score ?? 0,
        hasScore: value.score != null,
      }))
    : [];

  return (
    <div className="bg-[#0E1320] border border-white/5 rounded-2xl shadow-lg flex flex-col h-full min-h-0">
      <div className="p-5 border-b border-white/5">
        <h2 className="text-sm font-bold text-white font-sans flex items-center gap-2">
          <BarChart3 className="w-4 h-4 text-[#00E5FF]" />
          Overall Driver Performance
        </h2>
      </div>
      <div className="flex-1 overflow-y-auto p-5 space-y-4">
        {loading && <p className="text-xs text-slate-500">Loading performance…</p>}
        {error && <p className="text-xs text-amber-400">{error}</p>}

        {unavailable ? (
          <p className="text-sm text-slate-400">No evaluation data available yet.</p>
        ) : (
          <>
            <div className="bg-white/[0.02] border border-white/5 rounded-xl p-4 space-y-3">
              <Field label="Overall score" value={perf.score != null ? String(perf.score) : '—'} />
              <Field label="Label" value={formatScoreLabel(perf.scoreLabel)} />
              <Field label="Confidence" value={perf.confidence ?? '—'} />
              <Field label="Maturity" value={perf.maturity ?? '—'} />
              <Field label="Trend" value={formatTrend(perf.trend, perf.trendDelta)} />
              <Field label="Evaluated assignments" value={String(perf.evaluatedAssignmentsCount)} />
              <Field label="Incomplete evaluations" value={String(perf.incompleteAssignmentsCount)} />
              <Field label="Late evaluations" value={String(perf.lateEvaluationsCount)} />
              <Field label="Last evaluated" value={perf.lastEvaluatedAt ?? '—'} />
            </div>

            {chartData.some((row) => row.hasScore) ? (
              <div className="h-48 rounded-xl border border-white/5 bg-white/[0.02] p-2">
                <ResponsiveContainer width="100%" height="100%">
                  <BarChart data={chartData.filter((row) => row.hasScore)}>
                    <XAxis dataKey="name" tick={{ fill: '#94a3b8', fontSize: 10 }} interval={0} angle={-20} textAnchor="end" height={50} />
                    <YAxis domain={[0, 100]} tick={{ fill: '#94a3b8', fontSize: 10 }} />
                    <Tooltip
                      contentStyle={{ background: '#0E1320', border: '1px solid rgba(255,255,255,0.1)' }}
                      labelStyle={{ color: '#fff' }}
                    />
                    <Bar dataKey="score" fill="#00E5FF" radius={[4, 4, 0, 0]} />
                  </BarChart>
                </ResponsiveContainer>
              </div>
            ) : (
              <p className="text-xs text-slate-500">Insufficient data for category chart</p>
            )}
          </>
        )}

        <div>
          <h3 className="text-[10px] font-bold uppercase tracking-widest text-slate-500 mb-2">Evaluation history</h3>
          {history.length === 0 ? (
            <p className="text-xs text-slate-500">No finalized evaluations</p>
          ) : (
            <ul className="space-y-2">
              {history.map((item) => (
                <li key={item.evaluationId}>
                  <button
                    type="button"
                    onClick={() => {
                      void getDriverEvaluationDetail(driver.id, item.evaluationId)
                        .then(setDetail)
                        .catch(() => setDetail(null));
                    }}
                    className="w-full text-left rounded-lg border border-white/5 bg-white/[0.02] px-3 py-2 hover:border-[#00E5FF]/30"
                  >
                    <div className="flex items-center justify-between gap-2">
                      <span className="text-xs text-white">
                        {item.assignmentCode || `Assignment #${item.assignmentId}`}
                      </span>
                      <span className="text-xs font-bold text-[#00E5FF]">
                        {item.overallScore != null ? item.overallScore : 'Incomplete'}
                      </span>
                    </div>
                    <p className="text-[10px] text-slate-500 mt-1">
                      {item.finalizedAt ?? '—'} · {item.confidence ?? '—'} · rev {item.revision}
                    </p>
                  </button>
                </li>
              ))}
            </ul>
          )}
        </div>

        {detail && (
          <div className="rounded-xl border border-[#00E5FF]/20 bg-[#00E5FF]/5 p-4 space-y-2">
            <h3 className="text-xs font-bold text-white">Why this score?</h3>
            <Field label="Overall" value={detail.overallScore != null ? String(detail.overallScore) : '—'} />
            <Field label="Label" value={formatScoreLabel(detail.scoreLabel)} />
            <Field label="Confidence" value={detail.confidence ?? '—'} />
            <Field label="Evidence" value={detail.evidenceQuality ?? '—'} />
            <Field label="Scoring version" value={detail.scoringVersion ?? '—'} />
            {detail.categoryScores &&
              Object.entries(detail.categoryScores).map(([key, value]) => {
                const row = value as { evaluable?: boolean; score?: number | null; configured_weight?: number };
                if (!row?.evaluable) return null;
                const weight = row.configured_weight != null ? ` · weight ${(row.configured_weight * 100).toFixed(0)}%` : '';
                return (
                  <Field
                    key={key}
                    label={categoryDisplayName(key)}
                    value={`${row.score ?? '—'}${weight}`}
                  />
                );
              })}
            {detail.events.length > 0 && (
              <div className="pt-2 border-t border-white/5">
                <p className="text-[10px] uppercase tracking-widest text-slate-500 mb-1">Events</p>
                {detail.events.map((event) => (
                  <p key={event.id} className="text-xs text-slate-300">
                    {event.eventCode} · {event.severity} · {event.occurredAt}
                  </p>
                ))}
              </div>
            )}
          </div>
        )}
      </div>
    </div>
  );
}
