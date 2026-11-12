import type { ReactNode } from 'react';
import { useOperationalAnalytics } from '../hooks/useOperationalAnalytics';
import { AnalyticsHeader } from './operational/AnalyticsHeader';
import { DeliveryPerformanceKpis, ExecutiveKPIs } from './operational/ExecutiveKPIs';
import { FleetAnalytics } from './operational/FleetAnalytics';
import { DriverAnalytics } from './operational/DriverAnalytics';
import { ShipmentAnalytics } from './operational/ShipmentAnalytics';
import { OperationalActivity } from './operational/OperationalActivity';
import type { OperationalAnalyticsKpi } from '../types/operationalAnalytics.types';
import { analyticsUi } from './operational/analyticsUi';
import { cn } from '../../../../lib/utils';

const DELIVERY_KPI_IDS = new Set(['on-time-delivery', 'completed-today', 'avg-dispatch-time']);

function Section({
  title,
  description,
  children,
  priority = 'primary',
}: {
  title: string;
  description?: string;
  children: ReactNode;
  priority?: 'primary' | 'secondary';
}) {
  return (
    <section className={cn('space-y-4', priority === 'secondary' && 'opacity-[0.98]')}>
      <header className="relative border-b border-white/[0.07] pb-3.5 pl-3.5">
        <span
          className={cn(
            'absolute left-0 top-1 h-5 w-[3px] rounded-full',
            priority === 'primary' ? 'bg-cyan-400/80' : 'bg-slate-500/70'
          )}
          aria-hidden
        />
        <h2 className={analyticsUi.sectionTitle}>{title}</h2>
        {description ? <p className={analyticsUi.sectionDescription}>{description}</p> : null}
      </header>
      {children}
    </section>
  );
}

function splitExecutiveKpis(kpis: OperationalAnalyticsKpi[]) {
  const delivery = kpis.filter((kpi) => DELIVERY_KPI_IDS.has(kpi.id));
  const snapshot = kpis.filter((kpi) => !DELIVERY_KPI_IDS.has(kpi.id));
  return { snapshot, delivery };
}

export function OperationalAnalyticsCenter() {
  const {
    viewModel,
    isLoading,
    isRefreshing,
    error,
    activity,
    refreshData,
    retryActivity,
    retryKpis,
  } = useOperationalAnalytics();

  if (isLoading && !viewModel) {
    return (
      <div className="flex flex-col gap-6">
        <div className="h-10 animate-pulse rounded-2xl border border-white/10 bg-[#0E1320]/80" />
        <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
          {Array.from({ length: 7 }).map((_, index) => (
            <div key={index} className="h-32 animate-pulse rounded-[24px] border border-white/10 bg-[#0E1320]" />
          ))}
        </div>
        <div className="h-36 animate-pulse rounded-[24px] border border-white/10 bg-[#0E1320]" />
        <div className="h-48 animate-pulse rounded-[24px] border border-white/10 bg-[#0E1320]/80" />
      </div>
    );
  }

  const { snapshot, delivery } = viewModel
    ? splitExecutiveKpis(viewModel.kpis)
    : { snapshot: [], delivery: [] };

  return (
    <div className="flex flex-col gap-9">
      {viewModel ? (
        <>
          <AnalyticsHeader
            data={viewModel.header}
            onRefresh={refreshData}
            isRefreshing={isRefreshing}
          />

          {(viewModel.sectionErrors.dashboard || viewModel.sectionErrors.performance) && (
            <div className={analyticsUi.bannerWarn}>
              {viewModel.sectionErrors.dashboard
                ? `Dashboard KPI partial failure: ${viewModel.sectionErrors.dashboard}`
                : null}
              {viewModel.sectionErrors.dashboard && viewModel.sectionErrors.performance ? ' · ' : null}
              {viewModel.sectionErrors.performance
                ? `Performance partial failure: ${viewModel.sectionErrors.performance}`
                : null}
            </div>
          )}

          <Section
            title="Operations Snapshot"
            description="Operational risk, workload, volume, and capacity signals."
          >
            <ExecutiveKPIs kpis={snapshot} />
          </Section>

          {delivery.length > 0 ? (
            <Section
              title="Delivery Performance"
              description="On-time rate, completions today, and average dispatch time."
            >
              <DeliveryPerformanceKpis
                kpis={delivery}
                sourceError={viewModel.sectionErrors.performance}
              />
            </Section>
          ) : null}

          <Section
            title="Domain Breakdowns"
            description="Availability and status buckets by fleet, shipments, and drivers."
            priority="secondary"
          >
            <div className="flex flex-col gap-5">
              <FleetAnalytics metrics={viewModel.fleet} sourceError={viewModel.sectionErrors.fleet} />
              <ShipmentAnalytics
                metrics={viewModel.shipments}
                sourceError={viewModel.sectionErrors.shipments}
              />
              <DriverAnalytics
                metrics={viewModel.drivers}
                sourceError={viewModel.sectionErrors.drivers}
              />
            </div>
          </Section>
        </>
      ) : (
        <div className={analyticsUi.bannerError}>
          <p>{error ?? 'Unable to load operational analytics.'}</p>
          <button type="button" onClick={() => void retryKpis()} className={cn(analyticsUi.buttonPrimary, 'mt-4')}>
            Retry KPIs
          </button>
        </div>
      )}

      <Section
        title="Recent Operational Activity"
        description="Supporting context from the latest operational events."
        priority="secondary"
      >
        <OperationalActivity
          items={activity.items}
          status={
            isLoading && !viewModel && activity.status === 'loading'
              ? 'loading'
              : activity.status
          }
          error={activity.error}
          onRetry={retryActivity}
        />
      </Section>
    </div>
  );
}
