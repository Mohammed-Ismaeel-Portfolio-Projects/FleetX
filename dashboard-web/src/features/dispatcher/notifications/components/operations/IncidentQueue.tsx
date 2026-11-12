import { AlertCircle, ArrowRight } from 'lucide-react';
import type { Incident, IssuePaginationMeta } from '../../types/notifications.types';
import { displaySsotValue } from '../../utils/issueAdapter';

interface IncidentQueueProps {
  incidents: Incident[];
  selectedIncidentId: string | null;
  onSelect: (incidentId: string) => void;
  pagination: IssuePaginationMeta;
  onPageChange: (page: number) => void;
}

export default function IncidentQueue({
  incidents,
  selectedIncidentId,
  onSelect,
  pagination,
  onPageChange,
}: IncidentQueueProps) {
  const rangeLabel =
    pagination.from != null && pagination.to != null
      ? `${pagination.from}–${pagination.to} of ${pagination.total}`
      : `${incidents.length} on this page`;

  return (
    <section className="rounded-3xl border border-slate-800/80 bg-slate-950/70 p-5 shadow-lg shadow-black/10">
      <div className="mb-4 flex items-center justify-between">
        <div>
          <h2 className="text-lg font-semibold text-white">Issue Queue</h2>
          <p className="text-sm text-slate-400">Issues loaded from the Issues SSOT.</p>
        </div>
        <div className="rounded-full border border-slate-700 bg-slate-900/80 px-3 py-1 text-sm text-slate-300">
          {rangeLabel}
        </div>
      </div>

      <div className="space-y-3">
        {incidents.length === 0 ? (
          <div className="flex min-h-[240px] flex-col items-center justify-center rounded-2xl border border-dashed border-slate-700 bg-slate-900/60 text-center text-sm text-slate-400">
            <AlertCircle className="mb-3 h-8 w-8 text-slate-500" />
            No issues match the current filters.
          </div>
        ) : (
          incidents.map((incident) => {
            const isSelected = incident.id === selectedIncidentId;
            return (
              <button
                key={incident.id}
                type="button"
                onClick={() => onSelect(incident.id)}
                className={`w-full rounded-2xl border p-4 text-left transition ${
                  isSelected
                    ? 'border-sky-500/40 bg-sky-500/10'
                    : 'border-slate-800 bg-slate-900/70 hover:border-slate-600'
                }`}
              >
                <div className="flex items-start justify-between gap-3">
                  <div>
                    <p className="text-sm font-semibold text-white">{incident.id}</p>
                    <p className="mt-1 text-sm text-slate-300">{displaySsotValue(incident.type)}</p>
                  </div>
                  <div className="rounded-full border border-slate-700 px-2.5 py-1 text-xs text-slate-300">
                    {displaySsotValue(incident.status)}
                  </div>
                </div>
                <div className="mt-3 flex flex-wrap gap-2 text-xs text-slate-400">
                  <span className="rounded-full bg-slate-800/80 px-2.5 py-1">
                    {displaySsotValue(incident.driverName)}
                  </span>
                  <span className="rounded-full bg-slate-800/80 px-2.5 py-1">
                    {displaySsotValue(incident.shipmentNumber)}
                  </span>
                </div>
                <div className="mt-3 flex items-center justify-between text-xs text-slate-500">
                  <span>{displaySsotValue(incident.createdAt)}</span>
                  <span className="flex items-center gap-1 text-slate-400">
                    View details <ArrowRight className="h-3.5 w-3.5" />
                  </span>
                </div>
              </button>
            );
          })
        )}
      </div>

      {pagination.last_page > 1 ? (
        <div className="mt-4 flex items-center justify-between border-t border-slate-800 pt-4">
          <p className="text-xs text-slate-500">
            Page {pagination.current_page} of {pagination.last_page}
          </p>
          <div className="flex gap-2">
            <button
              type="button"
              disabled={pagination.current_page <= 1}
              onClick={() => onPageChange(pagination.current_page - 1)}
              className="rounded-lg bg-white/5 px-3 py-1.5 text-xs font-semibold text-slate-400 hover:text-white disabled:cursor-not-allowed disabled:opacity-50"
            >
              Previous
            </button>
            <button
              type="button"
              disabled={pagination.current_page >= pagination.last_page}
              onClick={() => onPageChange(pagination.current_page + 1)}
              className="rounded-lg bg-white/5 px-3 py-1.5 text-xs font-semibold text-slate-400 hover:text-white disabled:cursor-not-allowed disabled:opacity-50"
            >
              Next
            </button>
          </div>
        </div>
      ) : null}
    </section>
  );
}
