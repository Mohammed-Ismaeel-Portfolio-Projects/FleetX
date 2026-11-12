import { useCallback, useEffect, useState } from 'react';
import { PageHeader } from '../../../shared/components/dispatcher/PageHeader';
import { DispatcherSurface } from '../../../shared/components/dispatcher/DispatcherSurface';
import OperationalKPIs from './OperationalKPIs';
import AttentionRequiredPanel from './AttentionRequiredPanel';
import ActiveOperationsPanel from './ActiveOperationsPanel';
import RecentActivityPanel from './RecentActivityPanel';
import DashboardNavCards, { type WorkspaceMetrics } from './DashboardNavCards';
import type { DashboardKPI } from '../../../features/dispatcher/dashboard/services/dashboard.service';

const POLL_INTERVAL_MS = 15_000;

function formatUpdatedLabel(updatedAt: Date, now: Date): string {
  const diffSec = Math.max(0, Math.floor((now.getTime() - updatedAt.getTime()) / 1000));

  if (diffSec < 60) {
    return `Updated ${diffSec} sec ago`;
  }

  if (diffSec < 3600) {
    const mins = Math.floor(diffSec / 60);
    return `Updated ${mins} min ago`;
  }

  return `Updated at ${updatedAt.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}`;
}

export default function DispatcherDashboard() {
  const [refreshKey, setRefreshKey] = useState(0);
  const [lastUpdatedAt, setLastUpdatedAt] = useState<Date | null>(null);
  const [now, setNow] = useState(() => new Date());
  const [workspaceMetrics, setWorkspaceMetrics] = useState<WorkspaceMetrics | null>(null);

  const triggerRefresh = useCallback(() => {
    setRefreshKey((key) => key + 1);
  }, []);

  const handleKpiLoaded = useCallback((meta: { refreshedAt: Date; kpi: DashboardKPI }) => {
    setLastUpdatedAt(meta.refreshedAt);
    setNow(meta.refreshedAt);
    setWorkspaceMetrics({
      vehiclesOnAssignment: meta.kpi.vehicles_in_transit,
      driversAvailable: meta.kpi.available_drivers,
      activeShipments: meta.kpi.active_shipments,
      criticalIssues: meta.kpi.critical_alerts,
    });
  }, []);

  useEffect(() => {
    if (!lastUpdatedAt) return;
    const id = window.setInterval(() => setNow(new Date()), 1000);
    return () => window.clearInterval(id);
  }, [lastUpdatedAt]);

  useEffect(() => {
    let intervalId: number | null = null;

    const clearPolling = () => {
      if (intervalId !== null) {
        window.clearInterval(intervalId);
        intervalId = null;
      }
    };

    const startPolling = () => {
      if (intervalId !== null || document.hidden) return;
      intervalId = window.setInterval(() => {
        if (!document.hidden) {
          triggerRefresh();
        }
      }, POLL_INTERVAL_MS);
    };

    const onVisibilityChange = () => {
      if (document.hidden) {
        clearPolling();
        return;
      }
      triggerRefresh();
      startPolling();
    };

    startPolling();
    document.addEventListener('visibilitychange', onVisibilityChange);

    return () => {
      clearPolling();
      document.removeEventListener('visibilitychange', onVisibilityChange);
    };
  }, [triggerRefresh]);

  return (
    <DispatcherSurface className="w-full text-left gap-5">
      <section className="w-full space-y-3">
        <PageHeader
          title="Operations Overview"
          subtitle="What requires your attention right now."
          showStreaming={false}
          onRefresh={triggerRefresh}
          actions={
            <div className="rounded-xl border border-white/10 bg-white/[0.03] px-3 py-2 text-[11px] text-slate-300 font-mono">
              {lastUpdatedAt ? formatUpdatedLabel(lastUpdatedAt, now) : 'Waiting for KPI data…'}
            </div>
          }
        />
        <OperationalKPIs refreshKey={refreshKey} onLoaded={handleKpiLoaded} />
      </section>

      <section className="w-full">
        <AttentionRequiredPanel refreshKey={refreshKey} />
      </section>

      <section className="w-full">
        <ActiveOperationsPanel refreshKey={refreshKey} />
      </section>

      <section className="w-full">
        <RecentActivityPanel refreshKey={refreshKey} />
      </section>

      <section className="w-full space-y-2">
        <h3 className="text-[10px] font-semibold text-slate-500 uppercase tracking-wider">
          Workspaces
        </h3>
        <DashboardNavCards metrics={workspaceMetrics} />
      </section>
    </DispatcherSurface>
  );
}
