import { useEffect, useMemo, useState } from 'react';
import { Plus, Truck } from 'lucide-react';
import { useToast } from '../../../../shared/components/toast';
import { vehiclesService } from '../services/vehicles.service';
import { useVehicles } from '../hooks/useVehicles';
import FiltersBar from '../components/FiltersBar';
import VehiclesTable from '../components/VehiclesTable';
import VehicleModal from '../components/VehicleModal';
import VehicleDetailsDrawer from '../components/VehicleDetailsDrawer';
import DeleteDialog from '../components/DeleteDialog';
import PairDriverModal from '../components/PairDriverModal';
import { emptyVehicleForm, formDataToPayload, vehicleToFormData } from '../components/VehicleForm';
import type {
  Vehicle,
  VehicleFormData,
  VehicleSortField,
  VehicleValidationErrors,
} from '../types/vehicle.types';

interface LookupOption {
  value: string;
  label: string;
}

type ModalMode = 'create' | 'edit';

type ConfirmMode = 'delete' | 'restore';

const vehicleTypeOptions: LookupOption[] = [
  { value: '1', label: 'Tractor Unit' },
  { value: '2', label: 'Box Truck' },
  { value: '3', label: 'Flatbed Truck' },
  { value: '4', label: 'Refrigerated Truck' },
  { value: '5', label: 'Container Chassis' },
  { value: '6', label: 'Tanker Truck' },
  { value: '7', label: 'Heavy Haul Truck' },
  { value: '8', label: 'Curtainsider Truck' },
  { value: '9', label: 'Rigid Truck' },
  { value: '10', label: 'Low Loader' },
];

const fuelTypeOptions: LookupOption[] = [
  { value: '1', label: 'Diesel' },
  { value: '2', label: 'LNG' },
  { value: '3', label: 'CNG' },
  { value: '4', label: 'Electric' },
  { value: '5', label: 'Hybrid' },
];

const vehicleStatusOptions: LookupOption[] = [
  { value: '31', label: 'Available' },
  { value: '32', label: 'Assigned' },
  { value: '33', label: 'In Transit' },
  { value: '34', label: 'Loading' },
  { value: '35', label: 'Unloading' },
  { value: '36', label: 'At Customs' },
  { value: '37', label: 'Maintenance' },
  { value: '38', label: 'Out of Service' },
  { value: '39', label: 'Inactive' },
];

const defaultParams = {
  page: 1,
  per_page: 15,
  sort_by: 'created_at' as VehicleSortField,
  sort_direction: 'desc' as const,
};

function normalizeSearchValue(value: string) {
  return value.trim() || undefined;
}

export default function VehiclesPage() {
  const toast = useToast();
  const {
    vehicles,
    isLoading,
    error,
    pagination,
    updateParams,
    refresh,
    replaceVehicle,
  } = useVehicles(defaultParams);

  const [searchQuery, setSearchQuery] = useState('');
  const [vehicleTypeFilter, setVehicleTypeFilter] = useState('');
  const [fuelTypeFilter, setFuelTypeFilter] = useState('');
  const [statusFilter, setStatusFilter] = useState('');
  const [manufacturerFilter, setManufacturerFilter] = useState('');
  const [activeFilter, setActiveFilter] = useState<'all' | 'active' | 'inactive'>('all');
  const [sortField, setSortField] = useState<VehicleSortField>('created_at');
  const [sortDirection, setSortDirection] = useState<'asc' | 'desc'>('desc');

  const [modalMode, setModalMode] = useState<ModalMode>('create');
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [selectedVehicle, setSelectedVehicle] = useState<Vehicle | null>(null);
  const [formData, setFormData] = useState<VehicleFormData>(emptyVehicleForm);
  const [formErrors, setFormErrors] = useState<VehicleValidationErrors>({});
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [drawerVehicle, setDrawerVehicle] = useState<Vehicle | null>(null);
  const [isDrawerOpen, setIsDrawerOpen] = useState(false);
  const [isDrawerLoading, setIsDrawerLoading] = useState(false);
  const [pairingVehicle, setPairingVehicle] = useState<Vehicle | null>(null);
  const [confirmMode, setConfirmMode] = useState<ConfirmMode>('delete');
  const [confirmVehicle, setConfirmVehicle] = useState<Vehicle | null>(null);
  const [isConfirmSubmitting, setIsConfirmSubmitting] = useState(false);
  const [statusLoadingIds, setStatusLoadingIds] = useState<Set<string>>(new Set());

  useEffect(() => {
    const timeoutId = window.setTimeout(() => {
      updateParams({
        search: normalizeSearchValue(searchQuery),
        vehicle_type_id: vehicleTypeFilter ? Number(vehicleTypeFilter) : undefined,
        fuel_type_id: fuelTypeFilter ? Number(fuelTypeFilter) : undefined,
        status_id: statusFilter ? Number(statusFilter) : undefined,
        manufacturer: normalizeSearchValue(manufacturerFilter),
        is_active: activeFilter === 'all' ? undefined : activeFilter === 'active',
        page: 1,
      });
    }, 400);

    return () => window.clearTimeout(timeoutId);
  }, [
    activeFilter,
    fuelTypeFilter,
    manufacturerFilter,
    searchQuery,
    statusFilter,
    updateParams,
    vehicleTypeFilter,
  ]);

  const vehicleTypeNameById = useMemo(() => Object.fromEntries(
    vehicleTypeOptions.map((option) => [Number(option.value), option.label]),
  ) as Record<number, string>, []);

  const fuelTypeNameById = useMemo(() => Object.fromEntries(
    fuelTypeOptions.map((option) => [Number(option.value), option.label]),
  ) as Record<number, string>, []);

  const statusNameById = useMemo(() => Object.fromEntries(
    vehicleStatusOptions.map((option) => [Number(option.value), option.label]),
  ) as Record<number, string>, []);

  const hasFilters = Boolean(
    searchQuery
    || vehicleTypeFilter
    || fuelTypeFilter
    || statusFilter
    || manufacturerFilter
    || activeFilter !== 'all'
  );

  const actionDisabled = isSubmitting || isConfirmSubmitting;

  const openCreateModal = () => {
    setModalMode('create');
    setSelectedVehicle(null);
    setFormData(emptyVehicleForm);
    setFormErrors({});
    setIsModalOpen(true);
  };

  const openEditModal = (vehicle: Vehicle) => {
    setModalMode('edit');
    setSelectedVehicle(vehicle);
    setFormData(vehicleToFormData(vehicle));
    setFormErrors({});
    setIsModalOpen(true);
  };

  const closeModal = () => {
    if (isSubmitting) {
      return;
    }

    setIsModalOpen(false);
    setSelectedVehicle(null);
    setFormErrors({});
  };

  const handleSubmit = async (payload: ReturnType<typeof formDataToPayload>) => {
    setIsSubmitting(true);
    setFormErrors({});

    try {
      if (modalMode === 'create') {
        await vehiclesService.createVehicle(payload);
        toast.success('Vehicle created', 'The vehicle record was added successfully.');
      } else if (selectedVehicle) {
        await vehiclesService.updateVehicle(selectedVehicle.id, payload);
        toast.success('Vehicle updated', 'The vehicle record was updated successfully.');
      }

      setIsModalOpen(false);
      setSelectedVehicle(null);
      refresh();
    } catch (err) {
      if (err instanceof Error && 'validationErrors' in err) {
        setFormErrors((err as any).validationErrors || {});
        toast.error('Request failed', err.message);
      } else {
        toast.error('Request failed', err instanceof Error ? err.message : 'Unable to save vehicle.');
      }
    } finally {
      setIsSubmitting(false);
    }
  };

  const handleView = async (vehicle: Vehicle) => {
    setIsDrawerOpen(true);
    setIsDrawerLoading(true);
    setDrawerVehicle(vehicle);

    try {
      const details = await vehiclesService.getVehicleById(vehicle.id);
      setDrawerVehicle(details);
    } catch (err) {
      toast.error('Details unavailable', err instanceof Error ? err.message : 'Unable to load vehicle details.');
    } finally {
      setIsDrawerLoading(false);
    }
  };

  const requestDelete = (vehicle: Vehicle) => {
    setConfirmMode('delete');
    setConfirmVehicle(vehicle);
  };

  const requestRestore = (vehicle: Vehicle) => {
    setConfirmMode('restore');
    setConfirmVehicle(vehicle);
  };

  const closeConfirmDialog = () => {
    if (isConfirmSubmitting) {
      return;
    }

    setConfirmVehicle(null);
  };

  const handleConfirm = async () => {
    if (!confirmVehicle) {
      return;
    }

    setIsConfirmSubmitting(true);
    try {
      if (confirmMode === 'delete') {
        await vehiclesService.deleteVehicle(confirmVehicle.id);
        toast.warning('Vehicle deleted', 'This vehicle was soft deleted.');
      } else {
        await vehiclesService.restoreVehicle(confirmVehicle.id);
        toast.success('Vehicle restored', 'The vehicle record was restored.');
      }

      setConfirmVehicle(null);
      refresh();
    } catch (err) {
      toast.error('Request failed', err instanceof Error ? err.message : 'Unable to complete this action.');
    } finally {
      setIsConfirmSubmitting(false);
    }
  };

  const handleToggleStatus = async (vehicle: Vehicle, nextValue: boolean) => {
    setStatusLoadingIds((current) => new Set(current).add(vehicle.id));
    replaceVehicle(vehicle.id, { is_active: nextValue });

    try {
      await vehiclesService.toggleVehicleStatus(vehicle.id, nextValue);
      toast.info(nextValue ? 'Vehicle activated' : 'Vehicle deactivated');
    } catch (err) {
      replaceVehicle(vehicle.id, { is_active: vehicle.is_active });
      toast.error('Status rolled back', err instanceof Error ? err.message : 'Unable to update vehicle status.');
    } finally {
      setStatusLoadingIds((current) => {
        const next = new Set(current);
        next.delete(vehicle.id);
        return next;
      });
    }
  };

  const handleSort = (field: VehicleSortField) => {
    const direction = sortField === field && sortDirection === 'asc' ? 'desc' : 'asc';
    setSortField(field);
    setSortDirection(direction);
    updateParams({ sort_by: field, sort_direction: direction, page: 1 });
  };

  const handleResetFilters = () => {
    setSearchQuery('');
    setVehicleTypeFilter('');
    setFuelTypeFilter('');
    setStatusFilter('');
    setManufacturerFilter('');
    setActiveFilter('all');

    updateParams({
      search: undefined,
      vehicle_type_id: undefined,
      fuel_type_id: undefined,
      status_id: undefined,
      manufacturer: undefined,
      is_active: undefined,
      page: 1,
      sort_by: sortField,
      sort_direction: sortDirection,
    });
  };

  return (
    <div className="flex w-full flex-col gap-6 font-sans text-left select-none">
      <div className="flex flex-col gap-1.5">
        <div className="flex items-center gap-2">
          <Truck className="h-5 w-5 text-cyan-400" />
          <h1 className="text-2xl font-bold tracking-tight text-white">Vehicle Registry</h1>
        </div>
        <p className="text-xs font-normal text-slate-500">
          Real vehicle records from the Laravel backend source of truth.
        </p>
      </div>

      <div className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div className="grid grid-cols-1 gap-4 lg:grid-cols-3 lg:w-full">
          <div className="rounded-2xl border border-white/5 bg-[#0E1320] p-4 shadow-md">
            <p className="text-[10px] font-bold uppercase tracking-widest text-slate-500">Total Results</p>
            <p className="mt-2 font-mono text-2xl font-bold text-white">{pagination.total}</p>
          </div>
          <div className="rounded-2xl border border-white/5 bg-[#0E1320] p-4 shadow-md">
            <p className="text-[10px] font-bold uppercase tracking-widest text-slate-500">Active View</p>
            <p className="mt-2 font-mono text-2xl font-bold text-white">{activeFilter === 'all' ? 'All' : activeFilter}</p>
          </div>
        </div>

        <button
          type="button"
          onClick={openCreateModal}
          className="inline-flex items-center justify-center gap-2 rounded-xl bg-[#0250C5] px-4 py-2.5 text-sm font-bold text-white shadow-md transition-colors hover:bg-[#0244a5]"
        >
          <Plus className="h-4 w-4" />
          Create Vehicle
        </button>
      </div>

      <FiltersBar
        searchQuery={searchQuery}
        vehicleTypeFilter={vehicleTypeFilter}
        fuelTypeFilter={fuelTypeFilter}
        statusFilter={statusFilter}
        manufacturerFilter={manufacturerFilter}
        activeFilter={activeFilter}
        vehicleTypeOptions={vehicleTypeOptions}
        fuelTypeOptions={fuelTypeOptions}
        statusOptions={vehicleStatusOptions}
        onSearchChange={setSearchQuery}
        onVehicleTypeChange={setVehicleTypeFilter}
        onFuelTypeChange={setFuelTypeFilter}
        onStatusChange={setStatusFilter}
        onManufacturerChange={setManufacturerFilter}
        onActiveChange={setActiveFilter}
        onReset={handleResetFilters}
      />

      <VehiclesTable
        vehicles={vehicles}
        isLoading={isLoading}
        error={error}
        pagination={pagination}
        sortField={sortField}
        sortDirection={sortDirection}
        hasFilters={hasFilters}
        statusLoadingIds={statusLoadingIds}
        actionDisabled={actionDisabled}
        vehicleTypeNameById={vehicleTypeNameById}
        fuelTypeNameById={fuelTypeNameById}
        statusNameById={statusNameById}
        onSort={handleSort}
        onRetry={refresh}
        onPageChange={(page) => updateParams({ page })}
        onPerPageChange={(perPage) => updateParams({ per_page: perPage, page: 1 })}
        onView={handleView}
        onEdit={openEditModal}
        onDelete={requestDelete}
        onRestore={requestRestore}
        onToggleStatus={handleToggleStatus}
      />

      <VehicleModal
        isOpen={isModalOpen}
        title={modalMode === 'create' ? 'Create Vehicle' : 'Edit Vehicle'}
        description={modalMode === 'create'
          ? 'Add a new vehicle record with fleet, registration, and operational details.'
          : 'Update the selected vehicle record.'}
        formData={formData}
        errors={formErrors}
        isSubmitting={isSubmitting}
        submitLabel={modalMode === 'create' ? 'Create Vehicle' : 'Save Changes'}
        vehicleTypeOptions={vehicleTypeOptions}
        fuelTypeOptions={fuelTypeOptions}
        statusOptions={vehicleStatusOptions}
        onChange={setFormData}
        onSubmit={handleSubmit}
        onClose={closeModal}
      />

      <VehicleDetailsDrawer
        vehicle={drawerVehicle}
        isOpen={isDrawerOpen}
        isLoading={isDrawerLoading}
        onClose={() => setIsDrawerOpen(false)}
        onPairDriver={(v) => setPairingVehicle(v)}
        vehicleTypeNameById={vehicleTypeNameById}
        fuelTypeNameById={fuelTypeNameById}
        statusNameById={statusNameById}
      />

      <PairDriverModal
        vehicle={pairingVehicle}
        isOpen={Boolean(pairingVehicle)}
        onClose={() => setPairingVehicle(null)}
        onSuccess={(updated) => {
          replaceVehicle(updated);
          if (drawerVehicle?.id === updated.id) {
            setDrawerVehicle(updated);
          }
          toast.success('Vehicle driver pairing updated successfully');
        }}
      />

      <DeleteDialog
        isOpen={Boolean(confirmVehicle)}
        title={confirmMode === 'restore' ? 'Restore vehicle?' : 'Delete vehicle?'}
        message={confirmMode === 'restore'
          ? 'This vehicle will be restored and returned to the active registry.'
          : 'This vehicle will be soft deleted and removed from the active registry.'}
        confirmLabel={confirmMode === 'restore' ? 'Restore' : 'Delete'}
        tone={confirmMode === 'restore' ? 'restore' : 'danger'}
        isSubmitting={isConfirmSubmitting}
        onCancel={closeConfirmDialog}
        onConfirm={handleConfirm}
      />
    </div>
  );
}
