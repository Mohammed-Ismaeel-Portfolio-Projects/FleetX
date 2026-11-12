import { RefreshCw } from 'lucide-react';
import type { OperationalAnalyticsViewModel } from '../../types/operationalAnalytics.types';
import { cn } from '../../../../../lib/utils';
import { analyticsUi } from './analyticsUi';

interface AnalyticsHeaderProps {
  data: OperationalAnalyticsViewModel['header'];
  onRefresh: () => void;
  isRefreshing?: boolean;
}

/** Slim refresh toolbar — page title lives on PageHeader only. */
export function AnalyticsHeader({ data, onRefresh, isRefreshing = false }: AnalyticsHeaderProps) {
  return (
    <div className="flex flex-col gap-3 rounded-2xl border border-white/[0.06] bg-white/[0.02] px-4 py-3.5 sm:flex-row sm:items-center sm:justify-between">
      <p className="text-sm text-slate-500">
        {data.lastUpdated ? (
          <>
            Last refreshed <span className="tabular-nums font-medium text-slate-300">{data.lastUpdated}</span>
          </>
        ) : (
          'Current-state operational KPIs'
        )}
      </p>
      <button
        type="button"
        onClick={onRefresh}
        disabled={isRefreshing}
        className={cn(analyticsUi.buttonPrimary, 'py-2.5', isRefreshing && 'opacity-70')}
      >
        <RefreshCw className={cn('h-4 w-4', isRefreshing && 'animate-spin')} /> Refresh
      </button>
    </div>
  );
}
