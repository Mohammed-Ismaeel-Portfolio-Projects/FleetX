import { ChevronDown, ChevronUp, Eye, Inbox, ServerCrash } from 'lucide-react';
import type { Shipment, ShipmentSortField } from '../types/shipment.types';

interface ShipmentsTableProps {
  shipments: Shipment[];
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
  sortField: ShipmentSortField;
  sortDirection: 'asc' | 'desc';
  hasFilters: boolean;
  onSort: (field: ShipmentSortField) => void;
  onRetry: () => void;
  onPageChange: (page: number) => void;
  onPerPageChange: (perPage: number) => void;
  onView: (shipment: Shipment) => void;
}

const columns: Array<{ label: string; sort?: ShipmentSortField; className: string }> = [
  { label: 'Shipment #', sort: 'shipment_number', className: 'min-w-[160px]' },
  { label: 'Customer', className: 'min-w-[220px]' },
  { label: 'Pickup', className: 'min-w-[170px]' },
  { label: 'Delivery', className: 'min-w-[170px]' },
  { label: 'Priority', sort: 'priority', className: 'min-w-[130px]' },
  { label: 'Status', className: 'min-w-[180px]' },
  { label: 'Planned Pickup', sort: 'planned_pickup_date', className: 'min-w-[150px]' },
  { label: 'Actions', className: 'w-[90px] text-right' },
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

export default function ShipmentsTable({
  shipments,
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
}: ShipmentsTableProps) {
  if (error) {
    return (
      <div className="rounded-2xl border border-red-500/20 bg-[#0E1320] p-12 text-center shadow-md">
        <ServerCrash className="mx-auto h-12 w-12 text-red-300" />
        <h3 className="mt-4 text-sm font-bold text-white">Shipments could not be loaded</h3>
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

  if (!isLoading && shipments.length === 0) {
    return (
      <div className="rounded-2xl border border-white/5 bg-[#0E1320] p-12 text-center shadow-md">
        <Inbox className="mx-auto h-12 w-12 text-slate-600" />
        <h3 className="mt-4 text-sm font-bold text-white">No shipments found</h3>
        <p className="mt-2 text-xs text-slate-500">
          {hasFilters ? 'Try adjusting your search or filters.' : 'No shipments are available to display.'}
        </p>
      </div>
    );
  }

  return (
    <div className="overflow-hidden rounded-2xl border border-white/5 bg-[#0E1320] shadow-md">
      <div className="overflow-x-auto">
        <table className="w-full min-w-[1200px] border-collapse text-sm">
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
              shipments.map((shipment) => (
                <tr key={shipment.id} className="transition-colors hover:bg-white/[0.02]">
                  <td className="px-4 py-3 font-mono text-cyan-400">{shipment.shipment_number}</td>
                  <td className="px-4 py-3 text-white">{shipment.customer_company?.name || 'N/A'}</td>
                  <td className="px-4 py-3 text-slate-300">{shipment.pickup_city ? `${shipment.pickup_city}, ${shipment.pickup_country}` : 'N/A'}</td>
                  <td className="px-4 py-3 text-slate-300">{shipment.delivery_city ? `${shipment.delivery_city}, ${shipment.delivery_country}` : 'N/A'}</td>
                  <td className="px-4 py-3">
                    <span className="inline-flex rounded-full px-2 py-1 text-xs font-medium capitalize bg-slate-500/10 text-slate-200">
                      {shipment.priority}
                    </span>
                  </td>
                  <td className="px-4 py-3">
                    <span className="inline-flex rounded-full bg-[#111827] px-3 py-1 text-xs font-semibold uppercase tracking-widest text-slate-300">
                      {shipment.status?.name || 'Unknown'}
                    </span>
                  </td>
                  <td className="px-4 py-3 text-slate-300">{shipment.planned_pickup_date || 'N/A'}</td>
                  <td className="px-4 py-3 text-right">
                    <button
                      type="button"
                      onClick={() => onView(shipment)}
                      className="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-white/5 text-slate-400 transition-colors hover:bg-white/5 hover:text-white"
                      aria-label={`View ${shipment.shipment_number}`}
                      title="View details"
                    >
                      <Eye className="h-4 w-4" />
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
          Showing {pagination.from ?? 0} to {pagination.to ?? 0} of {pagination.total} shipments
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
