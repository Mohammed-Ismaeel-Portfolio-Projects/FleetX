import { useState } from 'react';
import { Search, Truck } from 'lucide-react';
import type { Vehicle } from '../types/fleet-operations.types';
import VehicleCard from './VehicleCard';

interface FleetListProps {
  vehicles: Vehicle[];
  selectedVehicleId: string;
  onVehicleSelect: (vehicle: Vehicle) => void;
}

export default function FleetList({ vehicles, selectedVehicleId, onVehicleSelect }: FleetListProps) {
  const [searchQuery, setSearchQuery] = useState('');
  const [statusFilter, setStatusFilter] = useState('All');
  const [sortBy, setSortBy] = useState('truckId');

  const filteredVehicles = vehicles.filter(vehicle => {
    const matchesSearch =
      vehicle.truckId.toLowerCase().includes(searchQuery.toLowerCase()) ||
      vehicle.plateNumber.toLowerCase().includes(searchQuery.toLowerCase()) ||
      vehicle.truckType?.toLowerCase().includes(searchQuery.toLowerCase()) ||
      vehicle.trailerType?.toLowerCase().includes(searchQuery.toLowerCase()) ||
      vehicle.driver?.toLowerCase().includes(searchQuery.toLowerCase()) ||
      vehicle.currentShipment?.toLowerCase().includes(searchQuery.toLowerCase());

    const matchesStatus = statusFilter === 'All' || vehicle.status === statusFilter;

    return matchesSearch && matchesStatus;
  });

  const sortedVehicles = [...filteredVehicles].sort((a, b) => {
    switch (sortBy) {
      case 'truckId':
        return a.truckId.localeCompare(b.truckId);
      case 'mileage':
        return (a.mileage ?? -1) - (b.mileage ?? -1);
      case 'status':
        return (a.status || '').localeCompare(b.status || '');
      default:
        return 0;
    }
  });

  const statusCounts = {
    available: vehicles.filter(v => v.availability === 'Available').length,
    assigned: vehicles.filter(v => v.hasActiveAssignment).length,
    maintenance: vehicles.filter(v => v.status === 'Maintenance').length,
  };

  return (
    <div className="bg-[#0E1320] border border-white/5 rounded-2xl shadow-lg flex flex-col h-full">
      <div className="p-5 border-b border-white/5">
        <div className="flex items-center justify-between mb-4">
          <h2 className="text-sm font-bold text-white font-sans flex items-center gap-2">
            <Truck className="w-4 h-4 text-[#00E5FF]" />
            Fleet List
          </h2>
          <span className="text-xs font-semibold text-slate-400">{filteredVehicles.length} vehicles</span>
        </div>

        <div className="relative">
          <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-500" />
          <input
            type="text"
            value={searchQuery}
            onChange={(e) => setSearchQuery(e.target.value)}
            placeholder="Search vehicles..."
            className="w-full pl-10 pr-4 py-2.5 bg-white/5 border border-white/10 rounded-xl text-sm text-white placeholder-slate-500 focus:outline-none focus:border-[#0250C5]/50 transition-all"
          />
        </div>
      </div>

      <div className="px-5 py-3 border-b border-white/5 flex items-center gap-3 flex-wrap">
        <select
          value={statusFilter}
          onChange={(e) => setStatusFilter(e.target.value)}
          className="px-3 py-1.5 bg-white/5 border border-white/10 rounded-lg text-xs text-white focus:outline-none focus:border-[#0250C5]/50 transition-all"
        >
          <option value="All">All Statuses</option>
          <option value="Available">Available</option>
          <option value="Assigned">Assigned</option>
          <option value="In Transit">In Transit</option>
          <option value="Loading">Loading</option>
          <option value="Unloading">Unloading</option>
          <option value="At Customs">At Customs</option>
          <option value="Maintenance">Maintenance</option>
          <option value="Out of Service">Out of Service</option>
          <option value="Inactive">Inactive</option>
        </select>

        <select
          value={sortBy}
          onChange={(e) => setSortBy(e.target.value)}
          className="px-3 py-1.5 bg-white/5 border border-white/10 rounded-lg text-xs text-white focus:outline-none focus:border-[#0250C5]/50 transition-all"
        >
          <option value="truckId">Sort by ID</option>
          <option value="mileage">Sort by Odometer</option>
          <option value="status">Sort by Status</option>
        </select>
      </div>

      <div className="flex-1 overflow-y-auto p-4 space-y-3">
        {sortedVehicles.length === 0 ? (
          <div className="text-center py-12">
            <Truck className="w-12 h-12 text-slate-600 mx-auto mb-3" />
            <p className="text-sm text-slate-400">No vehicles found</p>
            <p className="text-xs text-slate-500 mt-1">Try adjusting your filters</p>
          </div>
        ) : (
          sortedVehicles.map((vehicle) => (
            <VehicleCard
              key={vehicle.id}
              vehicle={vehicle}
              isSelected={selectedVehicleId === vehicle.id}
              onSelect={onVehicleSelect}
            />
          ))
        )}
      </div>

      <div className="p-4 border-t border-white/5">
        <div className="grid grid-cols-3 gap-3">
          <div className="text-center">
            <p className="text-lg font-bold text-emerald-400">{statusCounts.available}</p>
            <p className="text-[10px] text-slate-500 uppercase tracking-wider">Available</p>
          </div>
          <div className="text-center">
            <p className="text-lg font-bold text-blue-400">{statusCounts.assigned}</p>
            <p className="text-[10px] text-slate-500 uppercase tracking-wider">Assigned</p>
          </div>
          <div className="text-center">
            <p className="text-lg font-bold text-amber-400">{statusCounts.maintenance}</p>
            <p className="text-[10px] text-slate-500 uppercase tracking-wider">Maintenance</p>
          </div>
        </div>
      </div>
    </div>
  );
}
