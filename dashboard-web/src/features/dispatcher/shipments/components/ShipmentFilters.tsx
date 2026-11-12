import { Search, RotateCcw } from 'lucide-react';
import type { UseShipmentFiltersReturn } from '../hooks/useShipmentFilters';
import type { ShipmentStatus, ShipmentPriority } from '../types/shipment.types';

interface ShipmentFiltersProps {
  filters: UseShipmentFiltersReturn;
}

export default function ShipmentFilters({ filters }: ShipmentFiltersProps) {
  const {
    searchQuery,
    setSearchQuery,
    statusFilter,
    setStatusFilter,
    priorityFilter,
    setPriorityFilter,
    regionFilter,
    setRegionFilter,
    resetFilters,
  } = filters;

  return (
    <div className="bg-[#0E1320] border border-white/5 rounded-2xl p-5 flex flex-col md:flex-row md:items-center justify-between gap-4 select-none text-left shadow-md w-full">
      
      {/* 1. Search + Filter icon */}
      <div className="flex items-center gap-3.5 flex-grow max-w-md">
        <div className="relative w-full bg-[#090D16] border border-white/5 rounded-xl h-11 flex items-center px-4">
          <Search className="w-4 h-4 text-slate-500 flex-shrink-0 mr-3" />
          <input 
            type="text"
            value={searchQuery}
            onChange={(e) => setSearchQuery(e.target.value)}
            placeholder="Search shipment ID, customer, or driver..."
            className="bg-transparent text-xs text-white placeholder-slate-500 w-full focus:outline-none font-sans"
            aria-label="Search shipment items"
          />
        </div>
      </div>

      {/* 2. Select filter groups */}
      <div className="flex flex-wrap items-center gap-3.5 w-full md:w-auto">
        
        {/* Status Filter */}
        <div className="flex flex-col gap-1 text-[8px] font-bold text-slate-500 uppercase tracking-widest font-sans">
          <span>Status</span>
          <select
            value={statusFilter}
            onChange={(e) => setStatusFilter(e.target.value as ShipmentStatus | 'All')}
            className="bg-[#090D16] border border-white/5 text-xs text-slate-300 rounded-lg px-3 py-2 cursor-pointer font-sans focus:outline-none"
            aria-label="Filter by status"
          >
            <option value="All">All Statuses</option>
            <option value="Pending">Pending</option>
            <option value="Assigned">Assigned</option>
            <option value="In Transit">In Transit</option>
            <option value="Delivered">Delivered</option>
            <option value="Delayed">Delayed</option>
          </select>
        </div>

        {/* Priority Filter */}
        <div className="flex flex-col gap-1 text-[8px] font-bold text-slate-500 uppercase tracking-widest font-sans">
          <span>Priority</span>
          <select
            value={priorityFilter}
            onChange={(e) => setPriorityFilter(e.target.value as ShipmentPriority | 'All')}
            className="bg-[#090D16] border border-white/5 text-xs text-slate-300 rounded-lg px-3 py-2 cursor-pointer font-sans focus:outline-none"
            aria-label="Filter by priority"
          >
            <option value="All">All Priorities</option>
            <option value="Low">Low</option>
            <option value="Medium">Medium</option>
            <option value="High">High</option>
            <option value="Critical">Critical</option>
          </select>
        </div>

        {/* Region Filter */}
        <div className="flex flex-col gap-1 text-[8px] font-bold text-slate-500 uppercase tracking-widest font-sans">
          <span>Region</span>
          <select
            value={regionFilter}
            onChange={(e) => setRegionFilter(e.target.value)}
            className="bg-[#090D16] border border-white/5 text-xs text-slate-300 rounded-lg px-3 py-2 cursor-pointer font-sans focus:outline-none"
            aria-label="Filter by region"
          >
            <option value="All">All Regions</option>
            <option value="East">East Region</option>
            <option value="West">West Region</option>
            <option value="South">South Region</option>
            <option value="North">North Region</option>
          </select>
        </div>

        {/* Reset Button */}
        <button
          onClick={resetFilters}
          className="px-3 py-2.5 bg-white/2 hover:bg-white/5 border border-white/5 rounded-lg active:scale-[0.98] transition-all text-slate-400 hover:text-white text-[10px] font-bold uppercase tracking-wider flex items-center gap-1.5 cursor-pointer mt-auto"
        >
          <RotateCcw className="w-3.5 h-3.5" />
          <span>Reset</span>
        </button>

      </div>

    </div>
  );
}
export type { ShipmentFiltersProps };
