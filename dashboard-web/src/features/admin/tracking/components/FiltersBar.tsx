import { RotateCcw } from 'lucide-react';
import type { MovementStatus, OnlineStatus, SignalQuality } from '../types/tracking.types';

interface FiltersBarProps {
  searchQuery: string;
  movementFilter: MovementStatus | '';
  onlineFilter: OnlineStatus | '';
  signalFilter: SignalQuality | '';
  driverFilter: string;
  vehicleFilter: string;
  shipmentFilter: string;
  onSearchChange: (value: string) => void;
  onMovementChange: (value: MovementStatus | '') => void;
  onOnlineChange: (value: OnlineStatus | '') => void;
  onSignalChange: (value: SignalQuality | '') => void;
  onDriverChange: (value: string) => void;
  onVehicleChange: (value: string) => void;
  onShipmentChange: (value: string) => void;
  onReset: () => void;
}

const selectClass = 'w-full cursor-pointer appearance-none rounded-xl border border-white/5 bg-[#0A0E17] px-4 py-2.5 text-sm text-white outline-none transition-all focus:border-[#0250C5]/50 focus:ring-1 focus:ring-[#0250C5]/20';

export default function FiltersBar({
  searchQuery,
  movementFilter,
  onlineFilter,
  signalFilter,
  driverFilter,
  vehicleFilter,
  shipmentFilter,
  onSearchChange,
  onMovementChange,
  onOnlineChange,
  onSignalChange,
  onDriverChange,
  onVehicleChange,
  onShipmentChange,
  onReset,
}: FiltersBarProps) {
  return (
    <div className="flex flex-col gap-4 rounded-2xl border border-white/5 bg-[#0E1320] p-4">
      <div className="flex flex-col gap-4 xl:flex-row">
        <div className="flex-1">
          <input
            type="text"
            value={searchQuery}
            onChange={(event) => onSearchChange(event.target.value)}
            placeholder="Search by vehicle, driver, shipment..."
            className="w-full rounded-xl border border-white/5 bg-[#0A0E17] px-4 py-2.5 text-sm text-white placeholder:text-slate-500 outline-none transition-all focus:border-[#0250C5]/50 focus:ring-1 focus:ring-[#0250C5]/20"
          />
        </div>

        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:w-[800px] xl:grid-cols-[1fr_1fr_1fr_1fr_auto]">
          <select
            value={movementFilter}
            onChange={(event) => onMovementChange(event.target.value as MovementStatus | '')}
            className={selectClass}
            aria-label="Filter by movement status"
          >
            <option value="">All Movement</option>
            <option value="Moving">Moving</option>
            <option value="Stopped">Stopped</option>
            <option value="Idle">Idle</option>
          </select>

          <select
            value={onlineFilter}
            onChange={(event) => onOnlineChange(event.target.value as OnlineStatus | '')}
            className={selectClass}
            aria-label="Filter by online status"
          >
            <option value="">All Online</option>
            <option value="Online">Online</option>
            <option value="Offline">Offline</option>
          </select>

          <select
            value={signalFilter}
            onChange={(event) => onSignalChange(event.target.value as SignalQuality | '')}
            className={selectClass}
            aria-label="Filter by signal quality"
          >
            <option value="">All Signal</option>
            <option value="Good">Good</option>
            <option value="Weak">Weak</option>
            <option value="Lost">Lost</option>
          </select>

          <select
            value={driverFilter}
            onChange={(event) => onDriverChange(event.target.value)}
            className={selectClass}
            aria-label="Filter by driver"
          >
            <option value="">All Drivers</option>
          </select>

          <button
            type="button"
            onClick={onReset}
            className="inline-flex items-center justify-center gap-2 rounded-xl border border-white/10 px-4 py-2.5 text-sm font-bold text-slate-300 transition-colors hover:bg-white/5 hover:text-white"
          >
            <RotateCcw className="h-4 w-4" />
            Reset
          </button>
        </div>
      </div>

      <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
        <input
          type="text"
          value={vehicleFilter}
          onChange={(event) => onVehicleChange(event.target.value)}
          placeholder="Filter by vehicle fleet number..."
          className="rounded-xl border border-white/5 bg-[#0A0E17] px-4 py-2.5 text-sm text-white placeholder:text-slate-500 outline-none transition-all focus:border-[#0250C5]/50 focus:ring-1 focus:ring-[#0250C5]/20"
        />

        <input
          type="text"
          value={shipmentFilter}
          onChange={(event) => onShipmentChange(event.target.value)}
          placeholder="Filter by shipment number..."
          className="rounded-xl border border-white/5 bg-[#0A0E17] px-4 py-2.5 text-sm text-white placeholder:text-slate-500 outline-none transition-all focus:border-[#0250C5]/50 focus:ring-1 focus:ring-[#0250C5]/20"
        />
      </div>
    </div>
  );
}
