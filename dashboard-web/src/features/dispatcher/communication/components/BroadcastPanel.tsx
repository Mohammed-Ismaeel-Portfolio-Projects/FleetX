import { Megaphone } from 'lucide-react';
import { Link } from 'react-router-dom';
import { cn } from '../../../../lib/utils';
import { dispatcherDesignTokens } from '../../../../shared/constants/dispatcherDesignSystem';

/**
 * Broadcast remains a future capability — informative only, no dead compose UI.
 */
export function BroadcastPanel() {
  return (
    <div className={cn(dispatcherDesignTokens.surface, 'p-4 md:p-5')}>
      <div className="flex flex-wrap items-center gap-2">
        <Megaphone className="h-4 w-4 text-slate-400" aria-hidden />
        <h2 className="text-lg font-semibold text-white">Fleet broadcast</h2>
        <span className="rounded-full border border-white/10 bg-white/5 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-[0.14em] text-slate-400">
          Not connected
        </span>
      </div>
      <p className="mt-2 text-sm leading-relaxed text-slate-500">
        Fleet-wide messaging is not available. For a single driver use REST Conversations or Call /
        Email; for operational attention use Alerts Center.
      </p>
      <div className="mt-4 flex flex-wrap gap-2">
        <Link
          to="/dispatcher/notifications"
          className={cn(dispatcherDesignTokens.buttonSecondary, 'px-3 py-2 text-xs')}
        >
          Open Alerts Center
        </Link>
      </div>
    </div>
  );
}
