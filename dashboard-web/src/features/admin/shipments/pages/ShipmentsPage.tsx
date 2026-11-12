import { useEffect, useMemo, useState } from 'react';
import { Package } from 'lucide-react';
import { useToast } from '../../../../shared/components/toast';
import { useShipments } from '../hooks/useShipments';
import { shipmentsService } from '../services/shipments.service';
import ShipmentsTable from '../components/ShipmentsTable';
import ShipmentDetailsDrawer from '../components/ShipmentDetailsDrawer';
import type { Shipment, ShipmentSortField } from '../types/shipment.types';

const shipmentStatusOptions = [
  { value: '1', label: 'Draft' },
  { value: '2', label: 'Pending Review' },
  { value: '3', label: 'Approved' },
  { value: '4', label: 'Waiting Assignment' },
  { value: '5', label: 'Assigned' },
  { value: '6', label: 'Ready For Pickup' },
  { value: '7', label: 'Picked Up' },
  { value: '8', label: 'In Transit' },
  { value: '9', label: 'At Customs' },
  { value: '10', label: 'Out For Delivery' },
  { value: '11', label: 'Delivered' },
  { value: '12', label: 'Completed' },
  { value: '13', label: 'Cancelled' },
];

const defaultParams = {
  page: 1,
  per_page: 15,
  sort_by: 'created_at' as ShipmentSortField,
  sort_direction: 'desc' as const,
};

function normalizeSearchValue(value: string) {
  return value.trim() || undefined;
}

export default function ShipmentsPage() {
  const toast = useToast();
  const {
    shipments,
    isLoading,
    error,
    pagination,
    updateParams,
    refresh,
  } = useShipments(defaultParams);

  const [searchQuery, setSearchQuery] = useState('');
  const [customerFilter, setCustomerFilter] = useState('');
  const [statusFilter, setStatusFilter] = useState('');
  const [priorityFilter, setPriorityFilter] = useState('');
  const [countryFilter, setCountryFilter] = useState('');
  const [sortField, setSortField] = useState<ShipmentSortField>('created_at');
  const [sortDirection, setSortDirection] = useState<'asc' | 'desc'>('desc');
  const [drawerShipment, setDrawerShipment] = useState<Shipment | null>(null);
  const [isDrawerOpen, setIsDrawerOpen] = useState(false);
  const [isDrawerLoading, setIsDrawerLoading] = useState(false);

  useEffect(() => {
    const timeoutId = window.setTimeout(() => {
      updateParams({
        search: normalizeSearchValue(searchQuery),
        customer_company_id: customerFilter ? customerFilter : undefined,
        status_id: statusFilter ? Number(statusFilter) : undefined,
        priority: priorityFilter ? (priorityFilter as Shipment['priority']) : undefined,
        country: countryFilter ? countryFilter : undefined,
        page: 1,
      });
    }, 400);

    return () => window.clearTimeout(timeoutId);
  }, [customerFilter, countryFilter, priorityFilter, searchQuery, statusFilter, updateParams]);

  const statusLabelById = useMemo(
    () => Object.fromEntries(shipmentStatusOptions.map((option) => [option.value, option.label])),
    [],
  );

  const hasFilters = Boolean(
    searchQuery
    || customerFilter
    || statusFilter
    || priorityFilter
    || countryFilter
  );

  const handleView = async (shipment: Shipment) => {
    setIsDrawerOpen(true);
    setIsDrawerLoading(true);
    setDrawerShipment(shipment);

    try {
      const details = await shipmentsService.getShipment(shipment.id);
      setDrawerShipment(details);
    } catch (err) {
      toast.error('Details unavailable', err instanceof Error ? err.message : 'Unable to load shipment details.');
    } finally {
      setIsDrawerLoading(false);
    }
  };

  const handleSort = (field: ShipmentSortField) => {
    const direction = sortField === field && sortDirection === 'asc' ? 'desc' : 'asc';
    setSortField(field);
    setSortDirection(direction);
    updateParams({ sort_by: field, sort_direction: direction, page: 1 });
  };

  const handleResetFilters = () => {
    setSearchQuery('');
    setCustomerFilter('');
    setStatusFilter('');
    setPriorityFilter('');
    setCountryFilter('');

    updateParams({
      search: undefined,
      customer_company_id: undefined,
      status_id: undefined,
      priority: undefined,
      country: undefined,
      page: 1,
      sort_by: sortField,
      sort_direction: sortDirection,
    });
  };

  return (
    <div className="flex w-full flex-col gap-6 font-sans text-left select-none">
      <div className="flex flex-col gap-1.5">
        <div className="flex items-center gap-2">
          <Package className="h-5 w-5 text-cyan-400" />
          <h1 className="text-2xl font-bold tracking-tight text-white">Fleet Shipments</h1>
        </div>
        <p className="text-xs font-normal text-slate-500">
          Read-only shipment oversight across the platform.
        </p>
      </div>

      <div className="grid grid-cols-1 gap-4 lg:grid-cols-3">
        <div className="rounded-2xl border border-white/5 bg-[#0E1320] p-4 shadow-md">
          <p className="text-[10px] font-bold uppercase tracking-widest text-slate-500">Total Shipments</p>
          <p className="mt-2 font-mono text-2xl font-bold text-white">{pagination.total}</p>
        </div>
        <div className="rounded-2xl border border-white/5 bg-[#0E1320] p-4 shadow-md">
          <p className="text-[10px] font-bold uppercase tracking-widest text-slate-500">Status Filter</p>
          <p className="mt-2 font-mono text-2xl font-bold text-white">
            {statusFilter ? statusLabelById[statusFilter] || statusFilter : 'All'}
          </p>
        </div>
      </div>

      <div className="rounded-2xl border border-white/5 bg-[#0E1320] p-6 shadow-md">
        <div className="mb-4 flex items-center justify-between">
          <h2 className="text-lg font-semibold text-white">Shipments</h2>
          {hasFilters && (
            <button
              type="button"
              onClick={handleResetFilters}
              className="text-xs text-cyan-400 hover:text-cyan-300"
            >
              Reset Filters
            </button>
          )}
        </div>

        <ShipmentsTable
          shipments={shipments}
          isLoading={isLoading}
          error={error}
          pagination={pagination}
          sortField={sortField}
          sortDirection={sortDirection}
          hasFilters={hasFilters}
          onSort={handleSort}
          onRetry={refresh}
          onPageChange={(page) => updateParams({ page })}
          onPerPageChange={(perPage) => updateParams({ per_page: perPage, page: 1 })}
          onView={handleView}
        />
      </div>

      <ShipmentDetailsDrawer
        shipment={drawerShipment}
        isOpen={isDrawerOpen}
        isLoading={isDrawerLoading}
        onClose={() => setIsDrawerOpen(false)}
      />
    </div>
  );
}
