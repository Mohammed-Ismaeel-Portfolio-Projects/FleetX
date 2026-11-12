import { ChevronDown, ChevronUp, Fuel, Inbox, ServerCrash, Truck, User } from 'lucide-react';
import Pagination from './Pagination';
import StatusSwitch from './StatusSwitch';
import VehicleActionsDropdown from './VehicleActionsDropdown';
import type { Vehicle, VehicleCollection, VehicleSortField } from '../types/vehicle.types';

interface VehiclesTableProps {
  vehicles: Vehicle[];
  isLoading: boolean;
  error: string | null;
  pagination: VehicleCollection['meta'];
  sortField: VehicleSortField;
  sortDirection: 'asc' | 'desc';
  hasFilters: boolean;
  statusLoadingIds: Set<string>;
  actionDisabled: boolean;
  vehicleTypeNameById: Record<number, string>;
  fuelTypeNameById: Record<number, string>;
  statusNameById: Record<number, string>;
  onSort: (field: VehicleSortField) => void;
  onRetry: () => void;
  onPageChange: (page: number) => void;
  onPerPageChange: (perPage: number) => void;
  onView: (vehicle: Vehicle) => void;
  onEdit: (vehicle: Vehicle) => void;
  onDelete: (vehicle: Vehicle) => void;
  onRestore: (vehicle: Vehicle) => void;
  onToggleStatus: (vehicle: Vehicle, nextValue: boolean) => void;
}

const columns = [
  { label: 'Operational Code', sort: 'operational_code', className: 'min-w-[140px]' },
  { label: 'Fleet Number', sort: 'fleet_number', className: 'min-w-[130px]' },
  { label: 'Plate Number', sort: 'plate_number', className: 'min-w-[130px]' },
  { label: 'Manufacturer', sort: 'manufacturer', className: 'min-w-[130px]' },
  { label: 'Model', sort: 'model', className: 'min-w-[130px]' },
  { label: 'Vehicle Type', className: 'min-w-[150px]' },
  { label: 'Paired Driver', className: 'min-w-[160px]' },
  { label: 'Fuel Type', className: 'min-w-[130px]' },
  { label: 'Status', className: 'min-w-[170px]' },
  { label: 'Current Odometer', sort: 'current_odometer', className: 'min-w-[150px]' },
  { label: 'Insurance Expiry', className: 'min-w-[150px]' },
  { label: 'Inspection Expiry', className: 'min-w-[150px]' },
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

export default function VehiclesTable({
  vehicles,
  isLoading,
  error,
  pagination,
  sortField,
  sortDirection,
  hasFilters,
  statusLoadingIds,
  actionDisabled,
  vehicleTypeNameById,
  fuelTypeNameById,
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
}: VehiclesTableProps) {
  if (error) {
    return (
      <div className="rounded-2xl border border-red-500/20 bg-[#0E1320] p-12 text-center shadow-md">
        <ServerCrash className="mx-auto h-12 w-12 text-red-300" />
        <h3 className="mt-4 text-sm font-bold text-white">Vehicles could not be loaded</h3>
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

  if (!isLoading && vehicles.length === 0) {
    return (
      <div className="rounded-2xl border border-white/5 bg-[#0E1320] p-12 text-center shadow-md">
        <Inbox className="mx-auto h-12 w-12 text-slate-600" />
        <h3 className="mt-4 text-sm font-bold text-white">No vehicles found</h3>
        <p className="mt-2 text-xs text-slate-500">
          {hasFilters ? 'Try adjusting your search or filters.' : 'No vehicles are currently available from the API.'}
        </p>
      </div>
    );
  }

  return (
    <div className="overflow-hidden rounded-2xl border border-white/5 bg-[#0E1320] shadow-md">
      <div className="overflow-x-auto">
        <table className="min-w-[1760px] w-full border-collapse">
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
                      onClick={() => onSort(column.sort as VehicleSortField)}
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
              vehicles.map((vehicle) => (
                <tr key={vehicle.id} className="transition-colors hover:bg-white/2">
                  <td className="px-4 py-3">
                    <div className="flex items-center gap-2">
                      <span className="rounded-xl border border-secondary/20 bg-secondary/10 p-2 text-accent">
                        <Truck className="h-4 w-4" />
                      </span>
                      <span className="font-mono text-xs font-bold text-white">{vehicle.operational_code}</span>
                    </div>
                  </td>
                  <td className="px-4 py-3 text-xs font-mono font-bold text-slate-300">{vehicle.fleet_number}</td>
                  <td className="px-4 py-3 text-xs font-mono text-slate-300">{vehicle.plate_number}</td>
                  <td className="px-4 py-3 text-xs text-slate-300">{vehicle.manufacturer}</td>
                  <td className="px-4 py-3 text-xs text-slate-300">{vehicle.model}</td>
                  <td className="px-4 py-3 text-xs text-slate-300">{vehicleTypeNameById[vehicle.vehicle_type_id] || `Type #${vehicle.vehicle_type_id}`}</td>
                  <td className="px-4 py-3 text-xs text-slate-300">
                    {vehicle.driver ? (
                      <div className="flex items-center gap-1.5">
                        <User className="h-3.5 w-3.5 text-cyan-400 shrink-0" />
                        <div>
                          <p className="font-semibold text-white">{vehicle.driver.full_name || `${vehicle.driver.first_name} ${vehicle.driver.last_name}`}</p>
                          <p className="text-[10px] text-slate-400">#{vehicle.driver.employee_number}</p>
                        </div>
                      </div>
                    ) : (
                      <span className="text-slate-500 italic">No paired driver</span>
                    )}
                  </td>
                  <td className="px-4 py-3 text-xs text-slate-300">
                    <div className="flex items-center gap-2">
                      <Fuel className="h-3.5 w-3.5 text-slate-500" />
                      <span>{fuelTypeNameById[vehicle.fuel_type_id] || `Fuel #${vehicle.fuel_type_id}`}</span>
                    </div>
                  </td>
                  <td className="px-4 py-3">
                    <div className="flex items-center justify-between gap-4">
                      <span className={`inline-flex rounded-full border px-2 py-1 text-[11px] font-bold ${
                        vehicle.is_active
                          ? 'border-emerald-500/20 bg-emerald-500/10 text-emerald-300'
                          : 'border-slate-500/20 bg-slate-500/10 text-slate-400'
                      }`}>
                        {statusNameById[vehicle.status_id] || `Status #${vehicle.status_id}`}
                      </span>
                      <StatusSwitch
                        isActive={vehicle.is_active}
                        isLoading={statusLoadingIds.has(vehicle.id)}
                        disabled={actionDisabled}
                        onToggle={(nextValue) => onToggleStatus(vehicle, nextValue)}
                      />
                    </div>
                    <p className="mt-2 text-[10px] text-slate-500">{vehicle.is_active ? 'Active' : 'Inactive'}</p>
                  </td>
                  <td className="px-4 py-3 text-xs font-mono text-slate-300">
                    {vehicle.current_odometer != null ? `${vehicle.current_odometer.toLocaleString()} km` : 'Not set'}
                  </td>
                  <td className="px-4 py-3 text-xs text-slate-300">{formatDate(vehicle.insurance_expiry_date)}</td>
                  <td className="px-4 py-3 text-xs text-slate-300">{formatDate(vehicle.inspection_expiry_date)}</td>
                  <td className="px-4 py-3 text-right">
                    <VehicleActionsDropdown
                      vehicle={vehicle}
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
