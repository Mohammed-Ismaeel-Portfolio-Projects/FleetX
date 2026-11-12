import { useCallback, useEffect, useRef, useState } from 'react';
import { useShipments } from '../hooks/useShipments';
import { shipmentsService } from '../services/shipments.service';
import type { Shipment } from '../types/shipment.types';
import { DispatcherSurface } from '../../../../shared/components/dispatcher/DispatcherSurface';
import { PageHeader } from '../../../../shared/components/dispatcher/PageHeader';
import { FilterToolbar } from '../../../../shared/components/dispatcher/FilterToolbar';
import { LoadingState } from '../../../../shared/components/dispatcher/LoadingState';
import { EmptyState } from '../../../../shared/components/dispatcher/EmptyState';
import { useAuth } from '../../../../core/auth';
import ShipmentKPIs from '../components/ShipmentKPIs';
import ShipmentOperationsTable from '../components/ShipmentOperationsTable';
import ShipmentDetailsDrawer from '../components/ShipmentDetailsDrawer';

const POLL_INTERVAL_MS = 15_000;

export default function ShipmentsPage() {
  const { user } = useAuth();
  const {
    shipments,
    isLoading,
    pagination,
    loadShipments,
  } = useShipments();

  const [selectedShipment, setSelectedShipment] = useState<Shipment | null>(null);
  const [isDrawerOpen, setIsDrawerOpen] = useState(false);
  const [refreshKey, setRefreshKey] = useState(0);

  const [searchQuery, setSearchQuery] = useState('');
  const [statusFilter, setStatusFilter] = useState('');
  const [priorityFilter, setPriorityFilter] = useState('');
  const [customerFilter, setCustomerFilter] = useState('');
  const [regionFilter, setRegionFilter] = useState('');

  const selectedShipmentIdRef = useRef<string | null>(null);
  const isDrawerOpenRef = useRef(false);
  const drawerInFlightRef = useRef(false);
  const filterParamsRef = useRef({
    search: '',
    status: '',
    priority: '',
    customer: '',
    region: '',
    page: 1,
    per_page: 15,
  });

  useEffect(() => {
    selectedShipmentIdRef.current = selectedShipment?.id ?? null;
  }, [selectedShipment?.id]);

  useEffect(() => {
    isDrawerOpenRef.current = isDrawerOpen;
  }, [isDrawerOpen]);

  useEffect(() => {
    filterParamsRef.current = {
      search: searchQuery,
      status: statusFilter,
      priority: priorityFilter,
      customer: customerFilter,
      region: regionFilter,
      page: pagination.current_page,
      per_page: pagination.per_page,
    };
  }, [searchQuery, statusFilter, priorityFilter, customerFilter, regionFilter, pagination.current_page, pagination.per_page]);

  const buildListParams = useCallback(() => {
    const f = filterParamsRef.current;
    const params: Record<string, string | number> = {
      page: f.page,
      per_page: f.per_page,
    };
    if (f.search) params.search = f.search;
    if (f.status && f.status !== 'All') params.status = f.status;
    if (f.priority && f.priority !== 'All') params.priority = f.priority;
    if (f.customer && f.customer !== 'All') params.customer_company_id = f.customer;
    if (f.region && f.region !== 'All') params.country = f.region;
    return params;
  }, []);

  const refreshDrawer = useCallback(async (shipmentId: string) => {
    if (drawerInFlightRef.current) return;
    drawerInFlightRef.current = true;
    try {
      const fresh = await shipmentsService.getShipmentById(shipmentId);
      if (!isDrawerOpenRef.current || selectedShipmentIdRef.current !== shipmentId) {
        return;
      }
      if (fresh) {
        setSelectedShipment(fresh);
      } else {
        setSelectedShipment(null);
        setIsDrawerOpen(false);
      }
    } catch (error) {
      console.error('Error refreshing shipment drawer:', error);
    } finally {
      drawerInFlightRef.current = false;
    }
  }, []);

  const triggerRefresh = useCallback(async () => {
    setRefreshKey((key) => key + 1);
    await loadShipments(buildListParams(), { silent: true });
    const drawerId = selectedShipmentIdRef.current;
    if (isDrawerOpenRef.current && drawerId) {
      await refreshDrawer(drawerId);
    }
  }, [buildListParams, loadShipments, refreshDrawer]);

  // Filter-driven load (page changes go through handlePageChange)
  useEffect(() => {
    void loadShipments({
      page: 1,
      per_page: pagination.per_page,
      search: searchQuery || undefined,
      status: statusFilter && statusFilter !== 'All' ? statusFilter : undefined,
      priority: priorityFilter && priorityFilter !== 'All' ? priorityFilter : undefined,
      customer_company_id: customerFilter && customerFilter !== 'All' ? customerFilter : undefined,
      country: regionFilter && regionFilter !== 'All' ? regionFilter : undefined,
    });
    // eslint-disable-next-line react-hooks/exhaustive-deps -- intentional: filter changes only
  }, [searchQuery, statusFilter, priorityFilter, customerFilter, regionFilter, loadShipments]);

  // Visibility-aware 15s polling (same model as Fleet / Dispatcher Dashboard)
  useEffect(() => {
    let intervalId: number | null = null;

    const clearPolling = () => {
      if (intervalId !== null) {
        window.clearInterval(intervalId);
        intervalId = null;
      }
    };

    const startPolling = () => {
      if (intervalId !== null || document.hidden) return;
      intervalId = window.setInterval(() => {
        if (!document.hidden) {
          void triggerRefresh();
        }
      }, POLL_INTERVAL_MS);
    };

    const onVisibilityChange = () => {
      if (document.hidden) {
        clearPolling();
        return;
      }
      void triggerRefresh();
      startPolling();
    };

    startPolling();
    document.addEventListener('visibilitychange', onVisibilityChange);

    return () => {
      clearPolling();
      document.removeEventListener('visibilitychange', onVisibilityChange);
    };
  }, [triggerRefresh]);

  const handleRowClick = async (shipment: Shipment) => {
    setSelectedShipment(shipment);
    setIsDrawerOpen(true);
    selectedShipmentIdRef.current = shipment.id;
    isDrawerOpenRef.current = true;
    await refreshDrawer(shipment.id);
  };

  const handleResetFilters = () => {
    setSearchQuery('');
    setStatusFilter('');
    setPriorityFilter('');
    setCustomerFilter('');
    setRegionFilter('');
  };

  const handlePageChange = (newPage: number) => {
    void loadShipments({
      page: newPage,
      per_page: pagination.per_page,
      search: searchQuery || undefined,
      status: statusFilter || undefined,
      priority: priorityFilter || undefined,
      customer_company_id: customerFilter || undefined,
      country: regionFilter || undefined,
    });
  };

  const handleCloseDrawer = () => {
    setIsDrawerOpen(false);
    isDrawerOpenRef.current = false;
  };

  return (
    <DispatcherSurface className="w-full text-left font-sans select-none">
      <PageHeader
        title="Shipment Operations"
        subtitle={
          user?.isPartnerCarrier
            ? `Partner Fleet Workspace (${user.companyName || 'Carrier Fleet'}) · Monitor operational loads assigned to your fleet.`
            : 'Monitor loads, priorities, and assignment status in a single workspace.'
        }
        showStreaming={false}
        onRefresh={() => void triggerRefresh()}
      />

      <ShipmentKPIs refreshKey={refreshKey} />

      <FilterToolbar searchValue={searchQuery} onSearchChange={setSearchQuery} onReset={handleResetFilters}>
        <select aria-label="Shipment status" value={statusFilter} onChange={(event) => setStatusFilter(event.target.value)} className="rounded-2xl border border-white/10 bg-white/5 px-3 py-2 text-sm text-slate-300 outline-none">
          <option value="">All statuses</option>
          <option value="Draft">Draft</option>
          <option value="Pending Review">Pending Review</option>
          <option value="Approved">Approved</option>
          <option value="Waiting Assignment">Waiting Assignment</option>
          <option value="Assigned">Assigned</option>
          <option value="Ready For Pickup">Ready For Pickup</option>
          <option value="Picked Up">Picked Up</option>
          <option value="In Transit">In Transit</option>
          <option value="At Customs">At Customs</option>
          <option value="Out For Delivery">Out For Delivery</option>
          <option value="Delivered">Delivered</option>
          <option value="Completed">Completed</option>
          <option value="Cancelled">Cancelled</option>
        </select>
        <select aria-label="Shipment priority" value={priorityFilter} onChange={(event) => setPriorityFilter(event.target.value)} className="rounded-2xl border border-white/10 bg-white/5 px-3 py-2 text-sm text-slate-300 outline-none">
          <option value="">All priorities</option>
          <option value="urgent">urgent</option>
          <option value="high">high</option>
          <option value="normal">normal</option>
          <option value="low">low</option>
        </select>
      </FilterToolbar>

      {isLoading ? (
        <LoadingState title="Loading shipment operations" description="Loading shipment roster and lifecycle status." />
      ) : shipments.length === 0 ? (
        <EmptyState title="No shipments match the current filters" description="Adjust search or filter criteria to surface the right operational context." />
      ) : (
        <ShipmentOperationsTable
          shipments={shipments}
          isLoading={false}
          onRowClick={handleRowClick}
          pagination={pagination}
          onPageChange={handlePageChange}
        />
      )}

      <ShipmentDetailsDrawer
        shipment={selectedShipment}
        isOpen={isDrawerOpen}
        onClose={handleCloseDrawer}
      />
    </DispatcherSurface>
  );
}
