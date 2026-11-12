import { useEffect, useMemo, useState } from 'react';
import { Plus, User } from 'lucide-react';
import { useToast } from '../../../../shared/components/toast';
import { driversService } from '../services/drivers.service';
import { useDrivers } from '../hooks/useDrivers';
import FiltersBar from '../components/FiltersBar';
import DriversTable from '../components/DriversTable';
import DriverModal from '../components/DriverModal';
import DriverDetailsDrawer from '../components/DriverDetailsDrawer';
import DeleteDialog from '../components/DeleteDialog';
import { emptyDriverForm, formDataToPayload, driverToFormData } from '../components/DriverForm';
import type {
  Driver,
  DriverFormData,
  DriverSortField,
  DriverValidationErrors,
} from '../types/driver.types';

interface LookupOption {
  value: string;
  label: string;
}

type ModalMode = 'create' | 'edit';
type ConfirmMode = 'delete' | 'restore';

const driverStatusOptions: LookupOption[] = [
  { value: '1', label: 'Available' },
  { value: '2', label: 'Busy' },
  { value: '3', label: 'Offline' },
  { value: '4', label: 'On Leave' },
  { value: '5', label: 'Suspended' },
];

const licenseClassOptions: LookupOption[] = [
  { value: 'B', label: 'B' },
  { value: 'C', label: 'C' },
  { value: 'C1', label: 'C1' },
  { value: 'CE', label: 'CE' },
];

const defaultParams = {
  page: 1,
  per_page: 15,
  sort_by: 'created_at' as DriverSortField,
  sort_direction: 'desc' as const,
};

function normalizeSearchValue(value: string) {
  return value.trim() || undefined;
}

export default function DriversPage() {
  const toast = useToast();
  const {
    drivers,
    isLoading,
    error,
    pagination,
    updateParams,
    refresh,
    replaceDriver,
  } = useDrivers(defaultParams);

  const [searchQuery, setSearchQuery] = useState('');
  const [nationalityFilter, setNationalityFilter] = useState('');
  const [licenseClassFilter, setLicenseClassFilter] = useState('');
  const [statusFilter, setStatusFilter] = useState('');
  const [experienceFilter, setExperienceFilter] = useState('');
  const [activeFilter, setActiveFilter] = useState<'all' | 'active' | 'inactive'>('all');
  const [sortField, setSortField] = useState<DriverSortField>('created_at');
  const [sortDirection, setSortDirection] = useState<'asc' | 'desc'>('desc');

  const [modalMode, setModalMode] = useState<ModalMode>('create');
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [selectedDriver, setSelectedDriver] = useState<Driver | null>(null);
  const [formData, setFormData] = useState<DriverFormData>(emptyDriverForm);
  const [formErrors, setFormErrors] = useState<DriverValidationErrors>({});
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [drawerDriver, setDrawerDriver] = useState<Driver | null>(null);
  const [isDrawerOpen, setIsDrawerOpen] = useState(false);
  const [isDrawerLoading, setIsDrawerLoading] = useState(false);
  const [confirmMode, setConfirmMode] = useState<ConfirmMode>('delete');
  const [confirmDriver, setConfirmDriver] = useState<Driver | null>(null);
  const [isConfirmSubmitting, setIsConfirmSubmitting] = useState(false);
  const [statusLoadingIds, setStatusLoadingIds] = useState<Set<string>>(new Set());

  useEffect(() => {
    const timeoutId = window.setTimeout(() => {
      updateParams({
        search: normalizeSearchValue(searchQuery),
        nationality: normalizeSearchValue(nationalityFilter),
        license_class: normalizeSearchValue(licenseClassFilter),
        status_id: statusFilter ? Number(statusFilter) : undefined,
        min_experience: experienceFilter ? Number(experienceFilter) : undefined,
        is_active: activeFilter === 'all' ? undefined : activeFilter === 'active',
        page: 1,
      });
    }, 400);

    return () => window.clearTimeout(timeoutId);
  }, [
    activeFilter,
    nationalityFilter,
    searchQuery,
    licenseClassFilter,
    statusFilter,
    experienceFilter,
    updateParams,
  ]);

  const statusNameById = useMemo(() => Object.fromEntries(
    driverStatusOptions.map((option) => [Number(option.value), option.label]),
  ) as Record<number, string>, []);

  const hasFilters = Boolean(
    searchQuery
    || nationalityFilter
    || licenseClassFilter
    || statusFilter
    || experienceFilter
    || activeFilter !== 'all'
  );

  const actionDisabled = isSubmitting || isConfirmSubmitting;

  const openCreateModal = () => {
    setModalMode('create');
    setSelectedDriver(null);
    setFormData(emptyDriverForm);
    setFormErrors({});
    setIsModalOpen(true);
  };

  const openEditModal = (driver: Driver) => {
    setModalMode('edit');
    setSelectedDriver(driver);
    setFormData(driverToFormData(driver));
    setFormErrors({});
    setIsModalOpen(true);
  };

  const closeModal = () => {
    if (isSubmitting) {
      return;
    }

    setIsModalOpen(false);
    setSelectedDriver(null);
    setFormErrors({});
  };

  const handleSubmit = async (payload: ReturnType<typeof formDataToPayload>) => {
    setIsSubmitting(true);
    setFormErrors({});

    try {
      if (modalMode === 'create') {
        await driversService.createDriver(payload);
        toast.success('Driver created', 'The driver record was added successfully.');
      } else if (selectedDriver) {
        await driversService.updateDriver(selectedDriver.id, payload);
        toast.success('Driver updated', 'The driver record was updated successfully.');
      }

      setIsModalOpen(false);
      setSelectedDriver(null);
      refresh();
    } catch (err) {
      if (err instanceof Error && 'validationErrors' in err) {
        setFormErrors((err as any).validationErrors || {});
        toast.error('Request failed', err.message);
      } else {
        toast.error('Request failed', err instanceof Error ? err.message : 'Unable to save driver.');
      }
    } finally {
      setIsSubmitting(false);
    }
  };

  const handleView = async (driver: Driver) => {
    setIsDrawerOpen(true);
    setIsDrawerLoading(true);
    setDrawerDriver(driver);

    try {
      const details = await driversService.getDriverById(driver.id);
      setDrawerDriver(details);
    } catch (err) {
      toast.error('Details unavailable', err instanceof Error ? err.message : 'Unable to load driver details.');
    } finally {
      setIsDrawerLoading(false);
    }
  };

  const requestDelete = (driver: Driver) => {
    setConfirmMode('delete');
    setConfirmDriver(driver);
  };

  const requestRestore = (driver: Driver) => {
    setConfirmMode('restore');
    setConfirmDriver(driver);
  };

  const closeConfirmDialog = () => {
    if (isConfirmSubmitting) {
      return;
    }

    setConfirmDriver(null);
  };

  const handleConfirm = async () => {
    if (!confirmDriver) {
      return;
    }

    setIsConfirmSubmitting(true);
    try {
      if (confirmMode === 'delete') {
        await driversService.deleteDriver(confirmDriver.id);
        toast.warning('Driver deleted', 'This driver was soft deleted.');
      } else {
        await driversService.restoreDriver(confirmDriver.id);
        toast.success('Driver restored', 'The driver record was restored.');
      }

      setConfirmDriver(null);
      refresh();
    } catch (err) {
      toast.error('Request failed', err instanceof Error ? err.message : 'Unable to complete this action.');
    } finally {
      setIsConfirmSubmitting(false);
    }
  };

  const handleToggleStatus = async (driver: Driver, nextValue: boolean) => {
    setStatusLoadingIds((current) => new Set(current).add(driver.id));
    replaceDriver(driver.id, { is_active: nextValue });

    try {
      await driversService.toggleDriverStatus(driver.id, nextValue);
      toast.info(nextValue ? 'Driver activated' : 'Driver deactivated');
    } catch (err) {
      replaceDriver(driver.id, { is_active: driver.is_active });
      toast.error('Status rolled back', err instanceof Error ? err.message : 'Unable to update driver status.');
    } finally {
      setStatusLoadingIds((current) => {
        const next = new Set(current);
        next.delete(driver.id);
        return next;
      });
    }
  };

  const handleSort = (field: DriverSortField) => {
    const direction = sortField === field && sortDirection === 'asc' ? 'desc' : 'asc';

    setSortField(field);
    setSortDirection(direction);
    updateParams({ sort_by: field, sort_direction: direction, page: 1 });
  };

  const handleResetFilters = () => {
    setSearchQuery('');
    setNationalityFilter('');
    setLicenseClassFilter('');
    setStatusFilter('');
    setExperienceFilter('');
    setActiveFilter('all');

    updateParams({
      search: undefined,
      nationality: undefined,
      license_class: undefined,
      status_id: undefined,
      min_experience: undefined,
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
          <User className="h-5 w-5 text-cyan-400" />
          <h1 className="text-2xl font-bold tracking-tight text-white">Driver Registry</h1>
        </div>
        <p className="text-xs font-normal text-slate-500">
          FleetX driver employee records and operational status.
        </p>
      </div>

      <div className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div className="grid grid-cols-1 gap-4 lg:grid-cols-3 lg:w-full">
          <div className="rounded-2xl border border-white/5 bg-[#0E1320] p-4 shadow-md">
            <p className="text-[10px] font-bold uppercase tracking-widest text-slate-500">Total Drivers</p>
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
          Create Driver
        </button>
      </div>

      <FiltersBar
        searchQuery={searchQuery}
        nationalityFilter={nationalityFilter}
        licenseClassFilter={licenseClassFilter}
        statusFilter={statusFilter}
        experienceFilter={experienceFilter}
        activeFilter={activeFilter}
        licenseClassOptions={licenseClassOptions}
        statusOptions={driverStatusOptions}
        onSearchChange={setSearchQuery}
        onNationalityChange={setNationalityFilter}
        onLicenseClassChange={setLicenseClassFilter}
        onStatusChange={setStatusFilter}
        onExperienceChange={setExperienceFilter}
        onActiveChange={setActiveFilter}
        onReset={handleResetFilters}
      />

      <DriversTable
        drivers={drivers}
        isLoading={isLoading}
        error={error}
        pagination={pagination}
        sortField={sortField}
        sortDirection={sortDirection}
        hasFilters={hasFilters}
        statusNameById={statusNameById}
        statusLoadingIds={statusLoadingIds}
        actionDisabled={actionDisabled}
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

      <DriverModal
        isOpen={isModalOpen}
        title={modalMode === 'create' ? 'Create Driver' : 'Edit Driver'}
        description={modalMode === 'create'
          ? 'Add a new driver record with employee and license details.'
          : 'Update the selected driver record.'}
        formData={formData}
        errors={formErrors}
        isSubmitting={isSubmitting}
        submitLabel={modalMode === 'create' ? 'Create Driver' : 'Save Changes'}
        statusOptions={driverStatusOptions}
        onChange={setFormData}
        onSubmit={handleSubmit}
        onClose={closeModal}
      />

      <DriverDetailsDrawer
        driver={drawerDriver}
        isOpen={isDrawerOpen}
        isLoading={isDrawerLoading}
        onClose={() => setIsDrawerOpen(false)}
        statusNameById={statusNameById}
      />

      <DeleteDialog
        isOpen={Boolean(confirmDriver)}
        title={confirmMode === 'restore' ? 'Restore driver?' : 'Delete driver?'}
        message={confirmMode === 'restore'
          ? 'This driver will be restored and returned to the active registry.'
          : 'This driver will be soft deleted and removed from the active registry.'}
        confirmLabel={confirmMode === 'restore' ? 'Restore' : 'Delete'}
        tone={confirmMode === 'restore' ? 'restore' : 'danger'}
        isSubmitting={isConfirmSubmitting}
        onCancel={closeConfirmDialog}
        onConfirm={handleConfirm}
      />
    </div>
  );
}
