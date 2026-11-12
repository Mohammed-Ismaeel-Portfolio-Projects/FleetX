import { Link } from 'react-router-dom';
import { ShieldAlert } from 'lucide-react';
import { cn } from '../../../../lib/utils';
import { dispatcherDesignTokens } from '../../../../shared/constants/dispatcherDesignSystem';

interface EmergencyPanelProps {
  criticalAlerts: string;
  sourceError?: string | null;
}

/**
 * Emergency / critical ops attention — linked to Issues Alerts Center.
 * No fake “trigger emergency message” action.
 */
export function EmergencyPanel({ criticalAlerts, sourceError = null }: EmergencyPanelProps) {
  const unavailable = criticalAlerts === 'Unavailable';

  return (
    <div className={cn(dispatcherDesignTokens.surface, 'border-rose-400/20 p-4 md:p-5')}>
      <div className="flex items-center gap-2">
        <ShieldAlert className="h-4 w-4 text-rose-300" aria-hidden />
        <h2 className="text-lg font-semibold text-white">Critical Ops Attention</h2>
      </div>
      <p className="mt-2 text-sm leading-relaxed text-slate-500">
        Critical / emergency Issues live in Alerts Center — not an in-app emergency chat. Use Alerts
        to triage, then Focus contact / Call for the related driver when IDs exist.
      </p>

      {sourceError ? (
        <p className="mt-3 rounded-2xl border border-amber-500/20 bg-amber-500/10 px-3 py-2 text-sm text-amber-100">
          Unavailable — {sourceError}
        </p>
      ) : (
        <div className="mt-4 rounded-2xl border border-rose-400/20 bg-rose-500/[0.07] px-4 py-3">
          <p className="text-xs uppercase tracking-[0.16em] text-rose-200/80">Critical alerts</p>
          <p
            className={cn(
              'mt-1 text-3xl font-semibold tabular-nums',
              unavailable ? 'text-slate-500' : 'text-rose-50'
            )}
          >
            {criticalAlerts}
          </p>
        </div>
      )}

      <Link
        to="/dispatcher/notifications"
        className={cn(dispatcherDesignTokens.buttonPrimary, 'mt-4 w-full justify-center px-3 py-2.5')}
      >
        Open Alerts Center
      </Link>
    </div>
  );
}
