import { ChevronDown, ChevronUp, Inbox, ServerCrash } from 'lucide-react';
import type { TrackingState, TrackingSortField } from '../types/tracking.types';

interface TrackingTableProps {
  trackingStates: TrackingState[];
  isLoading: boolean;
  error: string | null;
  pagination: {
    current_page: number;
    per_page: number;
    total: number;
    last_page: number;
    from: number | null;
    to: number | null;
  };
  sortField: TrackingSortField;
  sortDirection: 'asc' | 'desc';
  hasFilters: boolean;
  onSort: (field: TrackingSortField) => void;
  onRetry: () => void;
  onPageChange: (page: number) => void;
  onPerPageChange: (perPage: number) => void;
  onView: (trackingState: TrackingState) => void;
}

const columns: Array<{ label: string; sort?: TrackingSortField; className: string }> = [
  { label: 'Vehicle', sort: 'last_update_at', className: 'min-w-[140px]' },
  { label: 'Driver', className: 'min-w-[160px]' },
  { label: 'Shipment', className: 'min-w-[140px]' },
  { label: 'Assignment', className: 'min-w-[120px]' },
  { label: 'Location', className: 'min-w-[180px]' },
  { label: 'Speed', className: 'min-w-[80px]' },
  { label: 'Movement', className: 'min-w-[100px]' },
  { label: 'Online', className: 'min-w-[80px]' },
  { label: 'Signal', className: 'min-w-[80px]' },
  { label: 'Last Update', sort: 'last_update_at', className: 'min-w-[150px]' },
  { label: 'Actions', className: 'w-[80px] text-right' },
];

function SkeletonRows() {
  return (
    <>
      {Array.from({ length: 6 }).map((_, index) => (
        <tr key={index} className="border-b border-white/5">
          {columns.map((column) => (
            <td key={column.label} className="px-4 py-4">
              <div className="h-4 animate-pulse rounded bg-white/5" />
            </td>
          ))}
        </tr>
      ))}
    </>
  );
}

function SortIcon({ active, direction }: { active: boolean; direction: 'asc' | 'desc' }) {
  if (!active) {
    return <ChevronDown className="h-3 w-3 opacity-30" />;
  }

  return direction === 'asc' ? <ChevronUp className="h-3 w-3" /> : <ChevronDown className="h-3 w-3" />;
}

function getMovementStatusColor(status: string): string {
  switch (status) {
    case 'Moving':
      return 'bg-green-500/10 text-green-300';
    case 'Stopped':
      return 'bg-yellow-500/10 text-yellow-300';
    case 'Idle':
      return 'bg-slate-500/10 text-slate-300';
    default:
      return 'bg-slate-500/10 text-slate-300';
  }
}

function getOnlineStatusColor(status: string): string {
  switch (status) {
    case 'Online':
      return 'bg-green-500/10 text-green-300';
    case 'Offline':
      return 'bg-red-500/10 text-red-300';
    default:
      return 'bg-slate-500/10 text-slate-300';
  }
}

function getSignalQualityColor(quality: string): string {
  switch (quality) {
    case 'Good':
      return 'bg-green-500/10 text-green-300';
    case 'Weak':
      return 'bg-yellow-500/10 text-yellow-300';
    case 'Lost':
      return 'bg-red-500/10 text-red-300';
    default:
      return 'bg-slate-500/10 text-slate-300';
  }
}

export default function TrackingTable({
  trackingStates,
  isLoading,
  error,
  pagination,
  sortField,
  sortDirection,
  hasFilters,
  onSort,
  onRetry,
  onPageChange,
  onPerPageChange,
  onView,
}: TrackingTableProps) {
  if (error) {
    return (
      <div className="rounded-2xl border border-red-500/20 bg-[#0E1320] p-12 text-center shadow-md">
        <ServerCrash className="mx-auto h-12 w-12 text-red-300" />
        <h3 className="mt-4 text-sm font-bold text-white">Tracking states could not be loaded</h3>
        <p className="mx-auto mt-2 max-w-md text-xs leading-5 text-slate-500">{error}</p>
        <button
          type="button"
          onClick={onRetry}
          className="mt-5 rounded-xl bg-secondary px-4 py-2.5 text-sm font-bold text-white transition-colors hover:bg-secondary/80"
        >
          Retry
        </button>
      </div>
    );
  }

  if (!isLoading && trackingStates.length === 0) {
    return (
      <div className="rounded-2xl border border-white/5 bg-[#0E1320] p-12 text-center shadow-md">
        <Inbox className="mx-auto h-12 w-12 text-slate-600" />
        <h3 className="mt-4 text-sm font-bold text-white">No tracking states found</h3>
        <p className="mt-2 text-xs text-slate-500">
          {hasFilters ? 'Try adjusting your search or filters.' : 'No tracking data available yet.'}
        </p>
      </div>
    );
  }

  return (
    <div className="overflow-hidden rounded-2xl border border-white/5 bg-[#0E1320] shadow-md">
      <div className="overflow-x-auto">
        <table className="w-full min-w-[1300px] border-collapse text-sm">
          <thead className="sticky top-0 z-10 bg-[#0A0E17]">
            <tr className="border-b border-white/5">
              {columns.map((column) => (
                <th key={column.label} className={`px-4 py-3 text-left text-[10px] font-bold uppercase tracking-widest text-slate-500 ${column.className}`}>
                  {column.sort ? (
                    <button
                      type="button"
                      onClick={() => onSort(column.sort!)}
                      className="inline-flex items-center gap-1.5 transition-colors hover:text-white"
                    >
                      {column.label}
                      <SortIcon active={sortField === column.sort} direction={sortDirection} />
                    </button>
                  ) : (
                    column.label
                  )}
                </th>
              ))}
            </tr>
          </thead>
          <tbody className="divide-y divide-white/5">
            {isLoading ? (
              <SkeletonRows />
            ) : (
              trackingStates.map((state) => (
                <tr key={state.id} className="transition-colors hover:bg-white/[0.02]">
                  <td className="px-4 py-3 font-mono text-cyan-400">{state.vehicle?.fleet_number || 'N/A'}</td>
                  <td className="px-4 py-3 text-white">{state.driver?.full_name || 'N/A'}</td>
                  <td className="px-4 py-3 font-mono text-slate-300">{state.shipment?.shipment_number || 'N/A'}</td>
                  <td className="px-4 py-3 font-mono text-slate-300">{state.assignment?.assignment_code || 'N/A'}</td>
                  <td className="px-4 py-3 text-slate-300">
                    {state.latitude.toFixed(6)}, {state.longitude.toFixed(6)}
                  </td>
                  <td className="px-4 py-3 text-slate-300">{state.speed.toFixed(1)} km/h</td>
                  <td className="px-4 py-3">
                    <span className={`inline-flex rounded-full px-2 py-1 text-xs font-medium capitalize ${getMovementStatusColor(state.movement_status)}`}>
                      {state.movement_status}
                    </span>
                  </td>
                  <td className="px-4 py-3">
                    <span className={`inline-flex rounded-full px-2 py-1 text-xs font-medium capitalize ${getOnlineStatusColor(state.online_status)}`}>
                      {state.online_status}
                    </span>
                  </td>
                  <td className="px-4 py-3">
                    <span className={`inline-flex rounded-full px-2 py-1 text-xs font-medium capitalize ${getSignalQualityColor(state.signal_quality)}`}>
                      {state.signal_quality}
                    </span>
                  </td>
                  <td className="px-4 py-3 text-slate-300">
                    {state.last_update_at ? new Date(state.last_update_at).toLocaleString() : 'N/A'}
                  </td>
                  <td className="px-4 py-3 text-right">
                    <button
                      type="button"
                      onClick={() => onView(state)}
                      className="rounded-lg border border-white/10 px-3 py-1.5 text-xs text-white transition-colors hover:bg-white/5"
                    >
                      View
                    </button>
                  </td>
                </tr>
              ))
            )}
          </tbody>
        </table>
      </div>

      <div className="flex flex-col gap-3 border-t border-white/5 px-4 py-4 md:flex-row md:items-center md:justify-between">
        <div className="text-xs text-slate-400">
          Showing {pagination.from ?? 0} to {pagination.to ?? 0} of {pagination.total} tracking states
        </div>
        <div className="flex flex-wrap items-center gap-2">
          <button
            type="button"
            onClick={() => onPageChange(Math.max(1, pagination.current_page - 1))}
            disabled={pagination.current_page === 1}
            className="rounded-xl border border-white/10 px-3 py-2 text-xs text-white disabled:opacity-50 disabled:cursor-not-allowed hover:bg-white/5"
          >
            Previous
          </button>
          <span className="text-xs text-slate-400">Page {pagination.current_page} of {pagination.last_page}</span>
          <button
            type="button"
            onClick={() => onPageChange(Math.min(pagination.last_page, pagination.current_page + 1))}
            disabled={pagination.current_page === pagination.last_page}
            className="rounded-xl border border-white/10 px-3 py-2 text-xs text-white disabled:opacity-50 disabled:cursor-not-allowed hover:bg-white/5"
          >
            Next
          </button>
          <select
            value={pagination.per_page}
            onChange={(event) => onPerPageChange(Number(event.target.value))}
            className="rounded-xl border border-white/10 bg-[#090D16] px-3 py-2 text-xs text-white outline-none"
          >
            {[10, 15, 25, 50].map((value) => (
              <option key={value} value={value}>{value} per page</option>
            ))}
          </select>
        </div>
      </div>
    </div>
  );
}
