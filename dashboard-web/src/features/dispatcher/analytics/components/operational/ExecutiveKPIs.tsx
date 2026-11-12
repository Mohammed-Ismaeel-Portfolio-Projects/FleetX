import { Link } from 'react-router-dom';
import type { OperationalAnalyticsKpi } from '../../types/operationalAnalytics.types';
import { cn } from '../../../../../lib/utils';
import {
  getExecutiveDrilldownCtaLabel,
  getExecutiveDrilldownPath,
  useOpsBasePath,
} from '../../utils/analyticsDrilldown';
import {
  analyticsAccent,
  analyticsUi,
  type AnalyticsAccent,
} from './analyticsUi';

interface ExecutiveKPIsProps {
  kpis: OperationalAnalyticsKpi[];
}

function isUnavailable(value: string): boolean {
  return value === 'Unavailable';
}

/** Visual identity by KPI role — not value thresholds. */
function accentForKpi(kpiId: string): AnalyticsAccent {
  switch (kpiId) {
    case 'critical-alerts':
      return 'risk';
    case 'delayed-shipments':
      return 'warn';
    case 'pending-assignments':
      return 'warn';
    case 'active-shipments':
    case 'vehicles-in-transit':
    case 'fleet-utilization':
      return 'active';
    case 'available-drivers':
    case 'on-time-delivery':
    case 'completed-today':
      return 'success';
    case 'avg-dispatch-time':
    default:
      return 'neutral';
  }
}

function KpiCard({
  kpi,
  featured = false,
  href = null,
}: {
  kpi: OperationalAnalyticsKpi;
  featured?: boolean;
  href?: string | null;
}) {
  const unavailable = isUnavailable(kpi.value);
  const clickable = Boolean(href) && !unavailable;
  const accent = accentForKpi(kpi.id);
  const tone = analyticsAccent[accent];

  const body = (
    <>
      <span
        className={cn('absolute inset-y-0 left-0 w-[3px]', unavailable ? 'bg-slate-600' : tone.bar)}
        aria-hidden
      />
      <div className={cn('absolute inset-0 pointer-events-none', unavailable ? 'bg-transparent' : tone.tint)} aria-hidden />
      <div className="relative z-[1] flex h-full flex-col">
        <p
          className={cn(
            analyticsUi.label,
            featured && 'font-medium uppercase tracking-[0.14em]',
            !unavailable && (featured ? 'text-emerald-200/85' : tone.label)
          )}
        >
          {kpi.label}
        </p>
        <div
          className={cn(
            'mt-3',
            featured ? 'text-4xl md:text-5xl font-semibold tracking-tight tabular-nums' : 'text-2xl md:text-3xl',
            unavailable ? analyticsUi.valueMuted : featured ? 'text-emerald-50' : cn(analyticsUi.value, tone.value)
          )}
        >
          {kpi.value}
        </div>
        {kpi.detail ? <p className={analyticsUi.detail}>{kpi.detail}</p> : null}
        {clickable ? (
          <p className="mt-auto pt-3 text-[11px] font-semibold uppercase tracking-[0.14em] text-cyan-300/85">
            {getExecutiveDrilldownCtaLabel(kpi.id)}
          </p>
        ) : (
          <div className="mt-auto" />
        )}
      </div>
    </>
  );

  const className = cn(
    featured ? analyticsUi.kpiCardFeatured : analyticsUi.kpiCard,
    !unavailable && tone.card,
    featured && !unavailable && 'border-emerald-400/30',
    clickable &&
      'transition-colors hover:border-cyan-400/35 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-400/40'
  );

  if (clickable && href) {
    return (
      <Link to={href} className={className} aria-label={`Open ${kpi.label} operational view`}>
        {body}
      </Link>
    );
  }

  return <div className={className}>{body}</div>;
}

/** Operations snapshot grid — counts and capacity signals only. */
export function ExecutiveKPIs({ kpis }: ExecutiveKPIsProps) {
  const base = useOpsBasePath();

  return (
    <div className="grid gap-3.5 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
      {kpis.map((kpi) => (
        <KpiCard key={kpi.id} kpi={kpi} href={getExecutiveDrilldownPath(kpi.id, base)} />
      ))}
    </div>
  );
}

/** Delivery performance — On-Time featured on its own row; companions secondary. */
export function DeliveryPerformanceKpis({
  kpis,
  sourceError = null,
}: {
  kpis: OperationalAnalyticsKpi[];
  sourceError?: string | null;
}) {
  const base = useOpsBasePath();
  const featured = kpis.find((kpi) => kpi.id === 'on-time-delivery');
  const companions = kpis.filter((kpi) => kpi.id !== 'on-time-delivery');

  return (
    <div className="space-y-3.5">
      {sourceError ? <p className={analyticsUi.bannerWarn}>Unavailable — {sourceError}</p> : null}
      {featured ? (
        <KpiCard kpi={featured} featured href={getExecutiveDrilldownPath(featured.id, base)} />
      ) : null}
      {companions.length > 0 ? (
        <div className="grid gap-3.5 sm:grid-cols-2">
          {companions.map((kpi) => (
            <KpiCard key={kpi.id} kpi={kpi} href={getExecutiveDrilldownPath(kpi.id, base)} />
          ))}
        </div>
      ) : null}
    </div>
  );
}
