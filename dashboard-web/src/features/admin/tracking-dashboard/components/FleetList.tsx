import { Search, Filter } from 'lucide-react';
import FleetCard from './FleetCard';
import type { FleetVehicle } from '../types/tracking-dashboard.types';
import { cn } from "../../../../lib/utils";

interface FleetListProps {
  vehicles: FleetVehicle[];
  selectedVehicleId?: string;
  isLoading?: boolean;
  searchValue?: string;
  onSearchChange?: (value: string) => void;
  onVehicleSelect?: (vehicle: FleetVehicle) => void;
}

export default function FleetList({
  vehicles,
  selectedVehicleId,
  isLoading = false,
  searchValue = '',
  onSearchChange,
  onVehicleSelect,
}: FleetListProps) {
  return (
    <div className={cn('flex', 'flex-col', 'gap-4', 'rounded-2xl', 'border', 'border-white/5', 'bg-[#0E1320]', 'p-4', 'shadow-md', 'h-[calc(100vh-320px)]')}>
      <div className={cn('flex', 'flex-col', 'gap-3')}>
        <h2 className={cn('text-sm', 'font-semibold', 'text-white')}>Fleet List</h2>
        <div className="relative">
          <Search className={cn('absolute', 'left-3', 'top-1/2', 'h-4', 'w-4', '-translate-y-1/2', 'text-slate-500')} />
          <input
            type="text"
            value={searchValue}
            onChange={(e) => onSearchChange?.(e.target.value)}
            placeholder="Search vehicles..."
            className={cn('w-full', 'rounded-xl', 'border', 'border-white/5', 'bg-[#0A0E17]', 'pl-10', 'pr-4', 'py-2', 'text-sm', 'text-white', 'placeholder:text-slate-500', 'outline-none', 'transition-all', 'focus:border-[#0250C5]/50', 'focus:ring-1', 'focus:ring-[#0250C5]/20')}
          />
        </div>
        <button
          type="button"
          className={cn('inline-flex', 'items-center', 'gap-2', 'rounded-xl', 'border', 'border-white/10', 'bg-[#0A0E17]', 'px-3', 'py-2', 'text-xs', 'font-medium', 'text-slate-300', 'transition-colors', 'hover:bg-white/5', 'hover:text-white')}
        >
          <Filter className={cn('h-3', 'w-3')} />
          Filter
        </button>
      </div>

      <div className={cn('flex-1', 'overflow-y-auto', 'min-h-0')}>
        {isLoading ? (
          <div className="space-y-3">
            {Array.from({ length: 5 }).map((_, index) => (
              <div
                key={index}
                className={cn('h-24', 'animate-pulse', 'rounded-xl', 'bg-white/5')}
              />
            ))}
          </div>
        ) : vehicles.length === 0 ? (
          <div className={cn('flex', 'h-48', 'items-center', 'justify-center', 'text-center')}>
            <div>
              <p className={cn('text-sm', 'font-semibold', 'text-white')}>No vehicles found</p>
              <p className={cn('mt-1', 'text-xs', 'text-slate-500')}>
                Try adjusting your search or filters.
              </p>
            </div>
          </div>
        ) : (
          <div className="space-y-3">
            {vehicles.map((vehicle) => (
              <FleetCard
                key={vehicle.id}
                vehicle={vehicle}
                isSelected={vehicle.id === selectedVehicleId}
                onClick={() => onVehicleSelect?.(vehicle)}
              />
            ))}
          </div>
        )}
      </div>
    </div>
  );
}
