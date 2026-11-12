import { Truck, User, FileText, Wifi, Cpu } from 'lucide-react';
import type { VehicleDetails } from '../../types/tracking.types';

interface VehicleOverviewProps {
  details: VehicleDetails;
}

export default function VehicleOverview({ details }: VehicleOverviewProps) {
  const getStatusColor = (status: string) => {
    switch (status.toLowerCase()) {
      case 'online':
      case 'running':
        return 'text-green-400';
      case 'offline':
      case 'stopped':
        return 'text-red-400';
      case 'idle':
        return 'text-yellow-400';
      default:
        return 'text-slate-400';
    }
  };

  return (
    <div className="space-y-3">
      <h3 className="text-xs font-semibold text-white uppercase tracking-wider">Vehicle Overview</h3>
      
      <div className="grid grid-cols-2 gap-2">
        {/* Vehicle Info */}
        <div className="rounded-lg border border-white/5 bg-[#0A0E17] p-3">
          <div className="flex items-center gap-2">
            <Truck className="h-4 w-4 text-cyan-400" />
            <p className="text-[10px] font-bold uppercase tracking-widest text-slate-500">Vehicle</p>
          </div>
          <p className="mt-1 font-mono text-sm font-semibold text-white">{details.vehicle.fleetNumber}</p>
          <p className="text-xs text-slate-400">{details.vehicle.plateNumber}</p>
          <p className="text-[10px] text-slate-500">{details.vehicle.vehicleType}</p>
        </div>

        {/* Driver Info */}
        <div className="rounded-lg border border-white/5 bg-[#0A0E17] p-3">
          <div className="flex items-center gap-2">
            <User className="h-4 w-4 text-cyan-400" />
            <p className="text-[10px] font-bold uppercase tracking-widest text-slate-500">Driver</p>
          </div>
          <p className="mt-1 text-sm font-semibold text-white">{details.driver.name}</p>
          <p className="text-xs text-slate-400">ID: {details.driver.employeeNumber}</p>
        </div>

        {/* Assignment Info */}
        <div className="rounded-lg border border-white/5 bg-[#0A0E17] p-3">
          <div className="flex items-center gap-2">
            <FileText className="h-4 w-4 text-cyan-400" />
            <p className="text-[10px] font-bold uppercase tracking-widest text-slate-500">Assignment</p>
          </div>
          <p className="mt-1 font-mono text-sm font-semibold text-white">{details.assignment.code}</p>
          <p className="text-xs text-slate-400">{details.shipment.shipmentNumber}</p>
        </div>

        {/* Status Info */}
        <div className="rounded-lg border border-white/5 bg-[#0A0E17] p-3">
          <div className="flex items-center gap-2">
            <Cpu className="h-4 w-4 text-cyan-400" />
            <p className="text-[10px] font-bold uppercase tracking-widest text-slate-500">Status</p>
          </div>
          <p className="mt-1 text-sm font-semibold text-white capitalize">{details.tracking.status}</p>
          <p className="text-xs text-slate-400">{details.shipment.status}</p>
        </div>
      </div>

      {/* Connection & Engine Status */}
      <div className="grid grid-cols-2 gap-2">
        <div className="flex items-center gap-2 rounded-lg border border-white/5 bg-[#0A0E17] p-2">
          <Wifi className={`h-4 w-4 ${getStatusColor(details.vehicle.connectionStatus || 'online')}`} />
          <div>
            <p className="text-[10px] text-slate-500">Connection</p>
            <p className={`text-xs font-semibold ${getStatusColor(details.vehicle.connectionStatus || 'online')}`}>
              {details.vehicle.connectionStatus || 'Online'}
            </p>
          </div>
        </div>
        <div className="flex items-center gap-2 rounded-lg border border-white/5 bg-[#0A0E17] p-2">
          <Cpu className={`h-4 w-4 ${getStatusColor(details.vehicle.engineStatus || 'idle')}`} />
          <div>
            <p className="text-[10px] text-slate-500">Engine</p>
            <p className={`text-xs font-semibold ${getStatusColor(details.vehicle.engineStatus || 'idle')}`}>
              {details.vehicle.engineStatus || 'Idle'}
            </p>
          </div>
        </div>
      </div>
    </div>
  );
}
