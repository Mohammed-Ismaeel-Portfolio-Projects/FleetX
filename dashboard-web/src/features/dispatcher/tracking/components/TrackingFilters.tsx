import { useState } from 'react';
import { Search, X, RotateCcw } from 'lucide-react';

interface TrackingFiltersProps {
  onSearchChange: (value: string) => void;
  onStatusChange: (value: string) => void;
  onRegionChange: (value: string) => void;
  onDriverChange: (value: string) => void;
  onDateRangeChange: (value: string) => void;
  onReset: () => void;
}

export default function TrackingFilters({
  onSearchChange,
  onStatusChange,
  onRegionChange,
  onDriverChange,
  onDateRangeChange,
  onReset,
}: TrackingFiltersProps) {
  const [searchQuery, setSearchQuery] = useState('');
  const [statusFilter, setStatusFilter] = useState('All');
  const [regionFilter, setRegionFilter] = useState('All');
  const [driverFilter, setDriverFilter] = useState('All');
  const [dateRangeFilter, setDateRangeFilter] = useState('All Time');
  const [activeFilters, setActiveFilters] = useState<string[]>([]);

  const handleSearchChange = (value: string) => {
    setSearchQuery(value);
    onSearchChange(value);
  };

  const handleStatusChange = (value: string) => {
    setStatusFilter(value);
    onStatusChange(value);
    updateActiveFilters('status', value);
  };

  const handleRegionChange = (value: string) => {
    setRegionFilter(value);
    onRegionChange(value);
    updateActiveFilters('region', value);
  };

  const handleDriverChange = (value: string) => {
    setDriverFilter(value);
    onDriverChange(value);
    updateActiveFilters('driver', value);
  };

  const handleDateRangeChange = (value: string) => {
    setDateRangeFilter(value);
    onDateRangeChange(value);
  };

  const handleReset = () => {
    setSearchQuery('');
    setStatusFilter('All');
    setRegionFilter('All');
    setDriverFilter('All');
    setDateRangeFilter('All Time');
    setActiveFilters([]);
    onReset();
  };

  const updateActiveFilters = (type: string, value: string) => {
    if (value === 'All') {
      setActiveFilters(prev => prev.filter(f => !f.startsWith(type)));
    } else {
      setActiveFilters(prev => {
        const filtered = prev.filter(f => !f.startsWith(type));
        return [...filtered, `${type}:${value}`];
      });
    }
  };

  const removeFilter = (filter: string) => {
    const [type] = filter.split(':');
    if (type === 'status') handleStatusChange('All');
    if (type === 'region') handleRegionChange('All');
    if (type === 'driver') handleDriverChange('All');
  };

  return (
    <div className="bg-[#0E1320] border border-white/5 rounded-2xl p-5 shadow-lg">
      <div className="flex flex-col lg:flex-row items-start lg:items-center justify-between gap-4">
        {/* Search */}
        <div className="relative flex-1 max-w-md">
          <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-500" />
          <input
            type="text"
            value={searchQuery}
            onChange={(e) => handleSearchChange(e.target.value)}
            placeholder="Search vehicles, drivers, shipments..."
            className="w-full pl-10 pr-4 py-2.5 bg-white/5 border border-white/10 rounded-xl text-sm text-white placeholder-slate-500 focus:outline-none focus:border-[#0250C5]/50 transition-all"
          />
        </div>

        {/* Filters */}
        <div className="flex flex-wrap items-center gap-3">
          {/* Status */}
          <select
            value={statusFilter}
            onChange={(e) => handleStatusChange(e.target.value)}
            className="px-4 py-2.5 bg-white/5 border border-white/10 rounded-xl text-sm text-white focus:outline-none focus:border-[#0250C5]/50 transition-all"
          >
            <option value="All" className="bg-[#0E1320]">All Statuses</option>
            <option value="Active" className="bg-[#0E1320]">Active</option>
            <option value="In Transit" className="bg-[#0E1320]">In Transit</option>
            <option value="Idle" className="bg-[#0E1320]">Idle</option>
            <option value="Delayed" className="bg-[#0E1320]">Delayed</option>
            <option value="Offline" className="bg-[#0E1320]">Offline</option>
          </select>

          {/* Region */}
          <select
            value={regionFilter}
            onChange={(e) => handleRegionChange(e.target.value)}
            className="px-4 py-2.5 bg-white/5 border border-white/10 rounded-xl text-sm text-white focus:outline-none focus:border-[#0250C5]/50 transition-all"
          >
            <option value="All" className="bg-[#0E1320]">All Regions</option>
            <option value="East" className="bg-[#0E1320]">East</option>
            <option value="West" className="bg-[#0E1320]">West</option>
            <option value="South" className="bg-[0E1320]">South</option>
            <option value="North" className="bg-[#0E1320]">North</option>
          </select>

          {/* Driver */}
          <select
            value={driverFilter}
            onChange={(e) => handleDriverChange(e.target.value)}
            className="px-4 py-2.5 bg-white/5 border border-white/10 rounded-xl text-sm text-white focus:outline-none focus:border-[#0250C5]/50 transition-all"
          >
            <option value="All" className="bg-[#0E1320]">All Drivers</option>
            <option value="Ahmed K." className="bg-[#0E1320]">Ahmed K.</option>
            <option value="Sarah J." className="bg-[#0E1320]">Sarah J.</option>
            <option value="Bill T." className="bg-[#0E1320]">Bill T.</option>
            <option value="Mark S." className="bg-[#0E1320]">Mark S.</option>
            <option value="Khalid M." className="bg-[#0E1320]">Khalid M.</option>
          </select>

          {/* Date Range */}
          <select
            value={dateRangeFilter}
            onChange={(e) => handleDateRangeChange(e.target.value)}
            className="px-4 py-2.5 bg-white/5 border border-white/10 rounded-xl text-sm text-white focus:outline-none focus:border-[#0250C5]/50 transition-all"
          >
            <option value="All Time" className="bg-[#0E1320]">All Time</option>
            <option value="Today" className="bg-[#0E1320]">Today</option>
            <option value="This Week" className="bg-[#0E1320]">This Week</option>
            <option value="This Month" className="bg-[#0E1320]">This Month</option>
          </select>

          {/* Reset */}
          <button
            onClick={handleReset}
            className="flex items-center gap-2 px-4 py-2.5 bg-white/5 hover:bg-white/10 border border-white/10 rounded-xl transition-all"
          >
            <RotateCcw className="w-4 h-4 text-slate-400" />
            <span className="text-sm font-semibold text-slate-300">Reset</span>
          </button>
        </div>
      </div>

      {/* Active Filter Chips */}
      {activeFilters.length > 0 && (
        <div className="flex flex-wrap items-center gap-2 mt-4 pt-4 border-t border-white/5">
          <span className="text-xs font-semibold text-slate-500 uppercase tracking-wider">Active Filters:</span>
          {activeFilters.map((filter) => (
            <button
              key={filter}
              onClick={() => removeFilter(filter)}
              className="flex items-center gap-1.5 px-3 py-1 bg-[#0250C5]/10 border border-[#0250C5]/20 rounded-lg text-xs font-semibold text-[#00E5FF] hover:bg-[#0250C5]/20 transition-all"
            >
              {filter.split(':')[1]}
              <X className="w-3 h-3" />
            </button>
          ))}
        </div>
      )}
    </div>
  );
}
export type { TrackingFiltersProps };
