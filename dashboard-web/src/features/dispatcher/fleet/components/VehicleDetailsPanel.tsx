import { Truck, MapPin, FileText, User, Package, Navigation, Activity } from 'lucide-react';
import type { Vehicle } from '../types/fleet-operations.types';

interface VehicleDetailsPanelProps {
  vehicle: Vehicle | null;
}

export default function VehicleDetailsPanel({ vehicle }: VehicleDetailsPanelProps) {
  if (!vehicle) {
    return (
      <div className="bg-[#0E1320] border border-white/5 rounded-2xl shadow-lg p-8 flex flex-col items-center justify-center h-full">
        <Truck className="w-16 h-16 text-slate-600 mb-4" />
        <p className="text-sm text-slate-400">Select a vehicle to view details</p>
      </div>
    );
  }

  return (
    <div className="bg-[#0E1320] border border-white/5 rounded-2xl shadow-lg flex flex-col h-full overflow-hidden">
      <div className="p-5 border-b border-white/5 bg-gradient-to-r from-[#0250C5]/10 to-transparent">
        <div className="flex items-center gap-4">
          <div className="w-16 h-16 rounded-2xl bg-gradient-to-br from-[#0250C5] to-[#00E5FF] flex items-center justify-center">
            <Truck className="w-8 h-8 text-white" />
          </div>
          <div className="flex-1">
            <h2 className="text-lg font-bold text-white">{vehicle.truckId}</h2>
            <p className="text-sm text-slate-400">{vehicle.plateNumber}</p>
            <div className="flex items-center gap-2 mt-1">
              <span className={`px-2 py-0.5 rounded-lg text-[10px] font-bold uppercase tracking-wider ${
                vehicle.status === 'Available' ? 'bg-emerald-500/10 text-emerald-400' :
                vehicle.status === 'Maintenance' ? 'bg-amber-500/10 text-amber-400' :
                vehicle.status === 'Out of Service' || vehicle.status === 'Inactive' ? 'bg-slate-500/10 text-slate-400' :
                'bg-blue-500/10 text-blue-400'
              }`}>
                {vehicle.status || 'Unavailable'}
              </span>
            </div>
          </div>
        </div>
      </div>

      <div className="flex-1 overflow-y-auto p-5 space-y-5">
        <div className="bg-white/[0.02] border border-white/5 rounded-xl p-4">
          <h3 className="text-xs font-bold text-slate-500 uppercase tracking-wider mb-3 flex items-center gap-2">
            <Truck className="w-3.5 h-3.5" />
            Vehicle Information
          </h3>
          <div className="grid grid-cols-2 gap-3">
            <div>
              <p className="text-[10px] text-slate-500 uppercase tracking-wider mb-1">Make</p>
              <p className="text-sm font-semibold text-white">{vehicle.specifications.make || 'Unavailable'}</p>
            </div>
            <div>
              <p className="text-[10px] text-slate-500 uppercase tracking-wider mb-1">Model</p>
              <p className="text-sm font-semibold text-white">{vehicle.specifications.model || 'Unavailable'}</p>
            </div>
            <div>
              <p className="text-[10px] text-slate-500 uppercase tracking-wider mb-1">Year</p>
              <p className="text-sm font-semibold text-white">{vehicle.specifications.year || 'Unavailable'}</p>
            </div>
            <div>
              <p className="text-[10px] text-slate-500 uppercase tracking-wider mb-1">VIN</p>
              <p className="text-sm font-semibold text-white font-mono">{vehicle.specifications.vin || 'Unavailable'}</p>
            </div>
            <div>
              <p className="text-[10px] text-slate-500 uppercase tracking-wider mb-1">Capacity</p>
              <p className="text-sm font-semibold text-white">
                {vehicle.specifications.capacityKg !== undefined
                  ? `${vehicle.specifications.capacityKg.toLocaleString()} kg`
                  : 'Unavailable'}
              </p>
            </div>
            <div>
              <p className="text-[10px] text-slate-500 uppercase tracking-wider mb-1">Fuel Capacity</p>
              <p className="text-sm font-semibold text-white">
                {vehicle.specifications.fuelCapacityL !== undefined
                  ? `${vehicle.specifications.fuelCapacityL} L`
                  : 'Unavailable'}
              </p>
            </div>
          </div>
        </div>

        <div className="bg-white/[0.02] border border-white/5 rounded-xl p-4">
          <h3 className="text-xs font-bold text-slate-500 uppercase tracking-wider mb-3 flex items-center gap-2">
            <User className="w-3.5 h-3.5" />
            Assigned Driver
          </h3>
          <div className="space-y-2">
            <div className="flex items-center justify-between">
              <span className="text-xs text-slate-400">Name</span>
              <span className="text-sm font-semibold text-white">{vehicle.assignedDriver?.name || 'Unavailable'}</span>
            </div>
            <div className="flex items-center justify-between">
              <span className="text-xs text-slate-400">Phone</span>
              <span className="text-sm font-semibold text-white">{vehicle.assignedDriver?.phone || 'Unavailable'}</span>
            </div>
          </div>
        </div>

        <div className="bg-white/[0.02] border border-white/5 rounded-xl p-4">
          <h3 className="text-xs font-bold text-slate-500 uppercase tracking-wider mb-3 flex items-center gap-2">
            <Package className="w-3.5 h-3.5" />
            Current Shipment
          </h3>
          <div className="flex items-center justify-between">
            <span className="text-xs text-slate-400">Shipment</span>
            <span className="text-sm font-semibold text-white">{vehicle.currentShipment || 'Unavailable'}</span>
          </div>
        </div>

        <div className="bg-white/[0.02] border border-white/5 rounded-xl p-4">
          <h3 className="text-xs font-bold text-slate-500 uppercase tracking-wider mb-3 flex items-center gap-2">
            <FileText className="w-3.5 h-3.5" />
            Documents
          </h3>
          <div className="space-y-2">
            <div className="flex items-center justify-between">
              <span className="text-xs text-slate-400">Insurance Expiry</span>
              <span className="text-sm font-semibold text-white">{vehicle.insuranceExpiry || 'Unavailable'}</span>
            </div>
            <div className="flex items-center justify-between">
              <span className="text-xs text-slate-400">Registration Expiry</span>
              <span className="text-sm font-semibold text-white">{vehicle.registrationExpiry || 'Unavailable'}</span>
            </div>
          </div>
        </div>

        <div className="bg-white/[0.02] border border-white/5 rounded-xl p-4">
          <h3 className="text-xs font-bold text-slate-500 uppercase tracking-wider mb-3 flex items-center gap-2">
            <MapPin className="w-3.5 h-3.5" />
            Location & Tracking
          </h3>
          <div className="space-y-2">
            <div className="flex items-center justify-between">
              <span className="text-xs text-slate-400">Coordinates</span>
              <span className="text-sm font-semibold text-white">{vehicle.currentLocation || 'Unavailable'}</span>
            </div>
            <div className="flex items-center justify-between">
              <span className="text-xs text-slate-400">Movement</span>
              <span className="text-sm font-semibold text-white">{vehicle.movementStatus || 'Unavailable'}</span>
            </div>
            <div className="flex items-center justify-between">
              <span className="text-xs text-slate-400">Online Status</span>
              <span className="text-sm font-semibold text-white">{vehicle.onlineStatus || 'Unavailable'}</span>
            </div>
            <div className="flex items-center justify-between">
              <span className="text-xs text-slate-400">Signal</span>
              <span className="text-sm font-semibold text-white">{vehicle.signalQuality || 'Unavailable'}</span>
            </div>
            <div className="flex items-center justify-between">
              <span className="text-xs text-slate-400">Speed</span>
              <span className="text-sm font-semibold text-white">{vehicle.speed !== undefined ? vehicle.speed : 'Unavailable'}</span>
            </div>
            <div className="flex items-center justify-between">
              <span className="text-xs text-slate-400">Availability</span>
              <span className={`text-xs font-bold ${
                vehicle.availability === 'Available' ? 'text-emerald-400' : 'text-slate-400'
              }`}>
                {vehicle.availability}
              </span>
            </div>
          </div>
        </div>

        <div className="bg-white/[0.02] border border-white/5 rounded-xl p-4">
          <h3 className="text-xs font-bold text-slate-500 uppercase tracking-wider mb-3 flex items-center gap-2">
            <Navigation className="w-3.5 h-3.5" />
            Odometer
          </h3>
          <div className="flex items-center justify-between">
            <span className="text-xs text-slate-400">Current Reading</span>
            <span className="text-lg font-bold text-white font-mono flex items-center gap-2">
              <Activity className="w-4 h-4 text-slate-500" />
              {vehicle.mileage !== undefined ? vehicle.mileage.toLocaleString() : 'Unavailable'}
            </span>
          </div>
        </div>
      </div>
    </div>
  );
}
