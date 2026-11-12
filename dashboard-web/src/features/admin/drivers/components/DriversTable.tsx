import { ChevronDown, ChevronUp, Inbox, ServerCrash, User } from 'lucide-react';
import Pagination from './Pagination';
import StatusSwitch from './StatusSwitch';
import DriverActionsDropdown from './DriverActionsDropdown';
import type { Driver, DriverCollection, DriverSortField } from '../types/driver.types';

interface DriversTableProps {
  drivers: Driver[];
  isLoading: boolean;
  error: string | null;
  pagination: DriverCollection['meta'];
  sortField: DriverSortField;
  sortDirection: 'asc' | 'desc';
  hasFilters: boolean;
  statusLoadingIds: Set<string>;
  actionDisabled: boolean;
  statusNameById: Record<number, string>;
  onSort: (field: DriverSortField) => void;
  onRetry: () => void;
  onPageChange: (page: number) => void;
  onPerPageChange: (perPage: number) => void;
  onView: (driver: Driver) => void;
  onEdit: (driver: Driver) => void;
  onDelete: (driver: Driver) => void;
  onRestore: (driver: Driver) => void;
  onToggleStatus: (driver: Driver, nextValue: boolean) => void;
}

const columns = [
  { label: 'Employee #', sort: 'employee_number', className: 'min-w-[130px]' },
  { label: 'Driver', sort: 'first_name', className: 'min-w-[180px]' },
  { label: 'Phone', className: 'min-w-[140px]' },
  { label: 'License Number', className: 'min-w-[140px]' },
  { label: 'License Expiry', className: 'min-w-[130px]' },
  { label: 'Medical Expiry', className: 'min-w-[130px]' },
  { label: 'Experience', sort: 'years_of_experience', className: 'min-w-[120px]' },
  { label: 'Status', className: 'min-w-[210px]' },
  { label: 'Actions', className: 'w-[100px] text-right' },
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

export default function DriversTable({
  drivers,
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
}: DriversTableProps) {
  if (error) {
    return (
      <div className="rounded-2xl border border-red-500/20 bg-[#0E1320] p-12 text-center shadow-md">
        <ServerCrash className="mx-auto h-12 w-12 text-red-300" />
        <h3 className="mt-4 text-sm font-bold text-white">Drivers could not be loaded</h3>
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

  if (!isLoading && drivers.length === 0) {
    return (
      <div className="rounded-2xl border border-white/5 bg-[#0E1320] p-12 text-center shadow-md">
        <Inbox className="mx-auto h-12 w-12 text-slate-600" />
        <h3 className="mt-4 text-sm font-bold text-white">No drivers found</h3>
        <p className="mt-2 text-xs text-slate-500">
          {hasFilters ? 'Try adjusting your search or filters.' : 'No drivers are currently available from the API.'}
        </p>
      </div>
    );
  }

  return (
    <div className="overflow-hidden rounded-2xl border border-white/5 bg-[#0E1320] shadow-md">
      <div className="overflow-x-auto">
        <table className="min-w-300 w-full border-collapse">
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
                      onClick={() => onSort(column.sort as DriverSortField)}
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
              drivers.map((driver) => (
                <tr key={driver.id} className="transition-colors hover:bg-white/2">
                  <td className="px-4 py-3">
                    <div className="flex items-center gap-2">
                      <span className="rounded-xl border border-secondary/20 bg-secondary/10 p-2 text-accent">
                        <User className="h-4 w-4" />
                      </span>
                      <span className="font-mono text-xs font-bold text-white">{driver.employee_number}</span>
                    </div>
                  </td>
                  <td className="px-4 py-3">
                    <div className="flex flex-col">
                      <span className="text-xs font-bold text-white">{driver.full_name}</span>
                      <span className="text-[10px] text-slate-500">{driver.email}</span>
                    </div>
                  </td>
                  <td className="px-4 py-3 text-xs font-mono text-slate-300">{driver.phone}</td>
                  <td className="px-4 py-3 text-xs font-mono text-slate-300">{driver.license_number}</td>
                  <td className="px-4 py-3 text-xs text-slate-300">{formatDate(driver.license_expiry_date)}</td>
                  <td className="px-4 py-3 text-xs text-slate-300">{formatDate(driver.medical_certificate_expiry)}</td>
                  <td className="px-4 py-3 text-xs font-mono text-slate-300">
                    {driver.years_of_experience != null ? `${driver.years_of_experience} years` : 'Not set'}
                  </td>
                  <td className="px-4 py-3">
                    <div className="flex items-center justify-between gap-3">
                      <div className="flex flex-col gap-1">
                        <span className={`inline-flex rounded-full border px-2 py-1 text-[11px] font-bold ${
                          driver.is_active
                            ? 'border-emerald-500/20 bg-emerald-500/10 text-emerald-300'
                            : 'border-slate-500/20 bg-slate-500/10 text-slate-400'
                        }`}>
                          {statusNameById[driver.status_id] || `Status #${driver.status_id}`}
                        </span>
                        <span className="text-[10px] text-slate-500">{driver.is_active ? 'Active' : 'Inactive'}</span>
                      </div>
                      <StatusSwitch
                        isActive={driver.is_active}
                        isLoading={statusLoadingIds.has(driver.id)}
                        disabled={actionDisabled}
                        onToggle={(nextValue) => onToggleStatus(driver, nextValue)}
                      />
                    </div>
                  </td>
                  <td className="px-4 py-3 text-right">
                    <DriverActionsDropdown
                      driver={driver}
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
