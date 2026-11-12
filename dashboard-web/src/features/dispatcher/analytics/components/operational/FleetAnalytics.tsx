import { Gauge } from 'lucide-react';
import { Link } from 'react-router-dom';
import type { FleetPerformanceMetric } from '../../types/operationalAnalytics.types';
import { cn } from '../../../../../lib/utils';
import { getFleetDrilldownPath, useOpsBasePath } from '../../utils/analyticsDrilldown';
import { analyticsAccent, analyticsUi, domainAccent, type AnalyticsAccent } from './analyticsUi';

interface FleetAnalyticsProps {
  metrics: FleetPerformanceMetric[];
  sourceError?: string | null;
}

function accentForFleetMetric(label: string): AnalyticsAccent {
  switch (label) {
    case 'Available':
      return 'success';
    case 'Assigned':
    case 'Utilization':
      return 'active';
    case 'Maintenance':
      return 'warn';
    default:
      return 'neutral';
  }
}

export function FleetAnalytics({ metrics, sourceError = null }: FleetAnalyticsProps) {
  const href = getFleetDrilldownPath(useOpsBasePath());
  const domain = domainAccent.fleet;

  return (
    <div className={cn(analyticsUi.panel, domain.panel, 'p-5 md:p-6')}>
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div className="min-w-0">
          <div className="flex items-center gap-3">
            <span className={domain.iconWrap} aria-hidden>
              <Gauge className="h-4 w-4" />
            </span>
            <h3 className={cn(analyticsUi.domainTitle, domain.title)}>Fleet</h3>
          </div>
          <p className={analyticsUi.sectionDescription}>
            Vehicle counts by availability. Assigned = busy on an active assignment. Utilization = assigned ÷
            active fleet.
          </p>
        </div>
        <Link to={href} className={analyticsUi.drillLink}>
          Open Fleet →
        </Link>
      </div>
      {sourceError ? <p className={cn(analyticsUi.bannerWarn, 'mt-3')}>Unavailable — {sourceError}</p> : null}
      <div className="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
        {metrics.map((metric) => {
          const unavailable = metric.value === 'Unavailable';
          const tone = analyticsAccent[accentForFleetMetric(metric.label)];
          return (
            <div
              key={metric.label}
              className={cn(
                analyticsUi.metricTile,
                'relative',
                !unavailable && tone.card,
                !unavailable && tone.tint
              )}
            >
              {!unavailable ? (
                <span className={cn('absolute left-0 top-3 bottom-3 w-[3px] rounded-full', tone.bar)} aria-hidden />
              ) : null}
              <div className={cn(!unavailable && 'pl-2')}>
                <p className={cn(analyticsUi.label, !unavailable && tone.label)}>{metric.label}</p>
                <p
                  className={cn(
                    'mt-2 text-xl md:text-2xl',
                    unavailable ? analyticsUi.valueMuted : cn(analyticsUi.value, tone.value)
                  )}
                >
                  {metric.value}
                </p>
              </div>
            </div>
          );
        })}
      </div>
    </div>
  );
}
