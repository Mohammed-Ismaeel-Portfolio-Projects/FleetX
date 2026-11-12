import { useState } from 'react';
import { Map } from 'lucide-react';
import { useTracking } from '../hooks/useTracking';
import type { TrackingSortField, MovementStatus, OnlineStatus, SignalQuality } from '../types/tracking.types';
import TrackingTable from '../components/TrackingTable';
import FiltersBar from '../components/FiltersBar';
import StatisticsCards from '../components/StatisticsCards';
import TrackingDetailsDrawer from '../components/TrackingDetailsDrawer';
import TrackingStatesMap from '../components/TrackingStatesMap';

const defaultParams = {
  page: 1,
  per_page: 15,
  sort_by: 'last_update_at' as TrackingSortField,
  sort_direction: 'desc' as const,
};

export default function TrackingPage() {
  const {
    trackingStates,
    isLoading,
    error,
    pagination,
    statistics,
    updateParams,
    refresh,
  } = useTracking(defaultParams);

  const [searchQuery, setSearchQuery] = useState('');
  const [movementFilter, setMovementFilter] = useState<MovementStatus | ''>('');
  const [onlineFilter, setOnlineFilter] = useState<OnlineStatus | ''>('');
  const [signalFilter, setSignalFilter] = useState<SignalQuality | ''>('');
  const [driverFilter, setDriverFilter] = useState('');
  const [vehicleFilter, setVehicleFilter] = useState('');
  const [shipmentFilter, setShipmentFilter] = useState('');
  const [sortField, setSortField] = useState<TrackingSortField>('last_update_at');
  const [sortDirection, setSortDirection] = useState<'asc' | 'desc'>('desc');
  const [selectedTrackingState, setSelectedTrackingState] = useState<null | any>(null);
  const [isDrawerOpen, setIsDrawerOpen] = useState(false);
  const [isDrawerLoading, setIsDrawerLoading] = useState(false);

  const handleSort = (field: TrackingSortField) => {
    const direction = sortField === field && sortDirection === 'asc' ? 'desc' : 'asc';
    setSortField(field);
    setSortDirection(direction);
    updateParams({ sort_by: field, sort_direction: direction, page: 1 });
  };

  const handleResetFilters = () => {
    setSearchQuery('');
    setMovementFilter('');
    setOnlineFilter('');
    setSignalFilter('');
    setDriverFilter('');
    setVehicleFilter('');
    setShipmentFilter('');

    updateParams({
      search: undefined,
      movement_status: undefined,
      online_status: undefined,
      signal_quality: undefined,
      driver_id: undefined,
      vehicle_id: undefined,
      shipment_id: undefined,
      page: 1,
      sort_by: sortField,
      sort_direction: sortDirection,
    });
  };

  const handleView = async (trackingState: any) => {
    setIsDrawerOpen(true);
    setIsDrawerLoading(true);
    setSelectedTrackingState(trackingState);
    setIsDrawerLoading(false);
  };

  const hasFilters = Boolean(
    searchQuery
    || movementFilter
    || onlineFilter
    || signalFilter
    || driverFilter
    || vehicleFilter
    || shipmentFilter
  );

  return (
    <div className="flex w-full flex-col gap-6 font-sans text-left select-none">
      <div className="flex flex-col gap-1.5">
        <div className="flex items-center gap-2">
          <Map className="h-5 w-5 text-cyan-400" />
          <h1 className="text-2xl font-bold tracking-tight text-white">Tracking Administration</h1>
        </div>
        <p className="text-xs font-normal text-slate-500">
          Monitor GPS telemetry, vehicle locations, and tracking states.
        </p>
      </div>

      <StatisticsCards statistics={statistics} />

      <div className="rounded-2xl border border-white/5 bg-[#0E1320] p-6 shadow-md">
        <div className="mb-4 flex items-center justify-between">
          <h2 className="text-lg font-semibold text-white">Tracking States</h2>
          {hasFilters && (
            <button
              onClick={handleResetFilters}
              className="text-xs text-cyan-400 hover:text-cyan-300"
            >
              Reset Filters
            </button>
          )}
        </div>

        <FiltersBar
          searchQuery={searchQuery}
          movementFilter={movementFilter}
          onlineFilter={onlineFilter}
          signalFilter={signalFilter}
          driverFilter={driverFilter}
          vehicleFilter={vehicleFilter}
          shipmentFilter={shipmentFilter}
          onSearchChange={setSearchQuery}
          onMovementChange={setMovementFilter}
          onOnlineChange={setOnlineFilter}
          onSignalChange={setSignalFilter}
          onDriverChange={setDriverFilter}
          onVehicleChange={setVehicleFilter}
          onShipmentChange={setShipmentFilter}
          onReset={handleResetFilters}
        />

        <div className="mt-4">
          <TrackingTable
            trackingStates={trackingStates}
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
      </div>

      <TrackingStatesMap trackingStates={trackingStates} isLoading={isLoading} />

      <TrackingDetailsDrawer
        trackingState={selectedTrackingState}
        isOpen={isDrawerOpen}
        isLoading={isDrawerLoading}
        onClose={() => setIsDrawerOpen(false)}
      />
    </div>
  );
}
