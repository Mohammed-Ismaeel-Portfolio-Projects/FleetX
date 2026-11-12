import { Truck, Gauge, MapPin, Navigation, Wifi, Activity } from 'lucide-react';
import type { LiveVehicle } from '../types/tracking.types';
import VehicleDriverPerformanceSection from './VehicleDriverPerformanceSection';

interface VehicleDetailsPanelProps {
  vehicle: LiveVehicle | null;
}

export default function VehicleDetailsPanel({ vehicle }: VehicleDetailsPanelProps) {
  if (!vehicle) {
    return (
      <div className="bg-[#0E1320] border border-white/5 rounded-2xl p-8 flex flex-col items-center justify-center h-full text-center shadow-lg">
        <div className="w-16 h-16 rounded-full bg-white/5 flex items-center justify-center mb-4">
          <Truck className="w-8 h-8 text-slate-500" />
        </div>
        <h3 className="text-lg font-bold text-white mb-2">No Vehicle Selected</h3>
        <p className="text-sm text-slate-400 max-w-md">
          Select a vehicle from the fleet list to view real-time telemetry and operational details.
        </p>
      </div>
    );
  }

  const getEngineStatus = (status: string) => {
    if (status === 'Active' || status === 'In Transit') return 'Running';
    if (status === 'Idle') return 'Idle';
    if (status === 'Offline') return 'Off';
    return 'Unknown';
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

  const getSignalQualityLabel = (signal: number) => {
    if (signal >= 80) return 'Excellent';
    if (signal >= 50) return 'Good';
    if (signal >= 30) return 'Fair';
    return 'Poor';
  };

  return (
    <div className="bg-[#0E1320] border border-white/5 rounded-2xl shadow-lg h-full flex flex-col overflow-hidden">
      {/* Header */}
      <div className="p-5 border-b border-white/5">
        <div className="flex items-start justify-between">
          <div className="flex items-center gap-4">
            <div className="w-14 h-14 rounded-xl bg-gradient-to-br from-[#0250C5] to-[#00E5FF] flex items-center justify-center">
              <Truck className="w-7 h-7 text-white" />
            </div>
            <div>
              <h2 className="text-xl font-bold text-white font-sans">{vehicle.vehicleCode}</h2>
              <p className="text-sm text-slate-400">{vehicle.driverName}</p>
              <div className="flex items-center gap-2 mt-1">
                <span className={`px-2 py-0.5 rounded text-[10px] font-bold uppercase ${
                  vehicle.status === 'Active' || vehicle.status === 'In Transit' 
                    ? 'bg-emerald-500/10 text-emerald-400' 
                    : vehicle.status === 'Delayed' 
                    ? 'bg-red-500/10 text-red-400' 
                    : 'bg-slate-500/10 text-slate-400'
                }`}>
                  {vehicle.status}
                </span>
              </div>
            </div>
          </div>
          
          {/* Quick Stats */}
          <div className="flex items-center gap-3">
            <div className="text-right">
              <p className="text-[10px] text-slate-500 uppercase tracking-wider">Speed</p>
              <p className="text-lg font-bold text-white font-mono">{vehicle.speed} mph</p>
            </div>
          </div>
        </div>
      </div>

      {/* Content */}
      <div className="flex-1 overflow-y-auto p-5 space-y-5">
        {/* Truck & Driver Info */}
        <div className="bg-white/[0.02] border border-white/5 rounded-xl p-4">
          <h3 className="text-xs font-bold text-slate-400 uppercase tracking-wider mb-4 flex items-center gap-2">
            <Truck className="w-4 h-4" />
            Vehicle & Driver Information
          </h3>
          <div className="grid grid-cols-2 gap-4">
            <div>
              <p className="text-[10px] text-slate-500 uppercase tracking-wider mb-1">Driver</p>
              <p className="text-sm font-semibold text-white">{vehicle.driverName}</p>
            </div>
            <div>
              <p className="text-[10px] text-slate-500 uppercase tracking-wider mb-1">Current Shipment</p>
              <p className="text-sm font-semibold text-white">{vehicle.shipmentNumber}</p>
            </div>
            <div>
              <p className="text-[10px] text-slate-500 uppercase tracking-wider mb-1">Plate Number</p>
              <p className="text-sm font-semibold text-white">{vehicle.plateNumber}</p>
            </div>
            <div>
              <p className="text-[10px] text-slate-500 uppercase tracking-wider mb-1">Engine Status</p>
              <p className="text-sm font-semibold text-emerald-400">{getEngineStatus(vehicle.status)}</p>
            </div>
          </div>
        </div>

        {/* DRIVER-EVAL-08B — overall + current assignment (selected vehicle only) */}
        <VehicleDriverPerformanceSection vehicle={vehicle} />

        {/* Telemetry */}
        <div className="bg-white/[0.02] border border-white/5 rounded-xl p-4">
          <h3 className="text-xs font-bold text-slate-400 uppercase tracking-wider mb-4 flex items-center gap-2">
            <Gauge className="w-4 h-4" />
            Real-Time Telemetry
          </h3>
          <div className="grid grid-cols-2 gap-4">
            <div className="flex items-center gap-3">
              <div className="w-10 h-10 rounded-lg bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center">
                <Gauge className="w-5 h-5 text-emerald-400" />
              </div>
              <div>
                <p className="text-[10px] text-slate-500 uppercase tracking-wider">Speed</p>
                <p className="text-lg font-bold text-white font-mono">{vehicle.speed} mph</p>
              </div>
            </div>
            <div className="flex items-center gap-3">
              <div className="w-10 h-10 rounded-lg bg-cyan-500/10 border border-cyan-500/20 flex items-center justify-center">
                <Navigation className="w-5 h-5 text-cyan-400" />
              </div>
              <div>
                <p className="text-[10px] text-slate-500 uppercase tracking-wider">Heading</p>
                <p className="text-lg font-bold text-white font-mono">{vehicle.heading.toFixed(0)}°</p>
              </div>
            </div>
          </div>
        </div>

        {/* GPS & Connectivity */}
        <div className="bg-white/[0.02] border border-white/5 rounded-xl p-4">
          <h3 className="text-xs font-bold text-slate-400 uppercase tracking-wider mb-4 flex items-center gap-2">
            <Wifi className="w-4 h-4" />
            Connectivity & GPS
          </h3>
          <div className="space-y-3">
            <div className="flex items-center justify-between">
              <div className="flex items-center gap-2">
                <Wifi className="w-4 h-4 text-emerald-400" />
                <span className="text-sm text-slate-400">Signal Quality</span>
              </div>
              <span className="text-sm font-bold text-emerald-400">{getSignalQualityLabel(vehicle.signalQuality)} ({vehicle.signalQuality}%)</span>
            </div>
            <div className="flex items-center justify-between">
              <div className="flex items-center gap-2">
                <MapPin className="w-4 h-4 text-cyan-400" />
                <span className="text-sm text-slate-400">GPS Status</span>
              </div>
              <span className="text-sm font-bold text-cyan-400">{vehicle.onlineStatus === 'online' ? 'Live' : 'Offline'}</span>
            </div>
            <div className="flex items-center justify-between">
              <div className="flex items-center gap-2">
                <Activity className="w-4 h-4 text-amber-400" />
                <span className="text-sm text-slate-400">Last Update</span>
              </div>
              <span className="text-sm font-bold text-white font-mono">{formatLastUpdate(vehicle.lastUpdateAt)}</span>
            </div>
          </div>
        </div>

        {/* Current Location */}
        <div className="bg-white/[0.02] border border-white/5 rounded-xl p-4">
          <h3 className="text-xs font-bold text-slate-400 uppercase tracking-wider mb-4 flex items-center gap-2">
            <MapPin className="w-4 h-4" />
            Current Location
          </h3>
          <div className="space-y-2">
            <div className="flex items-center justify-between">
              <span className="text-sm text-slate-400">Latitude</span>
              <span className="text-sm font-mono text-white">
                {vehicle.latitude.toFixed(6)}°N
              </span>
            </div>
            <div className="flex items-center justify-between">
              <span className="text-sm text-slate-400">Longitude</span>
              <span className="text-sm font-mono text-white">
                {Math.abs(vehicle.longitude).toFixed(6)}°W
              </span>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
export type { VehicleDetailsPanelProps };
