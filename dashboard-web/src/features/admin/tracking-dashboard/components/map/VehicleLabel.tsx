import { memo } from 'react';
import type { FleetVehicle } from '../../types/tracking-dashboard.types';

interface VehicleLabelProps {
  vehicle: FleetVehicle;
  zoom: number;
}

function VehicleLabelComponent({ vehicle, zoom }: VehicleLabelProps) {
  if (zoom < 10) return null;

  const statusColor = vehicle.status === 'moving' ? '#22C55E' : vehicle.status === 'idle' ? '#EAB308' : '#64748B';

  return (
    <div
      className="absolute pointer-events-none"
      style={{
        transform: 'translate(24px, -12px)',
        whiteSpace: 'nowrap',
      }}
    >
      <div className="flex flex-col gap-0.5">
        <div className="flex items-center gap-1.5">
          <span className="text-[10px] font-bold text-white font-mono">
            {vehicle.fleetNumber}
          </span>
          <span
            className="text-[8px] font-medium px-1 py-0.5 rounded-full text-white"
            style={{ backgroundColor: statusColor }}
          >
            {vehicle.status}
          </span>
        </div>
        <div className="flex items-center gap-1">
          <span className="text-[9px] text-slate-300">
            {vehicle.speed.toFixed(0)} km/h
          </span>
        </div>
      </div>
    </div>
  );
}

export default memo(VehicleLabelComponent);
