import { motion } from 'framer-motion';
import { RefreshCw, Activity } from 'lucide-react';
import { cn } from '../../../lib/utils';
import { dispatcherDesignTokens } from '../../constants/dispatcherDesignSystem';

interface PageHeaderProps {
  title: string;
  subtitle?: string;
  badge?: string;
  onRefresh?: () => void;
  actions?: React.ReactNode;
  className?: string;
  showStreaming?: boolean;
}

export function PageHeader({ title, subtitle, badge, onRefresh, actions, className, showStreaming = true }: PageHeaderProps) {
  return (
    <motion.div
      initial={{ opacity: 0, y: 12 }}
      animate={{ opacity: 1, y: 0 }}
      transition={{ duration: 0.45, ease: [0.16, 1, 0.3, 1] as const }}
      className={cn(dispatcherDesignTokens.surfaceElevated, 'p-6', className)}
    >
      <div className="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
        <div>
          <div className="flex flex-wrap items-center gap-3">
            <h1 className="text-2xl font-semibold text-white">{title}</h1>
            {badge ? (
              <span className="rounded-full border border-emerald-400/20 bg-emerald-500/10 px-2.5 py-1 text-[10px] font-semibold uppercase tracking-[0.25em] text-emerald-300">
                {badge}
              </span>
            ) : null}
          </div>
          {subtitle ? <p className="mt-3 text-sm text-slate-400">{subtitle}</p> : null}
        </div>
        <div className="flex flex-wrap items-center gap-3">
          {actions}
          {onRefresh ? (
            <button
              onClick={onRefresh}
              className="inline-flex items-center gap-2 rounded-2xl border border-cyan-400/30 bg-cyan-500/10 px-4 py-3 text-sm font-semibold text-cyan-200 transition hover:bg-cyan-500/20"
            >
              <RefreshCw className="h-4 w-4" /> Refresh
            </button>
          ) : null}
          {showStreaming ? (
            <div className="inline-flex items-center gap-2 rounded-2xl border border-emerald-400/20 bg-emerald-500/10 px-4 py-3 text-sm font-semibold text-emerald-300">
              <Activity className="h-4 w-4" /> Streaming
            </div>
          ) : null}
        </div>
      </div>
    </motion.div>
  );
}
