import { useCallback, useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { DispatcherSurface } from '../../../../shared/components/dispatcher/DispatcherSurface';
import { PageHeader } from '../../../../shared/components/dispatcher/PageHeader';
import { FilterToolbar } from '../../../../shared/components/dispatcher/FilterToolbar';
import { LoadingState } from '../../../../shared/components/dispatcher/LoadingState';
import { EmptyState } from '../../../../shared/components/dispatcher/EmptyState';
import { ErrorState } from '../../../../shared/components/dispatcher/ErrorState';
import { SHIPMENT_ORDER_STATUSES } from '../types/request.types';
import { useShipmentRequests } from '../hooks/useShipmentRequests';
import RequestsTable from '../components/RequestsTable';
import { formatRequestStatusLabel } from '../utils/requestMapper';
import type { ShipmentRequestListItem } from '../types/request.types';

type RequestsTab = 'active' | 'history';

/**
 * Dispatcher Request Intake & History Workspace.
 */
export default function RequestsPage() {
  const navigate = useNavigate();
  const { requests, isLoading, error, pagination, loadRequests } = useShipmentRequests();

  const [activeTab, setActiveTab] = useState<RequestsTab>('active');
  const [searchQuery, setSearchQuery] = useState('');
  const [statusFilter, setStatusFilter] = useState('');
  const [pickupFrom, setPickupFrom] = useState('');
  const [pickupTo, setPickupTo] = useState('');

  const buildParams = useCallback(
    (page = 1) => ({
      page,
      per_page: pagination.per_page || 15,
      search: searchQuery || undefined,
      status: statusFilter || (activeTab === 'history' ? 'converted' : undefined),
      exclude_converted: activeTab === 'active' ? true : false,
      requested_pickup_date_from: pickupFrom || undefined,
      requested_pickup_date_to: pickupTo || undefined,
    }),
    [pagination.per_page, searchQuery, statusFilter, activeTab, pickupFrom, pickupTo]
  );

  useEffect(() => {
    void loadRequests(buildParams(1));
  }, [searchQuery, statusFilter, activeTab, pickupFrom, pickupTo]); // eslint-disable-line react-hooks/exhaustive-deps

  const handleResetFilters = () => {
    setSearchQuery('');
    setStatusFilter('');
    setPickupFrom('');
    setPickupTo('');
  };

  const handlePageChange = (page: number) => {
    void loadRequests(buildParams(page));
  };

  const handleOpen = (request: ShipmentRequestListItem) => {
    navigate(`/dispatcher/requests/${request.id}`);
  };

  const handleRefresh = () => {
    void loadRequests(buildParams(pagination.current_page || 1));
  };

  const statusOptions = activeTab === 'active'
    ? SHIPMENT_ORDER_STATUSES.filter((s) => s !== 'converted')
    : SHIPMENT_ORDER_STATUSES;

  return (
    <DispatcherSurface className="w-full text-left font-sans select-none">
      <PageHeader
        title={activeTab === 'active' ? 'Active Request Intake' : 'Converted & Request History'}
        subtitle={
          activeTab === 'active'
            ? 'Scan incoming shipment requests, review decisions, approve, and convert to operational shipments.'
            : 'Historical shipment requests permanently preserved for auditability and financial tracking.'
        }
        badge="Operations"
        showStreaming={false}
        onRefresh={handleRefresh}
      />

      {/* Tabs bar */}
      <div className="mb-4 flex items-center gap-2 border-b border-white/10 pb-3">
        <button
          type="button"
          onClick={() => {
            setActiveTab('active');
            setStatusFilter('');
          }}
          className={`rounded-xl px-4 py-2 text-xs font-bold uppercase tracking-wider transition ${
            activeTab === 'active'
              ? 'bg-cyan-500/20 text-cyan-200 border border-cyan-400/40 shadow-sm'
              : 'text-slate-400 hover:bg-white/5 hover:text-slate-200 border border-transparent'
          }`}
        >
          Active Requests Queue
        </button>
        <button
          type="button"
          onClick={() => {
            setActiveTab('history');
            setStatusFilter('converted');
          }}
          className={`rounded-xl px-4 py-2 text-xs font-bold uppercase tracking-wider transition ${
            activeTab === 'history'
              ? 'bg-purple-500/20 text-purple-200 border border-purple-400/40 shadow-sm'
              : 'text-slate-400 hover:bg-white/5 hover:text-slate-200 border border-transparent'
          }`}
        >
          Converted / History
        </button>
      </div>

      <FilterToolbar
        searchValue={searchQuery}
        onSearchChange={setSearchQuery}
        onReset={handleResetFilters}
      >
        <select
          aria-label="Request status"
          value={statusFilter}
          onChange={(event) => setStatusFilter(event.target.value)}
          className="rounded-2xl border border-white/10 bg-white/5 px-3 py-2 text-sm text-slate-300 outline-none"
        >
          <option value="">
            {activeTab === 'active' ? 'All active statuses' : 'All statuses'}
          </option>
          {statusOptions.map((status) => (
            <option key={status} value={status}>
              {formatRequestStatusLabel(status)}
            </option>
          ))}
        </select>
        <label className="flex items-center gap-2 rounded-2xl border border-white/10 bg-white/5 px-3 py-2 text-xs text-slate-400">
          Pickup from
          <input
            type="date"
            aria-label="Requested pickup date from"
            value={pickupFrom}
            onChange={(event) => setPickupFrom(event.target.value)}
            className="bg-transparent text-sm text-slate-200 outline-none"
          />
        </label>
        <label className="flex items-center gap-2 rounded-2xl border border-white/10 bg-white/5 px-3 py-2 text-xs text-slate-400">
          Pickup to
          <input
            type="date"
            aria-label="Requested pickup date to"
            value={pickupTo}
            onChange={(event) => setPickupTo(event.target.value)}
            className="bg-transparent text-sm text-slate-200 outline-none"
          />
        </label>
      </FilterToolbar>

      {isLoading ? (
        <LoadingState
          title="Loading shipment requests"
          description="Fetching request intake queue from the operations API."
        />
      ) : error ? (
        <div className="flex flex-col gap-3">
          <ErrorState title="Unable to load requests" description={error} />
          <button
            type="button"
            onClick={handleRefresh}
            className="self-start rounded-2xl border border-cyan-400/30 bg-cyan-500/10 px-4 py-2 text-sm font-semibold text-cyan-200"
          >
            Retry
          </button>
        </div>
      ) : requests.length === 0 ? (
        <EmptyState
          title={
            activeTab === 'active'
              ? 'No active requests in the queue'
              : 'No converted request history matches the current filters'
          }
          description={
            activeTab === 'active'
              ? 'All client requests have been processed or converted into operational shipments.'
              : 'Adjust search or date filters to locate converted requests.'
          }
        />
      ) : (
        <RequestsTable
          requests={requests}
          pagination={pagination}
          onOpen={handleOpen}
          onPageChange={handlePageChange}
        />
      )}
    </DispatcherSurface>
  );
}
