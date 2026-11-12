import { ChevronDown, ChevronUp, Inbox, Package, ServerCrash } from 'lucide-react';
import Pagination from './Pagination';
import StatusSwitch from './StatusSwitch';
import TrailerActionsDropdown from './TrailerActionsDropdown';
import type { Trailer, TrailerCollection, TrailerSortField } from '../types/trailer.types';

interface TrailersTableProps {
  trailers: Trailer[];
  isLoading: boolean;
  error: string | null;
  pagination: TrailerCollection['meta'];
  sortField: TrailerSortField;
  sortDirection: 'asc' | 'desc';
  hasFilters: boolean;
  statusLoadingIds: Set<string>;
  actionDisabled: boolean;
  statusNameById: Record<number, string>;
  onSort: (field: TrailerSortField) => void;
  onRetry: () => void;
  onPageChange: (page: number) => void;
  onPerPageChange: (perPage: number) => void;
  onView: (trailer: Trailer) => void;
  onEdit: (trailer: Trailer) => void;
  onDelete: (trailer: Trailer) => void;
  onRestore: (trailer: Trailer) => void;
  onToggleStatus: (trailer: Trailer, nextValue: boolean) => void;
}

const columns = [
  { label: 'Trailer Code', sort: 'trailer_code', className: 'min-w-[140px]' },
  { label: 'Fleet Number', sort: 'fleet_number', className: 'min-w-[130px]' },
  { label: 'Plate Number', sort: 'plate_number', className: 'min-w-[130px]' },
  { label: 'Trailer Type', className: 'min-w-[180px]' },
  { label: 'Manufacturer', sort: 'manufacturer', className: 'min-w-[150px]' },
  { label: 'Status', className: 'min-w-[170px]' },
  { label: 'Payload (kg)', className: 'min-w-[120px]' },
  { label: 'Inspection Expiry', className: 'min-w-[150px]' },
  { label: 'Insurance Expiry', className: 'min-w-[150px]' },
  { label: 'Actions', className: 'w-[90px] text-right' },
];

function SkeletonRows() {
  return (
    <>
      {Array.from({ length: 8 }).map((_, index) => (
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

function formatDate(value: string | null) {
  if (!value) {
    return 'Not set';
  }

  return new Date(value).toLocaleDateString();
}

export default function TrailersTable({
  trailers,
  isLoading,
  error,
  pagination,
  sortField,
  sortDirection,
  hasFilters,
  statusLoadingIds,
  actionDisabled,
  statusNameById,
  onSort,
  onRetry,
  onPageChange,
  onPerPageChange,
  onView,
  onEdit,
  onDelete,
  onRestore,
  onToggleStatus,
}: TrailersTableProps) {
  if (error) {
    return (
      <div className="rounded-2xl border border-red-500/20 bg-[#0E1320] p-12 text-center shadow-md">
        <ServerCrash className="mx-auto h-12 w-12 text-red-300" />
        <h3 className="mt-4 text-sm font-bold text-white">Trailers could not be loaded</h3>
        <p className="mx-auto mt-2 max-w-md text-xs leading-5 text-slate-500">{error}</p>
        <button
          type="button"
          onClick={onRetry}
          className="mt-5 rounded-xl bg-secondary px-4 py-2.5 text-sm font-bold text-white transition-colors hover:bg-[#0244a5]"
        >
          Retry
        </button>
      </div>
    );
  }

  if (!isLoading && trailers.length === 0) {
    return (
      <div className="rounded-2xl border border-white/5 bg-[#0E1320] p-12 text-center shadow-md">
        <Inbox className="mx-auto h-12 w-12 text-slate-600" />
        <h3 className="mt-4 text-sm font-bold text-white">No trailers found</h3>
        <p className="mt-2 text-xs text-slate-500">
          {hasFilters ? 'Try adjusting your search or filters.' : 'No trailers are currently available from the API.'}
        </p>
      </div>
    );
  }

  return (
    <div className="overflow-hidden rounded-2xl border border-white/5 bg-[#0E1320] shadow-md">
      <div className="overflow-x-auto">
        <table className="min-w-350 w-full border-collapse">
          <thead className="sticky top-0 z-10 bg-[#0A0E17]">
            <tr className="border-b border-white/5">
              {columns.map((column) => (
                <th
                  key={column.label}
                  className={`px-4 py-3 text-left text-[10px] font-bold uppercase tracking-widest text-slate-500 ${column.className}`}
                >
                  {column.sort ? (
                    <button
                      type="button"
                      onClick={() => onSort(column.sort as TrailerSortField)}
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
              trailers.map((trailer) => (
                <tr key={trailer.id} className="transition-colors hover:bg-white/2">
                  <td className="px-4 py-3">
                    <div className="flex items-center gap-2">
                      <span className="rounded-xl border border-secondary/20 bg-secondary/10 p-2 text-accent">
                        <Package className="h-4 w-4" />
                      </span>
                      <span className="font-mono text-xs font-bold text-white">{trailer.trailer_code}</span>
                    </div>
                  </td>
                  <td className="px-4 py-3 text-xs font-mono font-bold text-slate-300">{trailer.fleet_number}</td>
                  <td className="px-4 py-3 text-xs font-mono text-slate-300">{trailer.plate_number}</td>
                  <td className="px-4 py-3 text-xs text-slate-300">{trailer.trailer_type}</td>
                  <td className="px-4 py-3 text-xs text-slate-300">{trailer.manufacturer}</td>
                  <td className="px-4 py-3">
                    <div className="flex items-center justify-between gap-4">
                      <span className={`inline-flex rounded-full border px-2 py-1 text-[11px] font-bold ${
                        trailer.is_active
                          ? 'border-emerald-500/20 bg-emerald-500/10 text-emerald-300'
                          : 'border-slate-500/20 bg-slate-500/10 text-slate-400'
                      }`}>
                        {statusNameById[trailer.status_id] || `Status #${trailer.status_id}`}
                      </span>
                      <StatusSwitch
                        isActive={trailer.is_active}
                        isLoading={statusLoadingIds.has(trailer.id)}
                        disabled={actionDisabled}
                        onToggle={(nextValue) => onToggleStatus(trailer, nextValue)}
                      />
                    </div>
                    <p className="mt-2 text-[10px] text-slate-500">{trailer.is_active ? 'Active' : 'Inactive'}</p>
                  </td>
                  <td className="px-4 py-3 text-xs font-mono text-slate-300">
                    {trailer.payload_capacity_kg != null ? `${trailer.payload_capacity_kg.toLocaleString()} kg` : 'Not set'}
                  </td>
                  <td className="px-4 py-3 text-xs text-slate-300">{formatDate(trailer.inspection_expiry_date)}</td>
                  <td className="px-4 py-3 text-xs text-slate-300">{formatDate(trailer.insurance_expiry_date)}</td>
                  <td className="px-4 py-3 text-right">
                    <TrailerActionsDropdown
                      trailer={trailer}
                      disabled={actionDisabled}
                      onView={onView}
                      onEdit={onEdit}
                      onDelete={onDelete}
                      onRestore={onRestore}
                    />
                  </td>
                </tr>
              ))
            )}
          </tbody>
        </table>
      </div>

      <Pagination
        pagination={pagination}
        onPageChange={onPageChange}
        onPerPageChange={onPerPageChange}
      />
    </div>
  );
}
