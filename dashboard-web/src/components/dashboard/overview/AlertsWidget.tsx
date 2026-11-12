import { useEffect, useState } from 'react';
import { AlertCircle, ShieldAlert } from 'lucide-react';
import { Link } from 'react-router-dom';
import {
  dashboardService,
  DashboardApiError,
} from '../../../features/dispatcher/dashboard/services/dashboard.service';

export default function AlertsWidget() {
  const [criticalIssues, setCriticalIssues] = useState<number | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    let cancelled = false;

    async function load() {
      setIsLoading(true);
      setError(null);
      try {
        const kpi = await dashboardService.getKPI();
        if (cancelled) return;
        setCriticalIssues(kpi.critical_alerts);
      } catch (err) {
        if (cancelled) return;
        setCriticalIssues(null);
        setError(err instanceof DashboardApiError ? err.message : 'Failed to load issue count');
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

  const issueCount = criticalIssues ?? 0;
  const hasIssues = issueCount > 0;

  return (
    <div className="bg-[#0E1320] border border-white/5 rounded-2xl p-5 flex flex-col h-full shadow-md select-none text-left">
      <div className="flex justify-between items-center mb-6 flex-shrink-0">
        <div className="flex items-center gap-2">
          <ShieldAlert className="w-4.5 h-4.5 text-red-500" />
          <h3 className="text-sm font-bold text-white font-sans tracking-tight">
            Critical Issues
          </h3>
        </div>
        {!isLoading && !error && (
          <span className={`text-[9px] font-bold px-2.5 py-0.5 rounded-full uppercase tracking-wider border ${
            hasIssues
              ? 'text-red-500 bg-red-500/10 border-red-500/20'
              : 'text-slate-500 bg-white/5 border-white/10'
          }`}>
            {issueCount} Open
          </span>
        )}
      </div>

      <div className="flex-grow flex flex-col justify-center">
        {isLoading && (
          <p className="text-sm text-slate-400">Loading issue summary…</p>
        )}

        {!isLoading && error && (
          <p className="text-sm text-amber-200">
            Issue summary unavailable. {error}
          </p>
        )}

        {!isLoading && !error && hasIssues && (
          <div className="rounded-xl border border-red-500/20 bg-red-500/5 p-5">
            <p className="text-3xl font-extrabold text-white font-mono">{issueCount}</p>
            <p className="mt-2 text-xs text-slate-300">
              Open critical or emergency issues require administrative awareness.
            </p>
            <Link
              to="/admin/notifications"
              className="mt-4 inline-flex text-[11px] font-semibold text-[#0250C5] hover:text-[#3b7ae8]"
            >
              View notifications →
            </Link>
          </div>
        )}

        {!isLoading && !error && !hasIssues && (
          <div className="flex flex-col items-center justify-center text-center p-6 border border-dashed border-white/5 rounded-xl">
            <AlertCircle className="w-8 h-8 text-slate-600 mb-2" />
            <span className="text-xs text-slate-500 font-sans">
              No open critical or emergency issues reported.
            </span>
            <Link
              to="/admin/notifications"
              className="mt-3 text-[11px] font-semibold text-[#0250C5] hover:text-[#3b7ae8]"
            >
              Open notifications
            </Link>
          </div>
        )}
      </div>
    </div>
  );
}
