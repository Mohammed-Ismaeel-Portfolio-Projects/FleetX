import { AlertTriangle, Zap, Minus, Circle } from 'lucide-react';
import { cn } from '../../../../../lib/utils';

interface PriorityBadgeProps {
  priority: string;
  size?: 'sm' | 'md' | 'lg';
  showIcon?: boolean;
}

const priorityConfig: Record<string, {
  icon: typeof Circle;
  color: string;
  bgColor: string;
  borderColor: string;
}> = {
  urgent: {
    icon: AlertTriangle,
    color: 'text-red-400',
    bgColor: 'bg-red-500/10',
    borderColor: 'border-red-500/20',
  },
  high: {
    icon: Zap,
    color: 'text-orange-400',
    bgColor: 'bg-orange-500/10',
    borderColor: 'border-orange-500/20',
  },
  normal: {
    icon: Minus,
    color: 'text-amber-400',
    bgColor: 'bg-amber-500/10',
    borderColor: 'border-amber-500/20',
  },
  low: {
    icon: Circle,
    color: 'text-slate-400',
    bgColor: 'bg-slate-500/10',
    borderColor: 'border-slate-500/20',
  },
};

const defaultConfig = {
  icon: Circle,
  color: 'text-slate-400',
  bgColor: 'bg-slate-500/10',
  borderColor: 'border-slate-500/20',
};

const sizeConfig = {
  sm: { padding: 'px-2 py-0.5', text: 'text-[9px]', icon: 'w-3 h-3' },
  md: { padding: 'px-2.5 py-1', text: 'text-[10px]', icon: 'w-3.5 h-3.5' },
  lg: { padding: 'px-3 py-1.5', text: 'text-xs', icon: 'w-4 h-4' },
};

export default function PriorityBadge({ priority, size = 'md', showIcon = true }: PriorityBadgeProps) {
  const key = (priority || '').toLowerCase();
  const config = priorityConfig[key] || defaultConfig;
  const sizeStyles = sizeConfig[size];
  const Icon = config.icon;

  return (
    <div className={cn(
      'inline-flex items-center gap-1.5 rounded-lg border font-semibold uppercase tracking-wider',
      config.bgColor,
      config.borderColor,
      config.color,
      sizeStyles.padding,
      sizeStyles.text
    )}>
      {showIcon && <Icon className={cn(sizeStyles.icon)} />}
      <span>{priority || 'Unavailable'}</span>
    </div>
  );
}
