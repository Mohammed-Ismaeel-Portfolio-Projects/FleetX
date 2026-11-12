import type { ShipmentRequestStatusHistoryItem } from '../types/request.types';
import { formatRequestStatusLabel } from '../utils/requestMapper';
import ReviewSection from './ReviewSection';

interface RequestStatusHistoryPanelProps {
  history: ShipmentRequestStatusHistoryItem[];
}

export default function RequestStatusHistoryPanel({ history }: RequestStatusHistoryPanelProps) {
  return (
    <ReviewSection title="Status history" subtitle="Chronological transitions from the existing request API.">
      {history.length === 0 ? (
        <p className="text-sm text-slate-400">No history rows available for this request.</p>
      ) : (
        <ol className="space-y-3">
          {history.map((row, index) => (
            <li key={String(row.id)} className="relative rounded-xl border border-white/5 bg-white/[0.02] px-3 py-3">
              <div className="flex flex-wrap items-center justify-between gap-2">
                <p className="text-sm font-semibold text-slate-100">
                  {formatRequestStatusLabel(row.fromStatus || '—')}
                  <span className="mx-2 text-slate-500">→</span>
                  {formatRequestStatusLabel(row.toStatus || '—')}
                </p>
                <span className="font-mono text-[11px] text-slate-500">{row.createdAt || '—'}</span>
              </div>
              <p className="mt-1 text-xs text-slate-400">
                Actor: {row.changedBy?.name || 'Unavailable'}
                {row.changedBy?.email ? ` · ${row.changedBy.email}` : ''}
              </p>
              {row.reason ? (
                <p className="mt-2 text-xs text-slate-300">Reason: {row.reason}</p>
              ) : null}
              <p className="mt-2 text-[10px] uppercase tracking-wider text-slate-600">Step {index + 1}</p>
            </li>
          ))}
        </ol>
      )}
    </ReviewSection>
  );
}
