import { Link } from 'react-router-dom';
import { MessageSquare, RefreshCw } from 'lucide-react';
import type { CommunicationViewModel } from '../types/communication.types';
import { cn } from '../../../../lib/utils';
import { dispatcherDesignTokens } from '../../../../shared/constants/dispatcherDesignSystem';

interface CommunicationHeaderProps {
  data: Pick<CommunicationViewModel, 'lastUpdated' | 'driverTotal' | 'sectionErrors'>;
  isRefreshing: boolean;
  onRefresh: () => void;
  messagingConnected?: boolean;
  conversationsLoading?: boolean;
  conversationCount?: number;
  conversationsError?: string | null;
  realtimeStatus?:
    | 'disabled'
    | 'connecting'
    | 'connected'
    | 'reconnecting'
    | 'disconnected'
    | 'permission_denied'
    | 'error';
}

function realtimeLabel(
  status:
    | 'disabled'
    | 'connecting'
    | 'connected'
    | 'reconnecting'
    | 'disconnected'
    | 'permission_denied'
    | 'error'
    | undefined
): string {
  switch (status) {
    case 'connected':
      return 'Realtime connected';
    case 'connecting':
      return 'Realtime connecting';
    case 'reconnecting':
      return 'Realtime reconnecting';
    case 'permission_denied':
      return 'Realtime permission denied';
    case 'error':
    case 'disconnected':
      return 'Realtime unavailable — REST';
    case 'disabled':
    default:
      return 'REST messaging (enable Reverb for WS)';
  }
}

export function CommunicationHeader({
  data,
  isRefreshing,
  onRefresh,
  messagingConnected = false,
  conversationsLoading = false,
  conversationCount,
  conversationsError = null,
  realtimeStatus = 'disabled',
}: CommunicationHeaderProps) {
  const driverLabel =
    data.driverTotal != null && Number.isFinite(data.driverTotal)
      ? `${data.driverTotal} active drivers in directory`
      : data.sectionErrors.drivers
        ? 'Driver directory unavailable'
        : 'Driver directory';

  const messagingLabel = conversationsError
    ? 'Messaging: refresh error'
    : conversationsLoading && !messagingConnected
      ? 'Messaging: loading'
      : messagingConnected
        ? 'Messaging available (REST)'
        : 'Messaging: idle';

  return (
    <div className={cn(dispatcherDesignTokens.surfaceElevated, 'p-5 md:p-6')}>
      <div className="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
        <div className="min-w-0">
          <div className="flex flex-wrap items-center gap-2">
            <h1 className="text-2xl font-semibold tracking-tight text-white">
              Communication Center
            </h1>
            <span
              className={cn(
                'inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-[10px] font-semibold uppercase tracking-[0.18em]',
                conversationsError
                  ? 'border-amber-400/25 bg-amber-500/10 text-amber-200'
                  : messagingConnected
                    ? 'border-emerald-400/25 bg-emerald-500/10 text-emerald-100'
                    : 'border-white/10 bg-white/5 text-slate-400'
              )}
            >
              <MessageSquare className="h-3 w-3" aria-hidden />
              {messagingLabel}
            </span>
            <span
              className={cn(
                'inline-flex items-center rounded-full border px-2.5 py-1 text-[10px] font-semibold uppercase tracking-[0.16em]',
                realtimeStatus === 'connected'
                  ? 'border-emerald-400/20 bg-emerald-500/5 text-emerald-200/90'
                  : 'border-white/10 bg-white/5 text-slate-500'
              )}
              title={
                realtimeStatus === 'disabled'
                  ? 'Realtime opt-in off (VITE_REALTIME_ENABLED). REST remains authoritative.'
                  : 'Websocket delivery status — not message persistence'
              }
            >
              {realtimeLabel(realtimeStatus)}
            </span>
          </div>
          <p className="mt-2 max-w-2xl text-sm leading-relaxed text-slate-400">
            Dispatcher workspace:{' '}
            <span className="text-slate-300">Contacts → Conversations → Messages</span>, with{' '}
            <span className="text-slate-300">Call / Email</span> and{' '}
            <span className="text-slate-300">Needs Attention → Alerts</span>. REST is authoritative;
            realtime is optional delivery when a WS transport is configured. No typing, presence, or
            delivery receipts.
          </p>
          <div className="mt-3 flex flex-wrap gap-2 text-sm text-slate-400">
            <span className="rounded-full border border-white/10 bg-white/5 px-3 py-1">
              {driverLabel}
            </span>
            {conversationCount != null && messagingConnected ? (
              <span className="rounded-full border border-white/10 bg-white/5 px-3 py-1">
                {conversationCount} conversation{conversationCount === 1 ? '' : 's'} loaded
              </span>
            ) : null}
            {data.lastUpdated ? (
              <span className="rounded-full border border-white/10 bg-white/5 px-3 py-1">
                Ops updated{' '}
                <span className="tabular-nums text-slate-300">{data.lastUpdated}</span>
              </span>
            ) : null}
          </div>
        </div>
        <div className="flex flex-wrap items-center gap-3">
          <Link
            to="/dispatcher/notifications"
            className={cn(dispatcherDesignTokens.buttonSecondary, 'px-4 py-3')}
          >
            Open Alerts
          </Link>
          <button
            type="button"
            onClick={onRefresh}
            disabled={isRefreshing}
            className={cn(dispatcherDesignTokens.buttonPrimary, 'px-4 py-3', isRefreshing && 'opacity-70')}
          >
            <RefreshCw className={cn('h-4 w-4', isRefreshing && 'animate-spin')} /> Refresh
          </button>
        </div>
      </div>
    </div>
  );
}
