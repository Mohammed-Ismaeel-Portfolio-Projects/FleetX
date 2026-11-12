import { memo, useState } from 'react';
import { X } from 'lucide-react';

type FilterType = 'moving' | 'idle' | 'offline' | 'alerts' | 'trucks' | 'trailers' | 'all';

interface QuickFiltersProps {
  onFilterChange: (filters: FilterType[]) => void;
}

const filters: { id: FilterType; label: string; color: string }[] = [
  { id: 'all', label: 'All', color: 'bg-cyan-500' },
  { id: 'moving', label: 'Moving', color: 'bg-green-500' },
  { id: 'idle', label: 'Idle', color: 'bg-yellow-500' },
  { id: 'offline', label: 'Offline', color: 'bg-slate-500' },
  { id: 'alerts', label: 'Alerts', color: 'bg-red-500' },
  { id: 'trucks', label: 'Trucks', color: 'bg-blue-500' },
  { id: 'trailers', label: 'Trailers', color: 'bg-purple-500' },
];

function QuickFiltersComponent({ onFilterChange }: QuickFiltersProps) {
  const [activeFilters, setActiveFilters] = useState<FilterType[]>(['all']);

  const toggleFilter = (filterId: FilterType) => {
    let newFilters: FilterType[];
    
    if (filterId === 'all') {
      newFilters = ['all'];
    } else {
      newFilters = activeFilters.filter(f => f !== 'all');
      if (newFilters.includes(filterId)) {
        newFilters = newFilters.filter(f => f !== filterId);
        if (newFilters.length === 0) {
          newFilters = ['all'];
        }
      } else {
        newFilters.push(filterId);
      }
    }
    
    setActiveFilters(newFilters);
    onFilterChange(newFilters);
  };

  const clearFilters = () => {
    setActiveFilters(['all']);
    onFilterChange(['all']);
  };

  return (
    <div className="flex flex-wrap gap-2">
      {filters.map((filter) => {
        const isActive = activeFilters.includes(filter.id);
        return (
          <button
            key={filter.id}
            type="button"
            onClick={() => toggleFilter(filter.id)}
            className={`
              inline-flex items-center gap-1.5 rounded-full border px-3 py-1.5 text-xs font-medium transition-all
              ${isActive 
                ? `${filter.color} text-white border-transparent shadow-lg` 
                : 'bg-[#0A0E17]/80 backdrop-blur-sm border-white/10 text-slate-300 hover:bg-white/5 hover:text-white'
              }
            `}
          >
            <span className={`w-2 h-2 rounded-full ${isActive ? 'bg-white' : filter.color}`} />
            {filter.label}
          </button>
        );
      })}
      {activeFilters.length > 1 && (
        <button
          type="button"
          onClick={clearFilters}
          className="inline-flex items-center gap-1.5 rounded-full border border-white/10 bg-[#0A0E17]/80 backdrop-blur-sm px-3 py-1.5 text-xs font-medium text-slate-300 transition-all hover:bg-white/5 hover:text-white"
        >
          <X className="h-3 w-3" />
          Clear
        </button>
      )}
    </div>
  );
}

export default memo(QuickFiltersComponent);
