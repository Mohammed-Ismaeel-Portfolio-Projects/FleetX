import { motion } from 'framer-motion';
import { Truck, MapPin, CheckCircle, Wrench, WifiOff, AlertTriangle, Activity } from 'lucide-react';
import type { Vehicle } from '../types/fleet-operations.types';

interface VehicleCardProps {
  vehicle: Vehicle;
  isSelected: boolean;
  onSelect: (vehicle: Vehicle) => void;
}

export default function VehicleCard({ vehicle, isSelected, onSelect }: VehicleCardProps) {
  const status = vehicle.status;

  const getStatusColor = (value?: string) => {
    switch (value) {
      case 'Available':
        return 'bg-emerald-500/10 border-emerald-500/20 text-emerald-400';
      case 'Assigned':
      case 'In Transit':
      case 'Loading':
      case 'Unloading':
      case 'At Customs':
        return 'bg-blue-500/10 border-blue-500/20 text-blue-400';
      case 'Maintenance':
        return 'bg-amber-500/10 border-amber-500/20 text-amber-400';
      case 'Out of Service':
      case 'Inactive':
        return 'bg-slate-500/10 border-slate-500/20 text-slate-400';
      default:
        return 'bg-white/5 border-white/10 text-slate-300';
    }
  };

  const getStatusIcon = (value?: string) => {
    switch (value) {
      case 'Available':
        return <CheckCircle className="w-3.5 h-3.5" />;
      case 'Maintenance':
        return <Wrench className="w-3.5 h-3.5" />;
      case 'Out of Service':
      case 'Inactive':
        return <WifiOff className="w-3.5 h-3.5" />;
      default:
        return <Truck className="w-3.5 h-3.5" />;
    }
  };

  return (
    <motion.div
      whileHover={{ y: -2, scale: 1.01 }}
      whileTap={{ scale: 0.99 }}
      onClick={() => onSelect(vehicle)}
      className={`p-4 rounded-xl border cursor-pointer transition-all ${
        isSelected
          ? 'bg-[#0250C5]/10 border-[#0250C5]/30'
          : 'bg-white/[0.02] border-white/5 hover:bg-white/5 hover:border-white/10'
      }`}
    >
      <div className="flex items-start justify-between mb-3">
        <div className="flex items-center gap-3">
          <div className="w-10 h-10 rounded-xl bg-gradient-to-br from-[#0250C5] to-[#00E5FF] flex items-center justify-center">
            <Truck className="w-5 h-5 text-white" />
          </div>
          <div>
            <h3 className="text-sm font-bold text-white">{vehicle.truckId}</h3>
            <p className="text-xs text-slate-500">{vehicle.plateNumber}</p>
          </div>
        </div>
        <div className={`flex items-center gap-1.5 px-2 py-1 rounded-lg border text-[10px] font-bold uppercase tracking-wider ${getStatusColor(status)}`}>
          {getStatusIcon(status)}
          <span>{status || 'Unavailable'}</span>
        </div>
      </div>

      <div className="grid grid-cols-2 gap-2 mb-3">
        <div>
          <p className="text-[10px] text-slate-500">Truck Type</p>
          <p className="text-xs font-semibold text-white">{vehicle.truckType || 'Unavailable'}</p>
        </div>
        <div>
          <p className="text-[10px] text-slate-500">Trailer Type</p>
          <p className="text-xs font-semibold text-white">{vehicle.trailerType || 'Unavailable'}</p>
        </div>
      </div>

      <div className="grid grid-cols-2 gap-2 mb-3">
        <div>
          <p className="text-[10px] text-slate-500">Driver</p>
          <p className="text-xs font-semibold text-white">{vehicle.driver || 'Unavailable'}</p>
        </div>
        <div>
          <p className="text-[10px] text-slate-500">Shipment</p>
          <p className="text-xs font-semibold text-white">{vehicle.currentShipment || 'Unavailable'}</p>
        </div>
      </div>

      {vehicle.currentLocation ? (
        <div className="flex items-center gap-2 mb-3">
          <MapPin className="w-3.5 h-3.5 text-slate-500" />
          <span className="text-xs text-slate-400">{vehicle.currentLocation}</span>
        </div>
      ) : (
        <div className="flex items-center gap-2 mb-3">
          <MapPin className="w-3.5 h-3.5 text-slate-500" />
          <span className="text-xs text-slate-500">Location unavailable</span>
        </div>
      )}

      <div className="flex items-center justify-between mb-3">
        <div className="flex items-center gap-2">
          <Activity className="w-3.5 h-3.5 text-slate-500" />
          <span className="text-xs text-slate-400">
            {vehicle.mileage !== undefined ? vehicle.mileage.toLocaleString() : 'Unavailable'}
          </span>
        </div>
        <span className="text-xs text-slate-400">
          {vehicle.speed !== undefined ? vehicle.speed : 'Speed unavailable'}
        </span>
      </div>

      <div className="flex items-center justify-between">
        <span className="text-xs text-slate-400">
          {vehicle.signalQuality ? `Signal: ${vehicle.signalQuality}` : 'Signal unavailable'}
        </span>
        <div className="flex items-center gap-1">
          {vehicle.onlineStatus === 'Online' ? (
            <CheckCircle className="w-3.5 h-3.5 text-emerald-400" />
          ) : vehicle.onlineStatus === 'Offline' ? (
            <AlertTriangle className="w-3.5 h-3.5 text-red-400" />
          ) : (
            <AlertTriangle className="w-3.5 h-3.5 text-slate-500" />
          )}
          <span className="text-[10px] font-semibold text-slate-400">
            {vehicle.onlineStatus || 'Unavailable'}
          </span>
        </div>
      </div>

      <div className="mt-3 pt-3 border-t border-white/5">
        <div className="flex items-center justify-between">
          <span className="text-[10px] text-slate-500">Availability</span>
          <span className={`text-[10px] font-semibold ${
            vehicle.availability === 'Available' ? 'text-emerald-400' : 'text-slate-400'
          }`}>
            {vehicle.availability}
          </span>
        </div>
      </div>
    </motion.div>
  );
}
