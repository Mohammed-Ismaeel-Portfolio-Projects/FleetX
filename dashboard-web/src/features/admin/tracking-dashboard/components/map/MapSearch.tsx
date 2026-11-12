import { memo, useState, useMemo } from 'react';
import { Search, X } from 'lucide-react';
import type { FleetVehicle } from '../../types/tracking-dashboard.types';

interface MapSearchProps {
  vehicles: FleetVehicle[];
  onVehicleSelect: (vehicle: FleetVehicle) => void;
  onCenterMap?: (vehicle: FleetVehicle) => void;
}

interface SearchResult {
  vehicle: FleetVehicle;
  type: 'fleet' | 'driver' | 'shipment' | 'assignment';
  label: string;
}

function MapSearchComponent({ vehicles, onVehicleSelect, onCenterMap }: MapSearchProps) {
  const [query, setQuery] = useState('');
  const [results, setResults] = useState<SearchResult[]>([]);
  const [isOpen, setIsOpen] = useState(false);

  const searchResults = useMemo(() => {
    if (!query.trim()) return [];

    const q = query.toLowerCase();
    const results: SearchResult[] = [];

    vehicles.forEach(vehicle => {
      // Search by fleet number
      if (vehicle.fleetNumber.toLowerCase().includes(q)) {
        results.push({
          vehicle,
          type: 'fleet',
          label: vehicle.fleetNumber,
        });
      }
      // Search by driver
      if (vehicle.driver.toLowerCase().includes(q)) {
        results.push({
          vehicle,
          type: 'driver',
          label: vehicle.driver,
        });
      }
      // Search by shipment
      if (vehicle.shipment.toLowerCase().includes(q)) {
        results.push({
          vehicle,
          type: 'shipment',
          label: vehicle.shipment,
        });
      }
    });

    // Remove duplicates and limit results
    const uniqueResults = results.filter((result, index, self) =>
      index === self.findIndex(r => r.vehicle.id === result.vehicle.id)
    ).slice(0, 8);

    return uniqueResults;
  }, [query, vehicles]);

  const handleSelect = (result: SearchResult) => {
    onVehicleSelect(result.vehicle);
    onCenterMap?.(result.vehicle);
    setQuery('');
    setResults([]);
    setIsOpen(false);
  };

  const handleClear = () => {
    setQuery('');
    setResults([]);
    setIsOpen(false);
  };

  const getTypeColor = (type: string) => {
    switch (type) {
      case 'fleet': return 'text-cyan-400';
      case 'driver': return 'text-green-400';
      case 'shipment': return 'text-yellow-400';
      case 'assignment': return 'text-purple-400';
      default: return 'text-slate-400';
    }
  };

  const getTypeLabel = (type: string) => {
    switch (type) {
      case 'fleet': return 'Fleet';
      case 'driver': return 'Driver';
      case 'shipment': return 'Shipment';
      case 'assignment': return 'Assignment';
      default: return type;
    }
  };

  return (
    <div className="absolute left-4 bottom-24 z-[1000] w-72">
      <div className="relative">
        <div className="relative flex items-center">
          <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-500" />
          <input
            type="text"
            value={query}
            onChange={(e) => {
              setQuery(e.target.value);
              setResults(searchResults);
              setIsOpen(true);
            }}
            onFocus={() => setIsOpen(true)}
            placeholder="Search vehicles, drivers, shipments..."
            className="w-full rounded-xl border border-white/10 bg-[#0A0E17]/90 backdrop-blur-sm pl-10 pr-10 py-2.5 text-sm text-white placeholder:text-slate-500 outline-none transition-all focus:border-cyan-500/50 focus:ring-1 focus:ring-cyan-500/20 shadow-lg"
          />
          {query && (
            <button
              type="button"
              onClick={handleClear}
              className="absolute right-3 top-1/2 -translate-y-1/2 text-slate-500 hover:text-white transition-colors"
            >
              <X className="h-4 w-4" />
            </button>
          )}
        </div>

        {isOpen && results.length > 0 && (
          <div className="absolute top-full left-0 right-0 mt-2 rounded-xl border border-white/10 bg-[#0E1320]/95 backdrop-blur-sm shadow-2xl overflow-hidden">
            {results.map((result, index) => (
              <button
                key={`${result.vehicle.id}-${result.type}-${index}`}
                type="button"
                onClick={() => handleSelect(result)}
                className="w-full flex items-center gap-3 px-4 py-3 text-left hover:bg-white/5 transition-colors border-b border-white/5 last:border-b-0"
              >
                <div className="flex-1 min-w-0">
                  <p className="text-sm font-medium text-white truncate">{result.label}</p>
                  <p className="text-xs text-slate-400">
                    <span className={getTypeColor(result.type)}>{getTypeLabel(result.type)}</span>
                    {' • '}{result.vehicle.fleetNumber}
                  </p>
                </div>
              </button>
            ))}
          </div>
        )}

        {isOpen && query && results.length === 0 && (
          <div className="absolute top-full left-0 right-0 mt-2 rounded-xl border border-white/10 bg-[#0E1320]/95 backdrop-blur-sm shadow-2xl px-4 py-3">
            <p className="text-sm text-slate-400">No results found</p>
          </div>
        )}
      </div>
    </div>
  );
}

export default memo(MapSearchComponent);
