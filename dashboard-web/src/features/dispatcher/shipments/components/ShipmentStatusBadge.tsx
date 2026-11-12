import { cn } from '../../../../lib/utils';
import type { ShipmentStatus } from '../types/shipment.types';

interface ShipmentStatusBadgeProps {
  status: ShipmentStatus;
  className?: string;
}

export default function ShipmentStatusBadge({ status, className }: ShipmentStatusBadgeProps) {
  return (
    <span className={cn(
      'px-2.5 py-0.5 rounded-full text-[9px] font-bold uppercase tracking-wider font-sans border inline-block select-none',
      status === 'Pending' && 'bg-amber-500/10 text-amber-400 border-amber-500/20',
      status === 'Assigned' && 'bg-indigo-500/10 text-indigo-400 border-indigo-500/20',
      status === 'In Transit' && 'bg-blue-500/10 text-[#00E5FF] border-blue-500/20',
      status === 'Delivered' && 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20',
      status === 'Delayed' && 'bg-red-500/10 text-red-400 border-red-500/20',
      className
    )}>
      {status}
    </span>
  );
}
