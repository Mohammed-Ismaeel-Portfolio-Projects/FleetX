import { cn } from '../../../lib/utils';

interface StatusBadgeProps {
  label: string;
  tone?: 'neutral' | 'info' | 'success' | 'warning' | 'danger';
  className?: string;
}

export function StatusBadge({ label, tone = 'neutral', className }: StatusBadgeProps) {
  const toneStyles = {
    neutral: 'border-white/10 bg-white/5 text-slate-300',
    info: 'border-cyan-400/20 bg-cyan-500/10 text-cyan-200',
    success: 'border-emerald-400/20 bg-emerald-500/10 text-emerald-300',
    warning: 'border-amber-400/20 bg-amber-500/10 text-amber-200',
    danger: 'border-rose-400/20 bg-rose-500/10 text-rose-200',
  };

  return <span className={cn('rounded-full border px-2.5 py-1 text-[10px] font-semibold uppercase tracking-[0.25em]', toneStyles[tone], className)}>{label}</span>;
}
