import { motion } from 'framer-motion';
import { Truck, Navigation } from 'lucide-react';
import { cn } from '../../../../lib/utils';
import type { LiveVehicle } from '../types/tracking.types';

interface FleetListPanelProps {
  vehicles: LiveVehicle[];
  selectedId: string | null;
  onSelect: (id: string) => void;
}

export default function FleetListPanel({
  vehicles,
  selectedId,
  onSelect,
}: FleetListPanelProps) {
  return (
    <div className="bg-[#0E1320] border border-white/5 rounded-2xl p-5 flex flex-col h-[500px] shadow-md select-none text-left w-full overflow-hidden">
      
      {/* Title */}
      <div className="flex justify-between items-center mb-4 flex-shrink-0">
        <div className="flex items-center gap-2">
          <Truck className="w-4.5 h-4.5 text-[#0250C5]" />
          <h3 className="text-sm font-bold text-white font-sans tracking-tight">
            Active Fleet Logs
          </h3>
        </div>
        <span className="text-[9px] font-bold text-slate-500 font-mono">
          {vehicles.length} Units
        </span>
      </div>

      {/* Vehicles list */}
      <div className="flex-grow overflow-y-auto pr-1 flex flex-col gap-2.5 scrollbar-none">
        {vehicles.map((v) => {
          const isSelected = v.vehicleId === selectedId;

          return (
            <motion.button
              key={v.vehicleId}
              onClick={() => onSelect(v.vehicleId)}
              whileHover={{ x: 2 }}
              className={cn(
                'w-full text-left p-3.5 border rounded-xl flex items-center justify-between transition-all duration-150 cursor-pointer',
                isSelected 
                  ? 'bg-[#0250C5]/10 border-[#0250C5]/40 text-white' 
                  : 'bg-white/2 border-white/5 hover:border-white/10 hover:bg-white/4 text-slate-300'
              )}
            >
              <div className="flex items-center gap-3">
                <div className={cn(
                  'w-2 h-2 rounded-full block shrink-0',
                  v.status === 'In Transit' && 'bg-[#00E5FF]',
                  v.status === 'Active' && 'bg-emerald-500',
                  v.status === 'Idle' && 'bg-blue-500',
                  v.status === 'Delayed' && 'bg-red-400',
                  v.status === 'Offline' && 'bg-slate-600'
                )} />

                <div className="flex flex-col">
                  <span className="text-xs font-bold font-sans">{v.id}</span>
                  <span className="text-[10px] text-slate-500 font-sans mt-0.5 leading-none">
                    {v.driver}
                  </span>
                </div>
              </div>

              {/* Quick stats speed */}
              <div className="flex items-center gap-1.5 text-right font-mono text-[10px] text-slate-400">
                <Navigation className="w-3 h-3 text-slate-500 rotate-[45deg]" />
                <span className="font-bold">{v.speed} mph</span>
              </div>

            </motion.button>
          );
        })}

        {vehicles.length === 0 && (
          <div className="py-8 text-center text-slate-500 text-xs font-sans">
            No active fleet matched filters.
          </div>
        )}
      </div>

    </div>
  );
}
export type { FleetListPanelProps };
