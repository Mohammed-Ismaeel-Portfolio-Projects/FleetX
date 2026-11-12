import { RefreshCw, Filter, Search } from 'lucide-react';

interface TrackingHeaderProps {
  onRefresh?: () => void;
  onFilter?: () => void;
  searchValue?: string;
  onSearchChange?: (value: string) => void;
}

export default function TrackingHeader({
  onRefresh,
  onFilter,
  searchValue = '',
  onSearchChange,
}: TrackingHeaderProps) {
  return (
    <div className="flex flex-col gap-4">
      <div>
        <h1 className="text-2xl font-bold tracking-tight text-white">Fleet Tracking</h1>
        <p className="mt-1 text-sm text-slate-400">
          Monitor real-time vehicle locations, driver performance, and fleet status.
        </p>
      </div>

      <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div className="flex flex-1 items-center gap-3">
          <div className="relative flex-1 max-w-md">
            <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-500" />
            <input
              type="text"
              value={searchValue}
              onChange={(e) => onSearchChange?.(e.target.value)}
              placeholder="Search vehicles, drivers, shipments..."
              className="w-full rounded-xl border border-white/5 bg-[#0A0E17] pl-10 pr-4 py-2.5 text-sm text-white placeholder:text-slate-500 outline-none transition-all focus:border-[#0250C5]/50 focus:ring-1 focus:ring-[#0250C5]/20"
            />
          </div>
        </div>

        <div className="flex items-center gap-2">
          <button
            type="button"
            onClick={onFilter}
            className="inline-flex items-center gap-2 rounded-xl border border-white/10 bg-[#0A0E17] px-4 py-2.5 text-sm font-medium text-slate-300 transition-colors hover:bg-white/5 hover:text-white"
          >
            <Filter className="h-4 w-4" />
            Filters
          </button>
          <button
            type="button"
            onClick={onRefresh}
            className="inline-flex items-center gap-2 rounded-xl border border-white/10 bg-[#0A0E17] px-4 py-2.5 text-sm font-medium text-slate-300 transition-colors hover:bg-white/5 hover:text-white"
          >
            <RefreshCw className="h-4 w-4" />
            Refresh
          </button>
        </div>
      </div>
    </div>
  );
}
