import { motion } from 'framer-motion';
import { MapPin, ChevronRight } from 'lucide-react';
import { cn } from '../../../../lib/utils';
import type { Shipment } from '../types/shipment.types';
import StatusBadge from './ui/StatusBadge';
import PriorityBadge from './ui/PriorityBadge';
import ProgressIndicator from './ui/ProgressIndicator';
import { getShipmentStatusProgress } from '../utils/shipmentStatusProgress';
import { formatOpsListIndicator } from '../utils/engineOperationalStage';

interface ShipmentRowProps {
  shipment: Shipment;
  onClick?: () => void;
  isSelected?: boolean;
}

export default function ShipmentRow({
  shipment,
  onClick,
  isSelected = false,
}: ShipmentRowProps) {
  const progress = getShipmentStatusProgress(shipment.status);
  const opsLine = formatOpsListIndicator(
    shipment.status,
    shipment.engineOps?.operationalStage,
    Boolean(shipment.engineOps?.isResting),
    {
      canEnroll: Boolean(shipment.engineOps?.enrollment.canEnroll),
      simulationEligible: Boolean(shipment.engineOps?.simulationEligible),
    }
  );

  return (
    <motion.div
      whileHover={{ y: -2, scale: 1.01 }}
      whileTap={{ scale: 0.99 }}
      onClick={onClick}
      className={cn(
        'bg-[#0E1320] border rounded-xl p-4 cursor-pointer transition-all group relative overflow-hidden',
        isSelected ? 'border-[#00E5FF] shadow-lg shadow-[#00E5FF]/10' : 'border-white/5 hover:border-white/10'
      )}
    >
      <div className="absolute inset-0 bg-gradient-to-r from-white/[0.02] to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300" />

      <div className="relative z-10">
        <div className="flex items-start gap-4">
          <div className="flex-1 min-w-0">
            <div className="flex items-center gap-3 mb-2">
              <h3 className="text-sm font-bold text-white font-sans">{shipment.shipmentNumber}</h3>
              <PriorityBadge priority={shipment.priority} size="sm" />
              {shipment.collaborationType === 'outbound_partner' ? (
                <span className="rounded-md border border-amber-400/30 bg-amber-500/10 px-2 py-0.5 text-[9px] font-bold uppercase tracking-wider text-amber-300">
                  Outbound Partner
                </span>
              ) : shipment.collaborationType === 'inbound_partner' ? (
                <span className="rounded-md border border-purple-400/30 bg-purple-500/10 px-2 py-0.5 text-[9px] font-bold uppercase tracking-wider text-purple-300">
                  Inbound Partner
                </span>
              ) : null}
            </div>

            <p className="text-xs font-semibold text-slate-300 mb-1">{shipment.customer}</p>

            <div className="flex items-center gap-2 text-[10px] text-slate-500">
              <div className="flex items-center gap-1">
                <MapPin className="w-3 h-3" />
                <span>{shipment.origin}</span>
              </div>
              <ChevronRight className="w-3 h-3" />
              <span>{shipment.destination}</span>
            </div>
          </div>

          <div className="flex flex-col items-end gap-1">
            <StatusBadge status={shipment.status} size="sm" />
            {opsLine ? (
              <span className="max-w-[180px] truncate text-[9px] font-semibold text-slate-400">
                {opsLine}
              </span>
            ) : null}
          </div>
        </div>

        <div className="flex items-center gap-6 mt-4 pt-4 border-t border-white/5">
          <div className="flex items-center gap-2">
            <div className="w-8 h-8 rounded-full bg-gradient-to-br from-[#0250C5] to-[#00E5FF] flex items-center justify-center text-white text-[10px] font-bold">
              {shipment.driver ? shipment.driver.split(' ').map(n => n[0]).join('') : '--'}
            </div>
            <div>
              <p className="text-[10px] font-semibold text-slate-300">{shipment.driver || 'Unavailable'}</p>
              <p className="text-[9px] text-slate-500">{shipment.truck || 'Unavailable'}</p>
            </div>
          </div>

          <div className="flex items-center gap-2">
            <span className="text-[10px] text-slate-500 uppercase tracking-wider">Planned Delivery</span>
            <span className="text-xs font-bold text-white font-mono">
              {shipment.plannedDeliveryDate || 'Unavailable'}
            </span>
          </div>

          {progress !== null && (
            <div className="flex-1 max-w-[200px]">
              <ProgressIndicator
                progress={progress}
                size="sm"
                color="emerald"
                showLabel={false}
              />
            </div>
          )}
        </div>
      </div>
    </motion.div>
  );
}
export type { ShipmentRowProps };
