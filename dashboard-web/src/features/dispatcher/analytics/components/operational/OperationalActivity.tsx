import { AlertCircle, AlertTriangle, CheckCircle, Clock, Info, Radio, Shield } from 'lucide-react';
import type { ActivityItem } from '../../../dashboard/services/dashboard.service';
import { cn } from '../../../../../lib/utils';
import { analyticsUi } from './analyticsUi';

interface OperationalActivityProps {
  items: ActivityItem[];
  status: 'loading' | 'ready' | 'empty' | 'error';
  error: string | null;
  onRetry: () => void;
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

function typeVisual(type: string): {
  chip: string;
  icon: string;
  Icon: typeof Shield;
} {
  const normalized = type.trim().toLowerCase();
  if (normalized === 'issue') {
    return {
      chip: 'border-rose-400/25 bg-rose-500/10 text-rose-200',
      icon: 'text-rose-300',
      Icon: AlertTriangle,
    };
  }
  if (normalized === 'tracking') {
    return {
      chip: 'border-sky-400/25 bg-sky-500/10 text-sky-200',
      icon: 'text-sky-300',
      Icon: Radio,
    };
  }
  if (normalized === 'audit') {
    return {
      chip: 'border-slate-400/25 bg-slate-500/10 text-slate-300',
      icon: 'text-slate-400',
      Icon: Shield,
    };
  }
  return {
    chip: 'border-white/10 bg-white/5 text-slate-400',
    icon: 'text-slate-500',
    Icon: Clock,
  };
}

function ActivityStatusIcon({ icon }: { icon: string }) {
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

/** Activity feed body — section title is owned by the page hierarchy. */
export function OperationalActivity({ items, status, error, onRetry }: OperationalActivityProps) {
  return (
    <div className={cn(analyticsUi.panelQuiet, 'p-5 md:p-6')}>
      {status === 'loading' ? (
        <div className="space-y-3">
          {Array.from({ length: 4 }).map((_, index) => (
            <div key={index} className="h-14 animate-pulse rounded-2xl border border-white/10 bg-white/[0.03]" />
          ))}
        </div>
      ) : null}

      {status === 'error' ? (
        <div className={analyticsUi.bannerError}>
          <p>{error?.trim() || 'Unable to load operational activity.'}</p>
          <button type="button" onClick={onRetry} className={cn(analyticsUi.buttonPrimary, 'mt-3')}>
            Retry
          </button>
        </div>
      ) : null}

      {status === 'empty' ? (
        <div className="rounded-2xl border border-dashed border-white/10 bg-white/[0.02] px-6 py-10 text-center text-sm text-slate-500">
          No operational events returned by the activity endpoint.
        </div>
      ) : null}

      {status === 'ready' ? (
        <div className="relative max-h-[320px] overflow-y-auto overscroll-contain pr-1">
          <div className="absolute bottom-0 left-[15px] top-0 w-px bg-white/[0.06]" aria-hidden />
          <ul className="relative space-y-0">
            {items.map((item) => {
              const visual = typeVisual(item.type || '');
              const TypeIcon = visual.Icon;
              return (
                <li
                  key={`${item.type}-${item.id}-${item.timestamp}`}
                  className="relative flex items-start gap-3 py-3.5 first:pt-0 last:pb-0"
                >
                  <div className="relative z-[1] mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full border border-white/10 bg-[#0E1320]">
                    <TypeIcon className={cn('h-3.5 w-3.5', visual.icon)} aria-hidden />
                  </div>
                  <div className="min-w-0 flex-1 rounded-xl border border-transparent px-1 py-0.5 hover:border-white/[0.04] hover:bg-white/[0.02]">
                    <div className="flex flex-wrap items-center gap-2">
                      <span className="tabular-nums text-[11px] font-medium text-slate-500">
                        {formatTimestamp(item.timestamp)}
                      </span>
                      {item.type ? (
                        <span
                          className={cn(
                            'inline-flex items-center rounded-md border px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-[0.12em]',
                            visual.chip
                          )}
                        >
                          {formatEventType(item.type)}
                        </span>
                      ) : null}
                      <span className="inline-flex items-center" title={item.icon || 'status'}>
                        <ActivityStatusIcon icon={item.icon} />
                      </span>
                    </div>
                    <p className="mt-1.5 truncate text-sm font-medium text-slate-200 sm:whitespace-normal sm:break-words">
                      {item.description?.trim() || item.message?.trim() || 'Unavailable'}
                    </p>
                    {item.message?.trim() &&
                    item.description?.trim() &&
                    item.message !== item.description ? (
                      <p className="mt-1 truncate text-xs text-slate-500">{item.message}</p>
                    ) : null}
                  </div>
                </li>
              );
            })}
          </ul>
        </div>
      ) : null}
    </div>
  );
}
