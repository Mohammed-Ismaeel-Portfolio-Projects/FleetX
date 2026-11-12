import { useEffect, useMemo, useState } from 'react';
import { Plus, Truck } from 'lucide-react';
import { useToast } from '../../../../shared/components/toast';
import { trailersService } from '../services/trailers.service';
import { useTrailers } from '../hooks/useTrailers';
import FiltersBar from '../components/FiltersBar';
import TrailersTable from '../components/TrailersTable';
import TrailerModal from '../components/TrailerModal';
import TrailerDetailsDrawer from '../components/TrailerDetailsDrawer';
import DeleteDialog from '../components/DeleteDialog';
import { emptyTrailerForm, formDataToPayload, trailerToFormData } from '../components/TrailerForm';
import type {
  Trailer,
  TrailerFormData,
  TrailerSortField,
  TrailerValidationErrors,
} from '../types/trailer.types';

interface LookupOption {
  value: string;
  label: string;
}

type ModalMode = 'create' | 'edit';
type ConfirmMode = 'delete' | 'restore';

const trailerTypeOptions: LookupOption[] = [
  { value: 'Dry Van', label: 'Dry Van' },
  { value: 'Refrigerated Trailer', label: 'Refrigerated Trailer' },
  { value: 'Flatbed Trailer', label: 'Flatbed Trailer' },
  { value: 'Lowboy', label: 'Lowboy' },
  { value: 'Container Chassis', label: 'Container Chassis' },
  { value: 'Tanker Trailer', label: 'Tanker Trailer' },
  { value: 'Curtainsider', label: 'Curtainsider' },
  { value: 'Walking Floor', label: 'Walking Floor' },
  { value: 'Tipper Trailer', label: 'Tipper Trailer' },
  { value: 'Car Carrier', label: 'Car Carrier' },
];

const trailerStatusOptions: LookupOption[] = [
  { value: '1', label: 'Available' },
  { value: '2', label: 'In Use' },
  { value: '3', label: 'Maintenance' },
  { value: '4', label: 'Inactive' },
  { value: '5', label: 'Retired' },
];

const defaultParams = {
  page: 1,
  per_page: 15,
  sort_by: 'created_at' as TrailerSortField,
  sort_direction: 'desc' as const,
};

function normalizeSearchValue(value: string) {
  return value.trim() || undefined;
}

export default function TrailersPage() {
  const toast = useToast();
  const {
    trailers,
    isLoading,
    error,
    pagination,
    updateParams,
    refresh,
    replaceTrailer,
  } = useTrailers(defaultParams);

  const [searchQuery, setSearchQuery] = useState('');
  const [trailerTypeFilter, setTrailerTypeFilter] = useState('');
  const [statusFilter, setStatusFilter] = useState('');
  const [manufacturerFilter, setManufacturerFilter] = useState('');
  const [activeFilter, setActiveFilter] = useState<'all' | 'active' | 'inactive'>('all');
  const [sortField, setSortField] = useState<TrailerSortField>('created_at');
  const [sortDirection, setSortDirection] = useState<'asc' | 'desc'>('desc');

  const [modalMode, setModalMode] = useState<ModalMode>('create');
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [selectedTrailer, setSelectedTrailer] = useState<Trailer | null>(null);
  const [formData, setFormData] = useState<TrailerFormData>(emptyTrailerForm);
  const [formErrors, setFormErrors] = useState<TrailerValidationErrors>({});
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [drawerTrailer, setDrawerTrailer] = useState<Trailer | null>(null);
  const [isDrawerOpen, setIsDrawerOpen] = useState(false);
  const [isDrawerLoading, setIsDrawerLoading] = useState(false);
  const [confirmMode, setConfirmMode] = useState<ConfirmMode>('delete');
  const [confirmTrailer, setConfirmTrailer] = useState<Trailer | null>(null);
  const [isConfirmSubmitting, setIsConfirmSubmitting] = useState(false);
  const [statusLoadingIds, setStatusLoadingIds] = useState<Set<string>>(new Set());

  useEffect(() => {
    const timeoutId = window.setTimeout(() => {
      updateParams({
        search: normalizeSearchValue(searchQuery),
        trailer_type: normalizeSearchValue(trailerTypeFilter),
        status_id: statusFilter ? Number(statusFilter) : undefined,
        manufacturer: normalizeSearchValue(manufacturerFilter),
        is_active: activeFilter === 'all' ? undefined : activeFilter === 'active',
        page: 1,
      });
    }, 400);

    return () => window.clearTimeout(timeoutId);
  }, [
    activeFilter,
    manufacturerFilter,
    searchQuery,
    statusFilter,
    trailerTypeFilter,
    updateParams,
  ]);

  const statusNameById = useMemo(() => Object.fromEntries(
    trailerStatusOptions.map((option) => [Number(option.value), option.label]),
  ) as Record<number, string>, []);

  const hasFilters = Boolean(
    searchQuery
    || trailerTypeFilter
    || statusFilter
    || manufacturerFilter
    || activeFilter !== 'all'
  );

  const actionDisabled = isSubmitting || isConfirmSubmitting;

  const openCreateModal = () => {
    setModalMode('create');
    setSelectedTrailer(null);
    setFormData(emptyTrailerForm);
    setFormErrors({});
    setIsModalOpen(true);
  };

  const openEditModal = (trailer: Trailer) => {
    setModalMode('edit');
    setSelectedTrailer(trailer);
    setFormData(trailerToFormData(trailer));
    setFormErrors({});
    setIsModalOpen(true);
  };

  const closeModal = () => {
    if (isSubmitting) {
      return;
    }

    setIsModalOpen(false);
    setSelectedTrailer(null);
    setFormErrors({});
  };

  const handleSubmit = async (payload: ReturnType<typeof formDataToPayload>) => {
    setIsSubmitting(true);
    setFormErrors({});

    try {
      if (modalMode === 'create') {
        await trailersService.createTrailer(payload);
        toast.success('Trailer created', 'The trailer record was added successfully.');
      } else if (selectedTrailer) {
        await trailersService.updateTrailer(selectedTrailer.id, payload);
        toast.success('Trailer updated', 'The trailer record was updated successfully.');
      }

      setIsModalOpen(false);
      setSelectedTrailer(null);
      refresh();
    } catch (err) {
      if (err instanceof Error && 'validationErrors' in err) {
        setFormErrors((err as any).validationErrors || {});
        toast.error('Request failed', err.message);
      } else {
        toast.error('Request failed', err instanceof Error ? err.message : 'Unable to save trailer.');
      }
    } finally {
      setIsSubmitting(false);
    }
  };

  const handleView = async (trailer: Trailer) => {
    setIsDrawerOpen(true);
    setIsDrawerLoading(true);
    setDrawerTrailer(trailer);

    try {
      const details = await trailersService.getTrailerById(trailer.id);
      setDrawerTrailer(details);
    } catch (err) {
      toast.error('Details unavailable', err instanceof Error ? err.message : 'Unable to load trailer details.');
    } finally {
      setIsDrawerLoading(false);
    }
  };

  const requestDelete = (trailer: Trailer) => {
    setConfirmMode('delete');
    setConfirmTrailer(trailer);
  };

  const requestRestore = (trailer: Trailer) => {
    setConfirmMode('restore');
    setConfirmTrailer(trailer);
  };

  const closeConfirmDialog = () => {
    if (isConfirmSubmitting) {
      return;
    }

    setConfirmTrailer(null);
  };

  const handleConfirm = async () => {
    if (!confirmTrailer) {
      return;
    }

    setIsConfirmSubmitting(true);
    try {
      if (confirmMode === 'delete') {
        await trailersService.deleteTrailer(confirmTrailer.id);
        toast.warning('Trailer deleted', 'This trailer was soft deleted.');
      } else {
        await trailersService.restoreTrailer(confirmTrailer.id);
        toast.success('Trailer restored', 'The trailer record was restored.');
      }

      setConfirmTrailer(null);
      refresh();
    } catch (err) {
      toast.error('Request failed', err instanceof Error ? err.message : 'Unable to complete this action.');
    } finally {
      setIsConfirmSubmitting(false);
    }
  };

  const handleToggleStatus = async (trailer: Trailer, nextValue: boolean) => {
    setStatusLoadingIds((current) => new Set(current).add(trailer.id));
    replaceTrailer(trailer.id, { is_active: nextValue });

    try {
      await trailersService.toggleTrailerStatus(trailer.id, nextValue);
      toast.info(nextValue ? 'Trailer activated' : 'Trailer deactivated');
    } catch (err) {
      replaceTrailer(trailer.id, { is_active: trailer.is_active });
      toast.error('Status rolled back', err instanceof Error ? err.message : 'Unable to update trailer status.');
    } finally {
      setStatusLoadingIds((current) => {
        const next = new Set(current);
        next.delete(trailer.id);
        return next;
      });
    }
  };

  const handleSort = (field: TrailerSortField) => {
    const direction = sortField === field && sortDirection === 'asc' ? 'desc' : 'asc';

    setSortField(field);
    setSortDirection(direction);
    updateParams({ sort_by: field, sort_direction: direction, page: 1 });
  };

  const handleResetFilters = () => {
    setSearchQuery('');
    setTrailerTypeFilter('');
    setStatusFilter('');
    setManufacturerFilter('');
    setActiveFilter('all');

    updateParams({
      search: undefined,
      trailer_type: undefined,
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
          <h1 className="text-2xl font-bold tracking-tight text-white">Trailer Registry</h1>
        </div>
        <p className="text-xs font-normal text-slate-500">
          Real trailer records from the Laravel backend source of truth.
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
          className="inline-flex items-center justify-center gap-2 rounded-xl bg-secondary px-4 py-2.5 text-sm font-bold text-white shadow-md transition-colors hover:bg-secondary/80"
        >
          <Plus className="h-4 w-4" />
          Create Trailer
        </button>
      </div>

      <FiltersBar
        searchQuery={searchQuery}
        trailerTypeFilter={trailerTypeFilter}
        statusFilter={statusFilter}
        manufacturerFilter={manufacturerFilter}
        activeFilter={activeFilter}
        trailerTypeOptions={trailerTypeOptions}
        statusOptions={trailerStatusOptions}
        onSearchChange={setSearchQuery}
        onTrailerTypeChange={setTrailerTypeFilter}
        onStatusChange={setStatusFilter}
        onManufacturerChange={setManufacturerFilter}
        onActiveChange={setActiveFilter}
        onReset={handleResetFilters}
      />

      <TrailersTable
        trailers={trailers}
        isLoading={isLoading}
        error={error}
        pagination={pagination}
        sortField={sortField}
        sortDirection={sortDirection}
        hasFilters={hasFilters}
        statusLoadingIds={statusLoadingIds}
        actionDisabled={actionDisabled}
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

      <TrailerModal
        isOpen={isModalOpen}
        title={modalMode === 'create' ? 'Create Trailer' : 'Edit Trailer'}
        description={modalMode === 'create'
          ? 'Add a new trailer record with fleet, registration, and operational details.'
          : 'Update the selected trailer record.'}
        formData={formData}
        errors={formErrors}
        isSubmitting={isSubmitting}
        submitLabel={modalMode === 'create' ? 'Create Trailer' : 'Save Changes'}
        trailerTypeOptions={trailerTypeOptions}
        statusOptions={trailerStatusOptions}
        onChange={setFormData}
        onSubmit={handleSubmit}
        onClose={closeModal}
      />

      <TrailerDetailsDrawer
        trailer={drawerTrailer}
        isOpen={isDrawerOpen}
        isLoading={isDrawerLoading}
        onClose={() => setIsDrawerOpen(false)}
        statusNameById={statusNameById}
      />

      <DeleteDialog
        isOpen={Boolean(confirmTrailer)}
        title={confirmMode === 'restore' ? 'Restore trailer?' : 'Delete trailer?'}
        message={confirmMode === 'restore'
          ? 'This trailer will be restored and returned to the active registry.'
          : 'This trailer will be soft deleted and removed from the active registry.'}
        confirmLabel={confirmMode === 'restore' ? 'Restore' : 'Delete'}
        isSubmitting={isConfirmSubmitting}
        onCancel={closeConfirmDialog}
        onConfirm={handleConfirm}
      />
    </div>
  );
}
