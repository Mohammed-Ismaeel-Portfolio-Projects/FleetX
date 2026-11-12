import { motion } from 'framer-motion';
import { Truck, Navigation, Wifi, AlertTriangle, Pause, WifiOff } from 'lucide-react';
import type { LiveVehicle } from '../types/tracking.types';

interface VehicleCardProps {
  vehicle: LiveVehicle;
  isSelected: boolean;
  onClick: () => void;
}

export default function VehicleCard({ vehicle, isSelected, onClick }: VehicleCardProps) {
  const getStatusColor = (status: string) => {
    switch (status) {
      case 'Active':
      case 'In Transit':
        return 'text-emerald-400 bg-emerald-500/10 border-emerald-500/20';
      case 'Idle':
        return 'text-amber-400 bg-amber-500/10 border-amber-500/20';
      case 'Delayed':
        return 'text-red-400 bg-red-500/10 border-red-500/20';
      case 'Offline':
        return 'text-slate-400 bg-slate-500/10 border-slate-500/20';
      default:
        return 'text-blue-400 bg-blue-500/10 border-blue-500/20';
    }
  };

  const getStatusIcon = (status: string) => {
    switch (status) {
      case 'Active':
      case 'In Transit':
        return Navigation;
      case 'Idle':
        return Pause;
      case 'Delayed':
        return AlertTriangle;
      case 'Offline':
        return WifiOff;
      default:
        return Truck;
    }
  };

  const getSignalQuality = (signal: number) => {
    if (signal >= 80) return 'text-emerald-400';
    if (signal >= 50) return 'text-amber-400';
    return 'text-red-400';
  };

  const formatLastUpdate = (lastUpdateAt: string) => {
    if (!lastUpdateAt) return 'N/A';
    const date = new Date(lastUpdateAt);
    const now = new Date();
    const diffMs = now.getTime() - date.getTime();
    const diffMins = Math.floor(diffMs / 60000);
    if (diffMins < 1) return 'Just now';
    if (diffMins < 60) return `${diffMins}m ago`;
    const diffHours = Math.floor(diffMins / 60);
    if (diffHours < 24) return `${diffHours}h ago`;
    return `${Math.floor(diffHours / 24)}d ago`;
  };

  const StatusIcon = getStatusIcon(vehicle.status);

  return (
    <motion.div
      whileHover={{ x: 4 }}
      whileTap={{ scale: 0.98 }}
      onClick={onClick}
      className={`
        bg-[#0E1320] border rounded-xl p-4 cursor-pointer transition-all relative overflow-hidden
        ${isSelected ? 'border-[#00E5FF] shadow-lg shadow-[#00E5FF]/10' : 'border-white/5 hover:border-white/10'}
      `}
    >
      {/* Background gradient on hover */}
      <div className="absolute inset-0 bg-gradient-to-r from-white/[0.02] to-transparent opacity-0 hover:opacity-100 transition-opacity duration-300" />
      
      <div className="relative z-10">
        {/* Header */}
        <div className="flex items-start justify-between mb-3">
          <div className="flex items-center gap-3">
            <div className={`w-10 h-10 rounded-lg ${getStatusColor(vehicle.status)} flex items-center justify-center border`}>
              <StatusIcon className="w-5 h-5" />
            </div>
            <div>
              <h3 className="text-sm font-bold text-white font-sans">{vehicle.vehicleCode}</h3>
              <p className="text-[10px] text-slate-400">{vehicle.driverName}</p>
            </div>
          </div>
          
          {/* Signal Quality */}
          <div className="flex items-center gap-1">
            <Wifi className={`w-3.5 h-3.5 ${getSignalQuality(vehicle.signalQuality)}`} />
            <span className="text-[10px] font-semibold text-slate-400">{vehicle.signalQuality}%</span>
          </div>
        </div>

        {/* Shipment Info */}
        <div className="mb-3">
          <p className="text-[10px] text-slate-500 uppercase tracking-wider mb-1">Current Shipment</p>
          <p className="text-xs font-semibold text-white">{vehicle.shipmentNumber}</p>
        </div>

        {/* Stats Grid */}
        <div className="grid grid-cols-2 gap-2 mb-3">
          <div>
            <p className="text-[9px] text-slate-500 uppercase tracking-wider mb-1">Speed</p>
            <p className="text-xs font-bold text-white font-mono">{vehicle.speed} mph</p>
          </div>
          <div>
            <p className="text-[9px] text-slate-500 uppercase tracking-wider mb-1">GPS</p>
            <p className="text-xs font-bold text-emerald-400 font-mono">{vehicle.onlineStatus === 'online' ? 'Live' : 'Offline'}</p>
          </div>
        </div>

        {/* Status Badge */}
        <div className={`inline-flex items-center gap-1.5 px-2 py-1 rounded-lg border ${getStatusColor(vehicle.status)}`}>
          <StatusIcon className="w-3 h-3" />
          <span className="text-[10px] font-bold uppercase tracking-wider">{vehicle.status}</span>
        </div>

        {/* Last Update */}
        <div className="mt-3 pt-3 border-t border-white/5">
          <div className="flex items-center justify-between">
            <span className="text-[9px] text-slate-500">Last Update</span>
            <span className="text-[10px] font-semibold text-slate-400 font-mono">{formatLastUpdate(vehicle.lastUpdateAt)}</span>
          </div>
        </div>
      </div>
    </motion.div>
  );
}
