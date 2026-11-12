import { useState } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import { Search, Filter, X, Calendar, MapPin, User, Truck, AlertTriangle, RotateCcw } from 'lucide-react';

interface FilterToolbarProps {
  onSearchChange: (query: string) => void;
  onStatusChange: (status: string) => void;
  onPriorityChange: (priority: string) => void;
  onCustomerChange: (customer: string) => void;
  onDriverChange: (driver: string) => void;
  onTruckChange: (truck: string) => void;
  onRegionChange: (region: string) => void;
  onDateRangeChange: (range: string) => void;
  onAISuggestedToggle?: (enabled: boolean) => void;
  onDelayedOnlyToggle: (enabled: boolean) => void;
  onReset: () => void;
}

const statusOptions = ['All', 'Pending', 'Assigned', 'In Transit', 'Delivered', 'Delayed'];
const priorityOptions = ['All', 'Critical', 'High', 'Medium', 'Low'];
const customerOptions = ['All', 'Vertex Corp', 'Helix Logistics', 'Nexus Dist', 'Global Freight', 'Zenith Corp'];
const driverOptions = ['All', 'Ahmed K.', 'Sarah J.', 'Bill T.', 'Mark S.'];
const truckOptions = ['All', 'TR-2204', 'TR-1192', 'TR-9942', 'TR-8815'];
const regionOptions = ['All', 'East', 'West', 'South', 'North'];
const dateRangeOptions = ['All Time', 'Today', 'This Week', 'This Month', 'Custom'];

export default function ShipmentFilterToolbar({
  onSearchChange,
  onStatusChange,
  onPriorityChange,
  onCustomerChange,
  onDriverChange,
  onTruckChange,
  onRegionChange,
  onDateRangeChange,
  onAISuggestedToggle,
  onDelayedOnlyToggle,
  onReset,
}: FilterToolbarProps) {
  const [searchQuery, setSearchQuery] = useState('');
  const [selectedStatus, setSelectedStatus] = useState('All');
  const [selectedPriority, setSelectedPriority] = useState('All');
  const [selectedCustomer, setSelectedCustomer] = useState('All');
  const [selectedDriver, setSelectedDriver] = useState('All');
  const [selectedTruck, setSelectedTruck] = useState('All');
  const [selectedRegion, setSelectedRegion] = useState('All');
  const [selectedDateRange, setSelectedDateRange] = useState('All Time');
  const [delayedOnly, setDelayedOnly] = useState(false);
  const [isExpanded, setIsExpanded] = useState(false);

  const handleSearchChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const value = e.target.value;
    setSearchQuery(value);
    onSearchChange(value);
  };

  const handleStatusChange = (status: string) => {
    setSelectedStatus(status);
    onStatusChange(status);
  };

  const handlePriorityChange = (priority: string) => {
    setSelectedPriority(priority);
    onPriorityChange(priority);
  };

  const handleCustomerChange = (customer: string) => {
    setSelectedCustomer(customer);
    onCustomerChange(customer);
  };

  const handleDriverChange = (driver: string) => {
    setSelectedDriver(driver);
    onDriverChange(driver);
  };

  const handleTruckChange = (truck: string) => {
    setSelectedTruck(truck);
    onTruckChange(truck);
  };

  const handleRegionChange = (region: string) => {
    setSelectedRegion(region);
    onRegionChange(region);
  };

  const handleDateRangeChange = (range: string) => {
    setSelectedDateRange(range);
    onDateRangeChange(range);
  };

  const handleDelayedOnlyToggle = () => {
    const newValue = !delayedOnly;
    setDelayedOnly(newValue);
    onDelayedOnlyToggle(newValue);
  };

  const handleReset = () => {
    setSearchQuery('');
    setSelectedStatus('All');
    setSelectedPriority('All');
    setSelectedCustomer('All');
    setSelectedDriver('All');
    setSelectedTruck('All');
    setSelectedRegion('All');
    setSelectedDateRange('All Time');
    setDelayedOnly(false);
    onReset();
  };

  const activeFiltersCount = [
    selectedStatus !== 'All',
    selectedPriority !== 'All',
    selectedCustomer !== 'All',
    selectedDriver !== 'All',
    selectedTruck !== 'All',
    selectedRegion !== 'All',
    selectedDateRange !== 'All Time',
    delayedOnly,
  ].filter(Boolean).length;

  return (
    <div className="bg-[#0E1320] border border-white/5 rounded-2xl p-5 shadow-lg">
      {/* Main Search Bar */}
      <div className="flex flex-col lg:flex-row gap-4 mb-4">
        <div className="flex-1 relative">
          <Search className="absolute left-4 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-500" />
          <input
            type="text"
            value={searchQuery}
            onChange={handleSearchChange}
            placeholder="Search shipments by ID, customer, driver, or truck..."
            className="w-full pl-12 pr-4 py-3 bg-white/5 border border-white/10 rounded-xl text-sm text-white placeholder-slate-500 focus:outline-none focus:border-[#0250C5]/50 focus:ring-1 focus:ring-[#0250C5]/50 transition-all"
          />
        </div>
        
        <div className="flex items-center gap-3">
          <button
            onClick={() => setIsExpanded(!isExpanded)}
            className="flex items-center gap-2 px-4 py-3 bg-white/5 hover:bg-white/10 border border-white/10 rounded-xl transition-all"
          >
            <Filter className="w-4 h-4 text-slate-400" />
            <span className="text-sm font-semibold text-slate-300">Filters</span>
            {activeFiltersCount > 0 && (
              <span className="px-2 py-0.5 bg-[#0250C5] text-white text-xs font-bold rounded-full">
                {activeFiltersCount}
              </span>
            )}
          </button>
          
          <button
            onClick={handleReset}
            className="flex items-center gap-2 px-4 py-3 bg-white/5 hover:bg-white/10 border border-white/10 rounded-xl transition-all"
          >
            <RotateCcw className="w-4 h-4 text-slate-400" />
            <span className="text-sm font-semibold text-slate-300">Reset</span>
          </button>
        </div>
      </div>

      {/* Expanded Filters */}
      <AnimatePresence>
        {isExpanded && (
          <motion.div
            initial={{ opacity: 0, height: 0 }}
            animate={{ opacity: 1, height: 'auto' }}
            exit={{ opacity: 0, height: 0 }}
            transition={{ duration: 0.2 }}
            className="space-y-4"
          >
            {/* Filter Grid */}
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
              {/* Status */}
              <div>
                <label className="flex items-center gap-2 text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">
                  <Truck className="w-3.5 h-3.5" />
                  Status
                </label>
                <select
                  value={selectedStatus}
                  onChange={(e) => handleStatusChange(e.target.value)}
                  className="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white focus:outline-none focus:border-[#0250C5]/50 transition-all"
                >
                  {statusOptions.map((option) => (
                    <option key={option} value={option} className="bg-[#0E1320]">
                      {option}
                    </option>
                  ))}
                </select>
              </div>

              {/* Priority */}
              <div>
                <label className="flex items-center gap-2 text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">
                  <AlertTriangle className="w-3.5 h-3.5" />
                  Priority
                </label>
                <select
                  value={selectedPriority}
                  onChange={(e) => handlePriorityChange(e.target.value)}
                  className="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white focus:outline-none focus:border-[#0250C5]/50 transition-all"
                >
                  {priorityOptions.map((option) => (
                    <option key={option} value={option} className="bg-[#0E1320]">
                      {option}
                    </option>
                  ))}
                </select>
              </div>

              {/* Customer */}
              <div>
                <label className="flex items-center gap-2 text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">
                  <User className="w-3.5 h-3.5" />
                  Customer
                </label>
                <select
                  value={selectedCustomer}
                  onChange={(e) => handleCustomerChange(e.target.value)}
                  className="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white focus:outline-none focus:border-[#0250C5]/50 transition-all"
                >
                  {customerOptions.map((option) => (
                    <option key={option} value={option} className="bg-[#0E1320]">
                      {option}
                    </option>
                  ))}
                </select>
              </div>

              {/* Driver */}
              <div>
                <label className="flex items-center gap-2 text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">
                  <User className="w-3.5 h-3.5" />
                  Driver
                </label>
                <select
                  value={selectedDriver}
                  onChange={(e) => handleDriverChange(e.target.value)}
                  className="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white focus:outline-none focus:border-[#0250C5]/50 transition-all"
                >
                  {driverOptions.map((option) => (
                    <option key={option} value={option} className="bg-[#0E1320]">
                      {option}
                    </option>
                  ))}
                </select>
              </div>

              {/* Truck */}
              <div>
                <label className="flex items-center gap-2 text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">
                  <Truck className="w-3.5 h-3.5" />
                  Truck
                </label>
                <select
                  value={selectedTruck}
                  onChange={(e) => handleTruckChange(e.target.value)}
                  className="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white focus:outline-none focus:border-[#0250C5]/50 transition-all"
                >
                  {truckOptions.map((option) => (
                    <option key={option} value={option} className="bg-[#0E1320]">
                      {option}
                    </option>
                  ))}
                </select>
              </div>

              {/* Region */}
              <div>
                <label className="flex items-center gap-2 text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">
                  <MapPin className="w-3.5 h-3.5" />
                  Region
                </label>
                <select
                  value={selectedRegion}
                  onChange={(e) => handleRegionChange(e.target.value)}
                  className="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white focus:outline-none focus:border-[#0250C5]/50 transition-all"
                >
                  {regionOptions.map((option) => (
                    <option key={option} value={option} className="bg-[#0E1320]">
                      {option}
                    </option>
                  ))}
                </select>
              </div>

              {/* Date Range */}
              <div>
                <label className="flex items-center gap-2 text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">
                  <Calendar className="w-3.5 h-3.5" />
                  Date Range
                </label>
                <select
                  value={selectedDateRange}
                  onChange={(e) => handleDateRangeChange(e.target.value)}
                  className="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white focus:outline-none focus:border-[#0250C5]/50 transition-all"
                >
                  {dateRangeOptions.map((option) => (
                    <option key={option} value={option} className="bg-[#0E1320]">
                      {option}
                    </option>
                  ))}
                </select>
              </div>
            </div>

            {/* Toggle Filters */}
            <div className="flex flex-wrap gap-3">
              <button
                onClick={handleDelayedOnlyToggle}
                className={`flex items-center gap-2 px-4 py-2 rounded-lg border transition-all ${
                  delayedOnly
                    ? 'bg-red-500/10 border-red-500/20 text-red-400'
                    : 'bg-white/5 border-white/10 text-slate-400 hover:bg-white/10'
                }`}
              >
                <AlertTriangle className="w-4 h-4" />
                <span className="text-sm font-semibold">Delayed Only</span>
              </button>
            </div>
          </motion.div>
        )}
      </AnimatePresence>

      {/* Active Filter Chips */}
      {activeFiltersCount > 0 && (
        <div className="flex flex-wrap gap-2 mt-4 pt-4 border-t border-white/5">
          {selectedStatus !== 'All' && (
            <FilterChip label={`Status: ${selectedStatus}`} onRemove={() => handleStatusChange('All')} />
          )}
          {selectedPriority !== 'All' && (
            <FilterChip label={`Priority: ${selectedPriority}`} onRemove={() => handlePriorityChange('All')} />
          )}
          {selectedCustomer !== 'All' && (
            <FilterChip label={`Customer: ${selectedCustomer}`} onRemove={() => handleCustomerChange('All')} />
          )}
          {selectedDriver !== 'All' && (
            <FilterChip label={`Driver: ${selectedDriver}`} onRemove={() => handleDriverChange('All')} />
          )}
          {selectedTruck !== 'All' && (
            <FilterChip label={`Truck: ${selectedTruck}`} onRemove={() => handleTruckChange('All')} />
          )}
          {selectedRegion !== 'All' && (
            <FilterChip label={`Region: ${selectedRegion}`} onRemove={() => handleRegionChange('All')} />
          )}
          {selectedDateRange !== 'All Time' && (
            <FilterChip label={`Date: ${selectedDateRange}`} onRemove={() => handleDateRangeChange('All Time')} />
          )}
          {delayedOnly && (
            <FilterChip label="Delayed Only" onRemove={handleDelayedOnlyToggle} />
          )}
        </div>
      )}
    </div>
  );
}

function FilterChip({ label, onRemove }: { label: string; onRemove: () => void }) {
  return (
    <div className="flex items-center gap-2 px-3 py-1.5 bg-[#0250C5]/10 border border-[#0250C5]/20 rounded-lg">
      <span className="text-xs font-semibold text-[#00E5FF]">{label}</span>
      <button
        onClick={onRemove}
        className="hover:text-white transition-colors"
      >
        <X className="w-3 h-3 text-[#00E5FF]" />
      </button>
    </div>
  );
}
