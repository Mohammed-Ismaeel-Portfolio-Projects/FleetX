import { memo, useMemo } from 'react';
import { Truck, Activity, Pause, StopCircle, Signal, AlertTriangle } from 'lucide-react';
import type { FleetVehicle } from '../../types/tracking-dashboard.types';

interface MapToolbarProps {
  vehicles: FleetVehicle[];
}

function MapToolbarComponent({ vehicles }: MapToolbarProps) {
  const stats = useMemo(() => {
    const moving = vehicles.filter(v => v.status === 'moving').length;
    const idle = vehicles.filter(v => v.status === 'idle').length;
    const stopped = vehicles.filter(v => v.speed === 0 && v.status !== 'idle').length;
    const offline = vehicles.filter(v => v.status === 'offline').length;
    const alerts = 0; // Placeholder for future alert implementation
    
    return {
      active: vehicles.length,
      moving,
      idle,
      stopped,
      offline,
      alerts,
    };
  }, [vehicles]);

  const StatItem = ({ icon: Icon, label, value, color }: { icon: any, label: string, value: number, color: string }) => (
    <div className="flex items-center gap-2 rounded-xl bg-[#0A0E17]/80 backdrop-blur-sm border border-white/5 px-3 py-2">
      <Icon className={`h-4 w-4 ${color}`} />
      <div className="flex flex-col">
        <span className="text-[10px] uppercase tracking-wider text-slate-500">{label}</span>
        <span className="text-sm font-bold text-white">{value}</span>
      </div>
    </div>
  );

  return (
    <div className="z-[1000] flex flex-wrap gap-2">
      <StatItem icon={Truck} label="Active" value={stats.active} color="text-cyan-400" />
      <StatItem icon={Activity} label="Moving" value={stats.moving} color="text-green-400" />
      <StatItem icon={Pause} label="Idle" value={stats.idle} color="text-yellow-400" />
      <StatItem icon={StopCircle} label="Stopped" value={stats.stopped} color="text-orange-400" />
      <StatItem icon={Signal} label="Offline" value={stats.offline} color="text-slate-400" />
      <StatItem icon={AlertTriangle} label="Alerts" value={stats.alerts} color="text-red-400" />
    </div>
  );
}

export default memo(MapToolbarComponent);
