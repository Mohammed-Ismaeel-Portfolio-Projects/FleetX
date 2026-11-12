import { Truck, User, Package, MapPin, Signal, Gauge } from 'lucide-react';
import type { FleetVehicle } from '../types/tracking-dashboard.types';

interface FleetCardProps {
  vehicle: FleetVehicle;
  isSelected?: boolean;
  onClick?: () => void;
}

function getStatusColor(status: FleetVehicle['status']): string {
  switch (status) {
    case 'moving':
      return 'bg-green-500/10 text-green-300 border-green-500/20';
    case 'idle':
      return 'bg-yellow-500/10 text-yellow-300 border-yellow-500/20';
    case 'offline':
      return 'bg-red-500/10 text-red-300 border-red-500/20';
    default:
      return 'bg-slate-500/10 text-slate-300 border-slate-500/20';
  }
}

function getSignalColor(quality: FleetVehicle['signalQuality']): string {
  switch (quality) {
    case 'good':
      return 'text-green-400';
    case 'weak':
      return 'text-yellow-400';
    case 'lost':
      return 'text-red-400';
    default:
      return 'text-slate-400';
  }
}

export default function FleetCard({ vehicle, isSelected = false, onClick }: FleetCardProps) {
  return (
    <button
      type="button"
      onClick={onClick}
      className={`w-full rounded-xl border p-4 text-left transition-all duration-200 ${
        isSelected
          ? 'border-cyan-500/40 bg-cyan-500/[0.08] shadow-sm ring-1 ring-cyan-500/20'
          : 'border-white/5 bg-[#0A0E17] hover:border-white/8 hover:bg-white/[0.03] hover:shadow-sm'
      }`}
    >
      <div className="flex items-start justify-between gap-3">
        <div className="flex-1">
          <div className="flex items-center gap-2">
            <Truck className="h-4 w-4 text-cyan-400" />
            <span className="font-mono text-sm font-semibold text-white">
              {vehicle.fleetNumber}
            </span>
          </div>
          <p className="mt-1 text-xs text-slate-400">{vehicle.plateNumber}</p>
        </div>
        <span className={`inline-flex rounded-full border px-2 py-0.5 text-[10px] font-medium uppercase capitalize ${getStatusColor(vehicle.status)}`}>
          {vehicle.status}
        </span>
      </div>

      <div className="mt-3 space-y-2">
        <div className="flex items-center gap-2 text-xs">
          <User className="h-3 w-3 text-slate-500" />
          <span className="text-slate-300">{vehicle.driver}</span>
        </div>
        <div className="flex items-center gap-2 text-xs">
          <Package className="h-3 w-3 text-slate-500" />
          <span className="font-mono text-slate-300">{vehicle.shipment}</span>
        </div>
      </div>

      <div className="mt-3 flex items-center justify-between border-t border-white/5 pt-3">
        <div className="flex items-center gap-2 text-xs">
          <Gauge className="h-3 w-3 text-slate-500" />
          <span className="text-slate-300">{vehicle.speed.toFixed(1)} km/h</span>
        </div>
        <div className="flex items-center gap-1">
          <Signal className={`h-3 w-3 ${getSignalColor(vehicle.signalQuality)}`} />
          <span className="text-[10px] capitalize text-slate-400">{vehicle.signalQuality}</span>
        </div>
      </div>

      <div className="mt-2 flex items-center gap-2 text-[10px] text-slate-500">
        <MapPin className="h-3 w-3" />
        <span className="truncate">
          {vehicle.latitude && vehicle.longitude
            ? `${vehicle.latitude.toFixed(4)}, ${vehicle.longitude.toFixed(4)}`
            : 'Location unavailable'}
        </span>
      </div>
    </button>
  );
}
