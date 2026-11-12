import { useState } from 'react';
import { Search, Filter, ArrowUpDown, Truck } from 'lucide-react';
import type { LiveVehicle } from '../types/tracking.types';
import VehicleCard from './VehicleCard';

interface FleetSidebarProps {
  vehicles: LiveVehicle[];
  selectedVehicleId: string | null;
  onVehicleSelect: (vehicle: LiveVehicle) => void;
}

export default function FleetSidebar({ vehicles, selectedVehicleId, onVehicleSelect }: FleetSidebarProps) {
  const [searchQuery, setSearchQuery] = useState('');
  const [statusFilter, setStatusFilter] = useState('All');
  const [sortBy, setSortBy] = useState('status');

  const filteredVehicles = vehicles.filter((vehicle) => {
    const matchesSearch = 
      vehicle.vehicleCode.toLowerCase().includes(searchQuery.toLowerCase()) ||
      vehicle.driverName.toLowerCase().includes(searchQuery.toLowerCase()) ||
      vehicle.shipmentNumber.toLowerCase().includes(searchQuery.toLowerCase());

    const matchesStatus = statusFilter === 'All' || vehicle.status === statusFilter;

    return matchesSearch && matchesStatus;
  });

  const sortedVehicles = [...filteredVehicles].sort((a, b) => {
    switch (sortBy) {
      case 'status':
        return a.status.localeCompare(b.status);
      case 'speed':
        return b.speed - a.speed;
      default:
        return 0;
    }
  });

  const statusOptions = ['All', 'Active', 'In Transit', 'Idle', 'Delayed', 'Offline'];

  return (
    <div className="bg-[#0E1320] border border-white/5 rounded-2xl shadow-lg h-full flex flex-col">
      {/* Header */}
      <div className="p-4 border-b border-white/5">
        <div className="flex items-center justify-between mb-4">
          <h2 className="text-sm font-bold text-white font-sans flex items-center gap-2">
            <Truck className="w-4 h-4 text-[#00E5FF]" />
            Fleet List
          </h2>
          <span className="text-xs font-semibold text-slate-400">{vehicles.length} Vehicles</span>
        </div>

        {/* Search */}
        <div className="relative mb-3">
          <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-500" />
          <input
            type="text"
            value={searchQuery}
            onChange={(e) => setSearchQuery(e.target.value)}
            placeholder="Search vehicles..."
            className="w-full pl-10 pr-4 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white placeholder-slate-500 focus:outline-none focus:border-[#0250C5]/50 transition-all"
          />
        </div>

        {/* Filters */}
        <div className="flex gap-2 mb-3">
          <select
            value={statusFilter}
            onChange={(e) => setStatusFilter(e.target.value)}
            className="flex-1 px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-xs text-white focus:outline-none focus:border-[#0250C5]/50 transition-all"
          >
            {statusOptions.map((option) => (
              <option key={option} value={option} className="bg-[#0E1320]">
                {option}
              </option>
            ))}
          </select>

          <button className="p-2 bg-white/5 hover:bg-white/10 border border-white/10 rounded-lg transition-all">
            <Filter className="w-4 h-4 text-slate-400" />
          </button>
        </div>

        {/* Sort */}
        <button
          onClick={() => setSortBy(sortBy === 'status' ? 'speed' : sortBy === 'speed' ? 'eta' : 'status')}
          className="flex items-center gap-2 text-xs font-semibold text-slate-400 hover:text-white transition-colors"
        >
          <ArrowUpDown className="w-3.5 h-3.5" />
          <span>Sort by {sortBy}</span>
        </button>
      </div>

      {/* Vehicle List */}
      <div className="flex-1 overflow-y-auto p-4 space-y-3">
        {sortedVehicles.length === 0 ? (
          <div className="flex flex-col items-center justify-center h-full text-center">
            <Truck className="w-12 h-12 text-slate-600 mb-3" />
            <p className="text-sm font-semibold text-slate-400">No vehicles found</p>
            <p className="text-xs text-slate-500">Try adjusting your search or filters</p>
          </div>
        ) : (
          sortedVehicles.map((vehicle) => (
            <VehicleCard
              key={vehicle.vehicleId}
              vehicle={vehicle}
              isSelected={selectedVehicleId === vehicle.vehicleId}
              onClick={() => onVehicleSelect(vehicle)}
            />
          ))
        )}
      </div>

      {/* Footer Stats */}
      <div className="p-4 border-t border-white/5">
        <div className="grid grid-cols-2 gap-3">
          <div className="bg-white/[0.02] border border-white/5 rounded-lg p-3">
            <p className="text-[10px] text-slate-500 uppercase tracking-wider mb-1">Active</p>
            <p className="text-lg font-bold text-emerald-400">
              {vehicles.filter(v => v.status === 'Active' || v.status === 'In Transit').length}
            </p>
          </div>
          <div className="bg-white/[0.02] border border-white/5 rounded-lg p-3">
            <p className="text-[10px] text-slate-500 uppercase tracking-wider mb-1">Issues</p>
            <p className="text-lg font-bold text-red-400">
              {vehicles.filter(v => v.status === 'Delayed' || v.status === 'Offline').length}
            </p>
          </div>
        </div>
      </div>
    </div>
  );
}
