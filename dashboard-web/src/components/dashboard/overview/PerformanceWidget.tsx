import { useEffect, useState } from 'react';
import { motion } from 'framer-motion';
import { BarChart3 } from 'lucide-react';
import { Link } from 'react-router-dom';
import {
  dashboardService,
  DashboardApiError,
  type DashboardPerformance,
} from '../../../features/dispatcher/dashboard/services/dashboard.service';

function formatPercent(value: number | null | undefined): string {
  if (value === null || value === undefined || !Number.isFinite(value)) {
    return 'Unavailable';
  }
  return `${value.toFixed(1)}%`;
}

function formatCount(value: number | null | undefined): string {
  if (value === null || value === undefined || !Number.isFinite(value)) {
    return 'Unavailable';
  }
  return value.toLocaleString();
}

export default function PerformanceWidget() {
  const [performance, setPerformance] = useState<DashboardPerformance | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    let cancelled = false;

    async function load() {
      setIsLoading(true);
      setError(null);
      try {
        const data = await dashboardService.getPerformance();
        if (cancelled) return;
        setPerformance(data);
      } catch (err) {
        if (cancelled) return;
        setPerformance(null);
        setError(err instanceof DashboardApiError ? err.message : 'Failed to load performance metrics');
      } finally {
        if (!cancelled) {
          setIsLoading(false);
        }
      }
    }

    void load();
    return () => {
      cancelled = true;
    };
  }, []);

  const onTimeValue = formatPercent(performance?.on_time_delivery_percentage);
  const completedToday = formatCount(performance?.completed_today);

  return (
    <div className="bg-[#0E1320] border border-white/5 rounded-2xl p-5 flex flex-col h-full shadow-md select-none text-left">
      <div className="flex items-center justify-between gap-3 mb-6">
        <div className="flex items-center gap-2">
          <BarChart3 className="w-4.5 h-4.5 text-blue-500" />
          <h3 className="text-sm font-bold text-white font-sans tracking-tight">
            Platform Performance
          </h3>
        </div>
        <Link
          to="/admin/analytics"
          className="text-[11px] font-semibold text-[#0250C5] hover:text-[#3b7ae8]"
        >
          Open analytics
        </Link>
      </div>

      {isLoading && (
        <p className="text-sm text-slate-400">Loading performance metrics…</p>
      )}

      {!isLoading && error && (
        <p className="text-sm text-amber-200">
          Performance metrics unavailable. {error}
        </p>
      )}

      {!isLoading && !error && performance && (
        <div className="flex flex-col gap-5 flex-grow">
          <div className="flex flex-col">
            <div className="flex justify-between items-center text-[11px] font-sans mb-2">
              <span className="font-bold text-slate-500 uppercase tracking-widest">
                On-Time Delivery
              </span>
              <span className="font-bold text-white font-mono">
                {onTimeValue}
              </span>
            </div>
            {onTimeValue !== 'Unavailable' && (
              <div className="w-full bg-white/5 h-1.5 rounded-full overflow-hidden">
                <motion.div
                  initial={{ width: 0 }}
                  animate={{ width: onTimeValue }}
                  transition={{ duration: 1, ease: 'easeOut' }}
                  className="bg-blue-500 h-full rounded-full"
                />
              </div>
            )}
          </div>

          <div className="flex justify-between items-center text-[11px] font-sans">
            <span className="font-bold text-slate-500 uppercase tracking-widest">
              Completed Today
            </span>
            <span className="font-extrabold text-emerald-400 font-sans text-sm">
              {completedToday}
            </span>
          </div>

          <p className="mt-auto text-[10px] text-slate-500 leading-relaxed">
            Metrics from GET /dashboard/performance. Commercial billing and invoice totals are not shown on this dashboard.
          </p>
        </div>
      )}
    </div>
  );
}
