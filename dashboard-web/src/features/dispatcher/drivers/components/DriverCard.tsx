import { MapPin, Wifi, WifiOff } from 'lucide-react';
import type { Driver } from '../types/driver-operations.types';

interface DriverCardProps {
  driver: Driver;
  isSelected: boolean;
  onSelect: (driver: Driver) => void;
}

function statusClasses(status: string) {
  switch (status) {
    case 'Available':
      return 'bg-emerald-500/10 border-emerald-500/20 text-emerald-400';
    case 'Busy':
      return 'bg-amber-500/10 border-amber-500/20 text-amber-400';
    case 'Offline':
      return 'bg-slate-500/10 border-slate-500/20 text-slate-400';
    case 'On Leave':
      return 'bg-cyan-500/10 border-cyan-500/20 text-cyan-400';
    case 'Suspended':
      return 'bg-red-500/10 border-red-500/20 text-red-400';
    default:
      return 'bg-white/5 border-white/10 text-slate-300';
  }
}

function locationLabel(driver: Driver): string {
  const t = driver.tracking;
  if (t?.latitude != null && t?.longitude != null) {
    return `${t.latitude.toFixed(4)}, ${t.longitude.toFixed(4)}`;
  }
  return 'Unavailable';
}

export default function DriverCard({ driver, isSelected, onSelect }: DriverCardProps) {
  const online = driver.tracking?.onlineStatus === 'online';

  return (
    <div
      onClick={() => onSelect(driver)}
      className={`p-4 rounded-xl border cursor-pointer transition-all ${
        isSelected
          ? 'bg-[#0250C5]/10 border-[#0250C5]/30'
          : 'bg-white/[0.02] border-white/5 hover:bg-white/5 hover:border-white/10'
      }`}
    >
      <div className="flex items-start justify-between mb-3">
        <div className="flex items-center gap-3">
          <div className="w-10 h-10 rounded-full bg-gradient-to-br from-[#0250C5] to-[#00E5FF] flex items-center justify-center text-sm font-bold text-white">
            {driver.name.split(' ').map((n) => n[0]).join('').slice(0, 2)}
          </div>
          <div>
            <h3 className="text-sm font-bold text-white">{driver.name}</h3>
            <p className="text-xs text-slate-500">{driver.employeeNumber}</p>
          </div>
        </div>
        <div className={`px-2 py-1 rounded-lg border text-[10px] font-bold uppercase tracking-wider ${statusClasses(driver.status)}`}>
          {driver.status}
        </div>
      </div>

      <div className="grid grid-cols-2 gap-2 mb-3">
        <div>
          <p className="text-[10px] text-slate-500">Vehicle</p>
          <p className="text-xs font-semibold text-white">{driver.assignment?.vehicleCode || 'Unavailable'}</p>
        </div>
        <div>
          <p className="text-[10px] text-slate-500">Shipment</p>
          <p className="text-xs font-semibold text-white">{driver.assignment?.shipmentNumber || 'Unavailable'}</p>
        </div>
      </div>

      <div className="flex items-center justify-between mb-2">
        <div className="flex items-center gap-2 min-w-0">
          <MapPin className="w-3.5 h-3.5 text-slate-500 flex-shrink-0" />
          <span className="text-xs text-slate-400 truncate">{locationLabel(driver)}</span>
        </div>
        <div className="flex items-center gap-1 flex-shrink-0">
          {online ? <Wifi className="w-3.5 h-3.5 text-emerald-400" /> : <WifiOff className="w-3.5 h-3.5 text-slate-500" />}
          <span className="text-xs text-slate-400">{driver.tracking?.onlineStatus || 'Unavailable'}</span>
        </div>
      </div>

      <div className="flex items-center justify-between text-[10px] text-slate-500 pt-2 border-t border-white/5">
        <span>
          Availability: {driver.dispatchAvailability || 'Unavailable'}
        </span>
        <span data-testid="driver-card-overall-performance">
          Overall:{' '}
          {driver.score?.score != null &&
          driver.score.maturity !== 'unavailable' &&
          driver.score.scoreAvailable !== false
            ? `${driver.score.score}${
                driver.score.scoreLabel ? ` · ${driver.score.scoreLabel.replace(/_/g, ' ')}` : ''
              }${
                driver.score.trend && driver.score.trend !== 'insufficient_data'
                  ? ` · ${driver.score.trend}`
                  : ''
              }`
            : 'No data'}
        </span>
      </div>

      <p className="text-[10px] text-slate-500 mt-2">
        Last update: {driver.tracking?.lastUpdateAt || 'Unavailable'}
      </p>
    </div>
  );
}
