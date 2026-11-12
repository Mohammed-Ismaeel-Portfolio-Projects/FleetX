import { CheckCircle, Clock, Truck, AlertTriangle, Package, XCircle, MapPin, Navigation } from 'lucide-react';
import { cn } from '../../../../../lib/utils';

interface StatusBadgeProps {
  status: string;
  size?: 'sm' | 'md' | 'lg';
  showIcon?: boolean;
}

const statusConfig: Record<string, {
  icon: typeof Package;
  color: string;
  bgColor: string;
  borderColor: string;
}> = {
  'Draft': { icon: Package, color: 'text-slate-400', bgColor: 'bg-slate-500/10', borderColor: 'border-slate-500/20' },
  'Pending Review': { icon: Clock, color: 'text-slate-400', bgColor: 'bg-slate-500/10', borderColor: 'border-slate-500/20' },
  'Approved': { icon: CheckCircle, color: 'text-blue-400', bgColor: 'bg-blue-500/10', borderColor: 'border-blue-500/20' },
  'Waiting Assignment': { icon: Clock, color: 'text-amber-400', bgColor: 'bg-amber-500/10', borderColor: 'border-amber-500/20' },
  'Assigned': { icon: Clock, color: 'text-blue-400', bgColor: 'bg-blue-500/10', borderColor: 'border-blue-500/20' },
  'Ready For Pickup': { icon: Package, color: 'text-blue-400', bgColor: 'bg-blue-500/10', borderColor: 'border-blue-500/20' },
  'Picked Up': { icon: Truck, color: 'text-cyan-400', bgColor: 'bg-cyan-500/10', borderColor: 'border-cyan-500/20' },
  'In Transit': { icon: Truck, color: 'text-emerald-400', bgColor: 'bg-emerald-500/10', borderColor: 'border-emerald-500/20' },
  'At Customs': { icon: MapPin, color: 'text-amber-400', bgColor: 'bg-amber-500/10', borderColor: 'border-amber-500/20' },
  'Out For Delivery': { icon: Navigation, color: 'text-emerald-400', bgColor: 'bg-emerald-500/10', borderColor: 'border-emerald-500/20' },
  'Delivered': { icon: CheckCircle, color: 'text-emerald-400', bgColor: 'bg-emerald-500/10', borderColor: 'border-emerald-500/20' },
  'Completed': { icon: CheckCircle, color: 'text-emerald-400', bgColor: 'bg-emerald-500/10', borderColor: 'border-emerald-500/20' },
  'Cancelled': { icon: XCircle, color: 'text-red-400', bgColor: 'bg-red-500/10', borderColor: 'border-red-500/20' },
};

const defaultConfig = {
  icon: AlertTriangle,
  color: 'text-slate-400',
  bgColor: 'bg-slate-500/10',
  borderColor: 'border-slate-500/20',
};

const sizeConfig = {
  sm: { padding: 'px-2 py-0.5', text: 'text-[9px]', icon: 'w-3 h-3' },
  md: { padding: 'px-2.5 py-1', text: 'text-[10px]', icon: 'w-3.5 h-3.5' },
  lg: { padding: 'px-3 py-1.5', text: 'text-xs', icon: 'w-4 h-4' },
};

export default function StatusBadge({ status, size = 'md', showIcon = true }: StatusBadgeProps) {
  const config = statusConfig[status] || defaultConfig;
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
      <span>{status}</span>
    </div>
  );
}
