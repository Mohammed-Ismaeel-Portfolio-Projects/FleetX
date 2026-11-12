import { Users } from 'lucide-react';
import type { Driver, DriverPagination } from '../types/driver-operations.types';
import DriverCard from './DriverCard';

interface DriverListProps {
  drivers: Driver[];
  selectedDriverId: string;
  onDriverSelect: (driver: Driver) => void;
  pagination?: DriverPagination | null;
  onPageChange?: (page: number) => void;
  error?: string | null;
}

export default function DriverList({
  drivers,
  selectedDriverId,
  onDriverSelect,
  pagination,
  onPageChange,
  error,
}: DriverListProps) {
  return (
    <div className="bg-[#0E1320] border border-white/5 rounded-2xl shadow-lg flex flex-col h-full">
      <div className="p-5 border-b border-white/5">
        <div className="flex items-center justify-between">
          <h2 className="text-sm font-bold text-white font-sans flex items-center gap-2">
            <Users className="w-4 h-4 text-[#00E5FF]" />
            Driver List
          </h2>
          <span className="text-xs font-semibold text-slate-400">
            {pagination ? `${pagination.total} total` : `${drivers.length} drivers`}
          </span>
        </div>
      </div>

      <div className="flex-1 overflow-y-auto p-4 space-y-3">
        {error ? (
          <div className="rounded-xl border border-red-500/20 bg-red-500/10 px-3 py-2">
            <p className="text-xs text-red-400">{error}</p>
          </div>
        ) : null}

        {drivers.length === 0 && !error ? (
          <div className="text-center py-12">
            <Users className="w-12 h-12 text-slate-600 mx-auto mb-3" />
            <p className="text-sm text-slate-400">No drivers match the current filters</p>
          </div>
        ) : null}

        {drivers.length === 0 && error ? (
          <div className="text-center py-8">
            <p className="text-sm text-slate-400">Driver roster unavailable</p>
          </div>
        ) : null}

        {drivers.map((driver) => (
          <DriverCard
            key={driver.id}
            driver={driver}
            isSelected={selectedDriverId === driver.id}
            onSelect={onDriverSelect}
          />
        ))}
      </div>

      {pagination && pagination.last_page > 1 ? (
        <div className="p-4 border-t border-white/5 flex items-center justify-between">
          <p className="text-xs text-slate-500">
            Page {pagination.current_page} of {pagination.last_page}
          </p>
          <div className="flex gap-2">
            <button
              type="button"
              disabled={pagination.current_page <= 1}
              onClick={() => onPageChange?.(pagination.current_page - 1)}
              className="px-3 py-1.5 text-xs font-semibold text-slate-400 hover:text-white bg-white/5 rounded-lg disabled:opacity-50"
            >
              Previous
            </button>
            <button
              type="button"
              disabled={pagination.current_page >= pagination.last_page}
              onClick={() => onPageChange?.(pagination.current_page + 1)}
              className="px-3 py-1.5 text-xs font-semibold text-slate-400 hover:text-white bg-white/5 rounded-lg disabled:opacity-50"
            >
              Next
            </button>
          </div>
        </div>
      ) : null}
    </div>
  );
}
