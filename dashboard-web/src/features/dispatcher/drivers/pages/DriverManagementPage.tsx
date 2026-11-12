import { useCallback, useEffect, useRef, useState } from 'react';
import { useAuth } from '../../../../core/auth';
import { DispatcherSurface } from '../../../../shared/components/dispatcher/DispatcherSurface';
import { PageHeader } from '../../../../shared/components/dispatcher/PageHeader';
import { FilterToolbar } from '../../../../shared/components/dispatcher/FilterToolbar';
import { LoadingState } from '../../../../shared/components/dispatcher/LoadingState';
import { EmptyState } from '../../../../shared/components/dispatcher/EmptyState';
import {
  getDrivers,
  getDriverById,
  getDriverKPIs,
  type DriverQueryParams,
} from '../services/driver-operations.service';
import type { Driver, DriverKPIs, DriverPagination } from '../types/driver-operations.types';
import DriverKPIsPanel from '../components/DriverKPIs';
import DriverList from '../components/DriverList';
import DriverDetailsPanel from '../components/DriverDetailsPanel';
import DriverPerformance from '../components/DriverPerformance';
import DriverTimeline from '../components/DriverTimeline';
import DriverAssignmentPanel from '../components/DriverAssignmentPanel';
import DriverCommunication from '../components/DriverCommunication';
import DriverComplianceCard from '../components/DriverComplianceCard';

const POLL_INTERVAL_MS = 15_000;

const OFFICIAL_STATUSES = ['Available', 'Busy', 'Offline', 'On Leave', 'Suspended'] as const;

export default function DriverManagementPage() {
  const { user } = useAuth();
  const isPartner = Boolean(user?.isPartnerCarrier);
  const companyId = user?.companyId ?? null;

  const [drivers, setDrivers] = useState<Driver[]>([]);
  const [selectedDriverId, setSelectedDriverId] = useState<string>('');
  const [selectedDriver, setSelectedDriver] = useState<Driver | null>(null);
  const [kpis, setKpis] = useState<DriverKPIs | null>(null);
  const [pagination, setPagination] = useState<DriverPagination>({
    current_page: 1,
    per_page: 15,
    total: 0,
    last_page: 1,
  });
  const [searchQuery, setSearchQuery] = useState('');
  const [statusFilter, setStatusFilter] = useState('');
  const [loading, setLoading] = useState(true);
  const [listError, setListError] = useState<string | null>(null);
  const [kpiError, setKpiError] = useState<string | null>(null);
  const [detailError, setDetailError] = useState<string | null>(null);

  const selectedDriverIdRef = useRef<string>('');
  const inFlightRef = useRef(false);
  const detailInFlightRef = useRef(false);
  const hasLoadedRef = useRef(false);
  const filterParamsRef = useRef({
    search: '',
    status: '',
    page: 1,
    per_page: 15,
  });

  useEffect(() => {
    selectedDriverIdRef.current = selectedDriverId;
  }, [selectedDriverId]);

  useEffect(() => {
    filterParamsRef.current = {
      search: searchQuery,
      status: statusFilter,
      page: pagination.current_page,
      per_page: pagination.per_page,
    };
  }, [searchQuery, statusFilter, pagination.current_page, pagination.per_page]);

  const buildListParams = useCallback((): DriverQueryParams => {
    const f = filterParamsRef.current;
    const params: DriverQueryParams = {
      page: f.page,
      per_page: f.per_page,
      is_active: true,
    };
    if (f.search) params.search = f.search;
    if (f.status) params.status = f.status;
    return params;
  }, []);

  const refreshSelectedDriver = useCallback(async (driverId: string) => {
    if (!driverId || detailInFlightRef.current) return;
    detailInFlightRef.current = true;
    try {
      const fresh = await getDriverById(driverId);
      if (selectedDriverIdRef.current !== driverId) return;
      setSelectedDriver(fresh);
      setDetailError(null);
    } catch (error) {
      if (selectedDriverIdRef.current !== driverId) return;
      setDetailError(error instanceof Error ? error.message : 'Failed to refresh driver details');
    } finally {
      detailInFlightRef.current = false;
    }
  }, []);

  const loadRoster = useCallback(async (options?: { silent?: boolean; params?: DriverQueryParams }) => {
    if (inFlightRef.current) return;
    inFlightRef.current = true;

    const silent = Boolean(options?.silent) && hasLoadedRef.current;
    if (!silent) {
      setLoading(true);
    }

    try {
      const params = options?.params ?? buildListParams();
      const fetchKpis = !isPartner;

      const [listSettled, kpisSettled] = await Promise.allSettled([
        getDrivers(params),
        fetchKpis ? getDriverKPIs() : Promise.resolve(null),
      ]);

      if (listSettled.status === 'fulfilled') {
        const listResult = listSettled.value;

        // Scoping & Fail-Closed Rule
        let scopedDrivers: Driver[];
        if (isPartner) {
          if (!companyId) {
            scopedDrivers = []; // Fail closed if company context missing
          } else {
            scopedDrivers = listResult.drivers.filter(
              (d) => !d.companyId || d.companyId === companyId
            );
          }
        } else {
          scopedDrivers = listResult.drivers;
        }

        setDrivers(scopedDrivers);
        setPagination(listResult.pagination);
        setListError(null);
        hasLoadedRef.current = true;

        const currentId = selectedDriverIdRef.current;
        if (currentId) {
          const stillPresent = scopedDrivers.find((d) => d.id === currentId);
          if (stillPresent) {
            await refreshSelectedDriver(currentId);
          } else if (scopedDrivers.length > 0) {
            const next = scopedDrivers[0];
            setSelectedDriverId(next.id);
            selectedDriverIdRef.current = next.id;
            await refreshSelectedDriver(next.id);
          } else {
            setSelectedDriverId('');
            setSelectedDriver(null);
          }
        } else if (scopedDrivers.length > 0) {
          const next = scopedDrivers[0];
          setSelectedDriverId(next.id);
          selectedDriverIdRef.current = next.id;
          await refreshSelectedDriver(next.id);
        } else {
          setSelectedDriverId('');
          setSelectedDriver(null);
        }
      } else {
        const message =
          listSettled.reason instanceof Error
            ? listSettled.reason.message
            : 'Failed to load driver roster';
        setListError(message);
        if (!hasLoadedRef.current) {
          setDrivers([]);
        }
      }

      if (fetchKpis && kpisSettled.status === 'fulfilled') {
        setKpis(kpisSettled.value);
        setKpiError(null);
        hasLoadedRef.current = true;
      } else if (fetchKpis) {
        const message =
          kpisSettled.reason instanceof Error
            ? kpisSettled.reason.message
            : 'Failed to load driver KPIs';
        setKpiError(message);
        if (!hasLoadedRef.current) {
          setKpis(null);
        }
      } else {
        setKpis(null);
      }
    } finally {
      inFlightRef.current = false;
      if (!silent) {
        setLoading(false);
      }
    }
  }, [buildListParams, isPartner, companyId, refreshSelectedDriver]);

  const triggerRefresh = useCallback(() => {
    void loadRoster({ silent: true });
  }, [loadRoster]);

  // Filter-driven load (reset to page 1)
  useEffect(() => {
    filterParamsRef.current = {
      ...filterParamsRef.current,
      search: searchQuery,
      status: statusFilter,
      page: 1,
    };
    void loadRoster({
      params: {
        page: 1,
        per_page: filterParamsRef.current.per_page,
        is_active: true,
        search: searchQuery || undefined,
        status: statusFilter || undefined,
      },
    });
    // eslint-disable-next-line react-hooks/exhaustive-deps -- intentional: filter changes only
  }, [searchQuery, statusFilter, loadRoster]);

  // Visibility-aware 15s polling
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
          triggerRefresh();
        }
      }, POLL_INTERVAL_MS);
    };

    const onVisibilityChange = () => {
      if (document.hidden) {
        clearPolling();
        return;
      }
      triggerRefresh();
      startPolling();
    };

    startPolling();
    document.addEventListener('visibilitychange', onVisibilityChange);

    return () => {
      clearPolling();
      document.removeEventListener('visibilitychange', onVisibilityChange);
    };
  }, [triggerRefresh]);

  const handleDriverSelect = async (driver: Driver) => {
    setSelectedDriverId(driver.id);
    selectedDriverIdRef.current = driver.id;
    setSelectedDriver(driver);
    await refreshSelectedDriver(driver.id);
  };

  const handlePageChange = (page: number) => {
    filterParamsRef.current = { ...filterParamsRef.current, page };
    void loadRoster({
      params: {
        page,
        per_page: filterParamsRef.current.per_page,
        is_active: true,
        search: searchQuery || undefined,
        status: statusFilter || undefined,
      },
    });
  };

  const handleResetFilters = () => {
    setSearchQuery('');
    setStatusFilter('');
  };

  if (loading && !hasLoadedRef.current) {
    return (
      <LoadingState
        title="Loading driver operations"
        description="Loading driver roster and dispatch availability."
      />
    );
  }

  return (
    <DispatcherSurface className="w-full text-left font-sans select-none">
      <PageHeader
        title="Driver Operations"
        subtitle={
          isPartner
            ? `Partner Fleet Workspace (${user?.companyName || 'Carrier Fleet'}) · Driver roster & dispatch availability.`
            : 'Monitor official status, dispatch availability, and active assignments.'
        }
        showStreaming={false}
        onRefresh={triggerRefresh}
      />

      <DriverKPIsPanel kpis={kpis} error={kpiError} />

      <FilterToolbar searchValue={searchQuery} onSearchChange={setSearchQuery} onReset={handleResetFilters}>
        <select
          aria-label="Driver status"
          value={statusFilter}
          onChange={(event) => setStatusFilter(event.target.value)}
          className="rounded-2xl border border-white/10 bg-white/5 px-3 py-2 text-sm text-slate-300 outline-none"
        >
          <option value="">All statuses</option>
          {OFFICIAL_STATUSES.map((status) => (
            <option key={status} value={status}>
              {status}
            </option>
          ))}
        </select>
      </FilterToolbar>

      {listError && drivers.length === 0 ? (
        <EmptyState title="Driver roster unavailable" description={listError} />
      ) : drivers.length === 0 ? (
        <EmptyState
          title={isPartner ? 'No drivers are available for your partner fleet.' : 'No drivers found in roster.'}
          description="Adjust filter criteria or contact an administrator to register drivers."
        />
      ) : (
        <div className="grid grid-cols-1 lg:grid-cols-12 gap-6 w-full items-stretch">
          <div className="lg:col-span-3 h-full min-h-[480px]">
            <DriverList
              drivers={drivers}
              selectedDriverId={selectedDriverId}
              onDriverSelect={(driver) => void handleDriverSelect(driver)}
              pagination={pagination}
              onPageChange={handlePageChange}
              error={listError}
            />
          </div>
          <div className="lg:col-span-3 h-full min-h-[480px]">
            <DriverDetailsPanel driver={selectedDriver} />
            {detailError ? (
              <p className="mt-2 text-xs text-red-400">{detailError}</p>
            ) : null}
          </div>
          <div className="lg:col-span-3 h-full min-h-[480px]">
            <DriverPerformance driver={selectedDriver} />
          </div>
          <div className="lg:col-span-3 h-full min-h-[480px]">
            <DriverAssignmentPanel driver={selectedDriver} />
          </div>
        </div>
      )}

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6 w-full">
        <DriverTimeline />
        <DriverCommunication driver={selectedDriver} />
        <DriverComplianceCard driver={selectedDriver} />
      </div>
    </DispatcherSurface>
  );
}
