import { Activity, AlertCircle, CheckCircle, Clock, Info } from 'lucide-react';
import type { ActivityItem } from '../../dashboard/services/dashboard.service';
import { cn } from '../../../../lib/utils';
import { dispatcherDesignTokens } from '../../../../shared/constants/dispatcherDesignSystem';

interface OperationalEventsProps {
  items: ActivityItem[];
  sourceError?: string | null;
}

function formatTimestamp(timestamp: string): string {
  const ms = new Date(timestamp).getTime();
  if (!Number.isFinite(ms)) return 'Unavailable';
  return new Date(ms).toLocaleString([], {
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
}

function formatEventType(type: string): string {
  const normalized = type.trim().toLowerCase();
  if (normalized === 'audit') return 'Audit';
  if (normalized === 'tracking') return 'Tracking';
  if (normalized === 'issue') return 'Issue';
  return type.trim() || 'Unavailable';
}

function ActivityIcon({ icon }: { icon: string }) {
  switch (icon) {
    case 'success':
      return <CheckCircle className="h-3.5 w-3.5 text-emerald-400" aria-hidden />;
    case 'warning':
      return <AlertCircle className="h-3.5 w-3.5 text-amber-400" aria-hidden />;
    case 'error':
      return <AlertCircle className="h-3.5 w-3.5 text-red-400" aria-hidden />;
    case 'info':
      return <Info className="h-3.5 w-3.5 text-sky-400" aria-hidden />;
    default:
      return <Clock className="h-3.5 w-3.5 text-slate-500" aria-hidden />;
  }
}

/** Dashboard activity timeline — operational events, not delivery-chat notifications. */
export function OperationalEvents({ items, sourceError = null }: OperationalEventsProps) {
  return (
    <div className={cn(dispatcherDesignTokens.surface, 'p-4 md:p-5')}>
      <div className="flex items-center gap-2">
        <Activity className="h-4 w-4 text-sky-300" aria-hidden />
        <h2 className="text-lg font-semibold text-white">Operational Events</h2>
      </div>
      <p className="mt-1 text-sm text-slate-500">
        Recent activity from the dashboard timeline (audit / tracking / issue).
      </p>

      {sourceError ? (
        <p className="mt-3 rounded-2xl border border-amber-500/20 bg-amber-500/10 px-3 py-2 text-sm text-amber-100">
          Unavailable — {sourceError}
        </p>
      ) : null}

      <div className="mt-4 max-h-[280px] space-y-0 overflow-y-auto overscroll-contain divide-y divide-white/5 pr-1">
        {!sourceError && items.length === 0 ? (
          <div className="rounded-2xl border border-dashed border-white/10 px-4 py-8 text-center text-sm text-slate-500">
            <p>No recent operational events from the activity timeline.</p>
            <p className="mt-2 text-slate-600">
              Use Attention Queue for Issues, or open Alerts Center / Active Tracking for live ops.
            </p>
          </div>
        ) : null}

        {items.map((item) => (
          <div key={`${item.type}-${item.id}-${item.timestamp}`} className="flex items-start gap-3 py-3 first:pt-0 last:pb-0">
            <div className="mt-0.5 shrink-0">
              <ActivityIcon icon={item.icon} />
            </div>
            <div className="min-w-0 flex-1">
              <p className="truncate text-sm font-medium text-slate-200 sm:whitespace-normal sm:break-words">
                {item.description?.trim() || item.message?.trim() || 'Unavailable'}
              </p>
              <div className="mt-1 flex flex-wrap gap-x-3 gap-y-1 text-xs text-slate-500">
                <span className="tabular-nums">{formatTimestamp(item.timestamp)}</span>
                {item.type ? <span>{formatEventType(item.type)}</span> : null}
              </div>
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}
