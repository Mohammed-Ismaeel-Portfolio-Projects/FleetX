import { Link } from 'react-router-dom';
import { BellRing, Package, Truck, UserCheck, Users } from 'lucide-react';
import type { CommunicationContextMetric } from '../types/communication.types';
import { cn } from '../../../../lib/utils';
import { dispatcherDesignTokens } from '../../../../shared/constants/dispatcherDesignSystem';

interface CommunicationKPIsProps {
  metrics: CommunicationContextMetric[];
  sourceErrors?: { driverKpis?: string | null; dashboard?: string | null };
}

const ICONS: Record<string, typeof Users> = {
  'available-drivers': UserCheck,
  'busy-drivers': Users,
  'critical-alerts': BellRing,
  'active-shipments': Package,
  'vehicles-in-transit': Truck,
};

export function CommunicationKPIs({ metrics, sourceErrors }: CommunicationKPIsProps) {
  return (
    <div className="space-y-3">
      {(sourceErrors?.driverKpis || sourceErrors?.dashboard) && (
        <div className="rounded-2xl border border-amber-500/20 bg-amber-500/10 px-4 py-3 text-sm text-amber-100">
          {[sourceErrors.driverKpis && `Driver KPIs: ${sourceErrors.driverKpis}`, sourceErrors.dashboard && `Dashboard: ${sourceErrors.dashboard}`]
            .filter(Boolean)
            .join(' · ')}
        </div>
      )}
      <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
        {metrics.map((metric) => {
          const Icon = ICONS[metric.id] ?? Users;
          const unavailable = metric.value === 'Unavailable';
          const body = (
            <>
              <div className="flex items-center justify-between gap-2">
                <p className="text-[13px] font-medium text-slate-400">{metric.label}</p>
                <span className="rounded-lg border border-cyan-400/20 bg-cyan-500/10 p-1.5 text-cyan-300">
                  <Icon className="h-3.5 w-3.5" aria-hidden />
                </span>
              </div>
              <p
                className={cn(
                  'mt-3 text-3xl font-semibold tabular-nums tracking-tight',
                  unavailable ? 'text-slate-500' : 'text-white'
                )}
              >
                {metric.value}
              </p>
              <p className="mt-2 line-clamp-2 text-xs leading-relaxed text-slate-500">{metric.detail}</p>
              {metric.href && !unavailable ? (
                <p className="mt-3 text-[11px] font-semibold uppercase tracking-[0.14em] text-cyan-300/80">
                  Open →
                </p>
              ) : null}
            </>
          );

          const className = cn(
            dispatcherDesignTokens.surface,
            'flex h-full min-h-[128px] flex-col p-4',
            metric.href &&
              !unavailable &&
              'transition-colors hover:border-cyan-400/30 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-400/40'
          );

          if (metric.href && !unavailable) {
            return (
              <Link key={metric.id} to={metric.href} className={className} aria-label={`Open ${metric.label}`}>
                {body}
              </Link>
            );
          }

          return (
            <div key={metric.id} className={className}>
              {body}
            </div>
          );
        })}
      </div>
    </div>
  );
}
