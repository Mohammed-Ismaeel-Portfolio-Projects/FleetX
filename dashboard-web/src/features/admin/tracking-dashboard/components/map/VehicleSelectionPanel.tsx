import { memo } from 'react';
import { X, Truck, User, Package, FileText, Gauge, Navigation, Signal, Activity, MapPin } from 'lucide-react';
import { formatCoordinates, formatSpeed, formatHeading } from './utils/map.utils';
import type { FleetVehicle } from '../../types/tracking-dashboard.types';

interface VehicleSelectionPanelProps {
  vehicle: FleetVehicle | null;
  onClose: () => void;
  onViewShipment?: () => void;
  onViewAssignment?: () => void;
  onViewDriver?: () => void;
  onCenterMap?: (vehicle: FleetVehicle) => void;
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

function VehicleSelectionPanelComponent({
  vehicle,
  onClose,
  onViewShipment,
  onViewAssignment,
  onViewDriver,
  onCenterMap,
}: VehicleSelectionPanelProps) {
  if (!vehicle) return null;

  const handleCenterMap = () => {
    onCenterMap?.(vehicle);
  };

  return (
    <div className="absolute right-4 top-4 z-[1000] w-80 rounded-2xl border border-white/10 bg-[#0E1320]/95 backdrop-blur-sm shadow-2xl overflow-hidden">
      {/* Header */}
      <div className="flex items-center justify-between border-b border-white/5 p-4">
        <div className="flex items-center gap-3">
          <div className="rounded-xl bg-gradient-to-br from-cyan-500/20 to-cyan-500/5 p-3 border border-cyan-500/20">
            <Truck className="h-5 w-5 text-cyan-400" />
          </div>
          <div>
            <p className="font-mono text-base font-bold text-white">{vehicle.fleetNumber}</p>
            <p className="text-xs text-slate-400">{vehicle.plateNumber}</p>
          </div>
        </div>
        <button
          type="button"
          onClick={onClose}
          className="p-1.5 rounded-lg text-slate-400 hover:text-white hover:bg-white/5 transition-colors"
        >
          <X className="h-4 w-4" />
        </button>
      </div>

      <div className="p-4 space-y-4 max-h-[calc(100vh-200px)] overflow-y-auto">
        {/* Status Badge */}
        <div className="flex items-center justify-between">
          <span className={`inline-flex rounded-full border px-3 py-1 text-[11px] font-bold uppercase tracking-wider ${getStatusColor(vehicle.status)}`}>
            {vehicle.status}
          </span>
          <span className="text-xs text-slate-400">
            Last update: {new Date(vehicle.lastUpdate).toLocaleString()}
          </span>
        </div>

        {/* Vehicle Info */}
        <div className="space-y-3">
          <div className="flex items-center gap-3 rounded-xl bg-[#0A0E17] p-3 border border-white/5">
            <User className="h-4 w-4 text-cyan-400" />
            <div className="flex-1 min-w-0">
              <p className="text-[10px] uppercase tracking-wider text-slate-500">Driver</p>
              <p className="text-sm font-medium text-white truncate">{vehicle.driver}</p>
            </div>
          </div>

          <div className="flex items-center gap-3 rounded-xl bg-[#0A0E17] p-3 border border-white/5">
            <Package className="h-4 w-4 text-cyan-400" />
            <div className="flex-1 min-w-0">
              <p className="text-[10px] uppercase tracking-wider text-slate-500">Shipment</p>
              <p className="text-sm font-mono font-medium text-white truncate">{vehicle.shipment}</p>
            </div>
          </div>

          <div className="flex items-center gap-3 rounded-xl bg-[#0A0E17] p-3 border border-white/5">
            <FileText className="h-4 w-4 text-cyan-400" />
            <div className="flex-1">
              <p className="text-[10px] uppercase tracking-wider text-slate-500">Assignment</p>
              <p className="text-sm font-medium text-white">Active</p>
            </div>
          </div>
        </div>

        {/* Telemetry Grid */}
        <div className="grid grid-cols-2 gap-3">
          <div className="flex items-center gap-2 rounded-xl bg-[#0A0E17] p-3 border border-white/5">
            <Gauge className="h-4 w-4 text-cyan-400" />
            <div>
              <p className="text-[10px] uppercase tracking-wider text-slate-500">Speed</p>
              <p className="text-sm font-bold text-white">{formatSpeed(vehicle.speed)}</p>
            </div>
          </div>

          <div className="flex items-center gap-2 rounded-xl bg-[#0A0E17] p-3 border border-white/5">
            <Navigation className="h-4 w-4 text-cyan-400" />
            <div>
              <p className="text-[10px] uppercase tracking-wider text-slate-500">Heading</p>
              <p className="text-sm font-bold text-white">{formatHeading(vehicle.heading)}</p>
            </div>
          </div>

          <div className="flex items-center gap-2 rounded-xl bg-[#0A0E17] p-3 border border-white/5">
            <Signal className={`h-4 w-4 ${getSignalColor(vehicle.signalQuality)}`} />
            <div>
              <p className="text-[10px] uppercase tracking-wider text-slate-500">Signal</p>
              <p className={`text-sm font-bold capitalize ${getSignalColor(vehicle.signalQuality)}`}>
                {vehicle.signalQuality}
              </p>
            </div>
          </div>

          <div className="flex items-center gap-2 rounded-xl bg-[#0A0E17] p-3 border border-white/5">
            <Activity className="h-4 w-4 text-cyan-400" />
            <div>
              <p className="text-[10px] uppercase tracking-wider text-slate-500">Status</p>
              <p className="text-sm font-bold capitalize text-white">{vehicle.status}</p>
            </div>
          </div>
        </div>

        {/* Location */}
        <div className="flex items-center gap-3 rounded-xl bg-[#0A0E17] p-3 border border-white/5">
          <MapPin className="h-4 w-4 text-cyan-400" />
          <div className="flex-1 min-w-0">
            <p className="text-[10px] uppercase tracking-wider text-slate-500">Coordinates</p>
            <p className="text-sm font-mono font-medium text-white truncate">
              {formatCoordinates(vehicle.latitude || 0, vehicle.longitude || 0)}
            </p>
          </div>
        </div>

        {/* Quick Actions */}
        <div className="pt-4 border-t border-white/5">
          <p className="text-[10px] uppercase tracking-wider text-slate-500 mb-3">Quick Actions</p>
          <div className="grid grid-cols-2 gap-2">
            <button
              type="button"
              onClick={onViewShipment}
              className="flex items-center gap-2 rounded-lg border border-white/10 bg-[#0A0E17] px-3 py-2 text-xs font-medium text-slate-300 transition-all hover:bg-white/5 hover:text-white hover:border-cyan-500/30"
            >
              <Package className="h-3 w-3" />
              View Shipment
            </button>
            <button
              type="button"
              onClick={onViewAssignment}
              className="flex items-center gap-2 rounded-lg border border-white/10 bg-[#0A0E17] px-3 py-2 text-xs font-medium text-slate-300 transition-all hover:bg-white/5 hover:text-white hover:border-cyan-500/30"
            >
              <FileText className="h-3 w-3" />
              View Assignment
            </button>
            <button
              type="button"
              onClick={onViewDriver}
              className="flex items-center gap-2 rounded-lg border border-white/10 bg-[#0A0E17] px-3 py-2 text-xs font-medium text-slate-300 transition-all hover:bg-white/5 hover:text-white hover:border-cyan-500/30"
            >
              <User className="h-3 w-3" />
              View Driver
            </button>
            <button
              type="button"
              onClick={handleCenterMap}
              className="flex items-center gap-2 rounded-lg border border-white/10 bg-[#0A0E17] px-3 py-2 text-xs font-medium text-slate-300 transition-all hover:bg-white/5 hover:text-white hover:border-cyan-500/30"
            >
              <MapPin className="h-3 w-3" />
              Center Map
            </button>
          </div>
        </div>
      </div>
    </div>
  );
}

export default memo(VehicleSelectionPanelComponent);
