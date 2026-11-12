import { useState, useCallback, useRef, useEffect } from 'react';
import TrackingHeader from '../components/TrackingHeader';
import TrackingStats from '../components/TrackingStats';
import FleetList from '../components/FleetList';
import { TrackingMap as SharedTrackingMap } from '../../../../shared/components/tracking-map';
import TrackingDetails from '../components/TrackingDetails';
import TrackingTimeline from '../components/TrackingTimeline';
import LoadingState from '../components/LoadingState';
import LiveIndicator from '../components/LiveIndicator';
import { useLiveTracking } from '../hooks/useLiveTracking';
import type { FleetVehicle, VehicleDetails } from '../types/tracking-dashboard.types';
import MapToolbar from '../components/map/MapToolbar';
import QuickFilters from '../components/map/QuickFilters';
import { cn } from "../../../../lib/utils";

export default function TrackingDashboard() {
  const [searchValue, setSearchValue] = useState('');
  const [selectedVehicle, setSelectedVehicle] = useState<FleetVehicle | null>(null);
  const mapRef = useRef<any>(null);

  const { vehicles, stats, events, loading, error, refresh, isLive, lastUpdate, startPolling, stopPolling, getVehicleTrail } = useLiveTracking();

  // Start live tracking on mount
  useEffect(() => {
    startPolling();
    return () => stopPolling();
  }, [startPolling, stopPolling]);

  // Update selected vehicle data when vehicles refresh (keep selection alive)
  useEffect(() => {
    if (selectedVehicle) {
      const updatedVehicle = vehicles.find(v => v.id === selectedVehicle.id);
      if (updatedVehicle) {
        setSelectedVehicle(updatedVehicle);
      }
    }
  }, [vehicles, selectedVehicle]);

  const handleVehicleSelect = useCallback((vehicle: FleetVehicle) => {
    setSelectedVehicle(vehicle);
  }, []);

  const handleMapInit = useCallback((map: any) => {
    mapRef.current = map;
  }, []);

  const flyToVehicle = useCallback((vehicle: FleetVehicle) => {
    if (mapRef.current) {
      mapRef.current.flyTo(
        [vehicle.latitude || 0, vehicle.longitude || 0],
        15,
        {
          duration: 1.5,
          easeLinearity: 0.25,
        }
      );
    }
  }, []);

  const handleFleetListSelect = useCallback((vehicle: FleetVehicle) => {
    handleVehicleSelect(vehicle);
    flyToVehicle(vehicle);
    // Scroll details panel into view
    setTimeout(() => {
      const detailsPanel = document.querySelector('[data-details-panel]');
      if (detailsPanel) {
        detailsPanel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
      }
    }, 100);
  }, [handleVehicleSelect, flyToVehicle]);

  const handleFilterChange = (_filters: string[]) => {
    // Handle filter changes if needed
  };

  const vehicleDetails: VehicleDetails | null = selectedVehicle
    ? {
      vehicle: {
        id: selectedVehicle.id,
        fleetNumber: selectedVehicle.fleetNumber,
        plateNumber: selectedVehicle.plateNumber,
        manufacturer: 'Volvo',
        model: 'FH16',
        year: 2023,
        vehicleType: 'Heavy Truck',
        connectionStatus: selectedVehicle.status === 'offline' ? 'Offline' : 'Online',
        engineStatus: selectedVehicle.status === 'moving' ? 'Running' : 'Idle',
      },
      driver: {
        id: selectedVehicle.driverId,
        name: selectedVehicle.driver,
        employeeNumber: selectedVehicle.driverId,
        phone: '+1-555-0100',
        email: 'driver@fleetx.com',
      },
      shipment: {
        id: selectedVehicle.shipmentId,
        shipmentNumber: selectedVehicle.shipment,
        origin: 'Berlin, DE',
        destination: 'Paris, FR',
        status: 'In Transit',
      },
      assignment: {
        id: 'ASN-001',
        code: 'ASG-2024-001',
        startedAt: new Date(Date.now() - 3600000 * 4).toISOString(),
        estimatedCompletion: new Date(Date.now() + 3600000 * 6).toISOString(),
      },
      tracking: {
        status: selectedVehicle.status,
        coordinates: {
          latitude: selectedVehicle.latitude || 0,
          longitude: selectedVehicle.longitude || 0,
        },
        speed: selectedVehicle.speed,
        heading: selectedVehicle.heading,
        signalQuality: selectedVehicle.signalQuality,
        movementStatus: selectedVehicle.status === 'moving' ? 'Moving' : 'Idle',
        lastUpdate: selectedVehicle.lastUpdate,
      },
      tripProgress: {
        originCity: 'Berlin, DE',
        destinationCity: 'Paris, FR',
        progressPercent: 42,
        distanceRemaining: 580,
        estimatedDistance: 1000,
        estimatedArrival: new Date(Date.now() + 3600000 * 6).toISOString(),
        elapsedTripTime: '4h 12m',
        averageSpeed: selectedVehicle.speed > 0 ? selectedVehicle.speed * 0.9 : 45,
        currentSpeed: selectedVehicle.speed,
      },
      liveTracking: {
        gpsCoordinates: `${selectedVehicle.latitude?.toFixed(6) || 0}, ${selectedVehicle.longitude?.toFixed(6) || 0}`,
        heading: selectedVehicle.heading,
        signalQuality: selectedVehicle.signalQuality,
        gpsAccuracy: selectedVehicle.signalQuality === 'good' ? '±5m' : selectedVehicle.signalQuality === 'weak' ? '±25m' : '±100m',
        lastUpdate: selectedVehicle.lastUpdate,
        lastCommunication: selectedVehicle.lastUpdate,
        onlineDuration: selectedVehicle.status === 'offline' ? '0m' : '4h 12m',
      },
      operationalHealth: {
        gps: selectedVehicle.signalQuality === 'good' ? 'healthy' : selectedVehicle.signalQuality === 'weak' ? 'warning' : 'critical',
        engine: selectedVehicle.status === 'moving' ? 'healthy' : 'warning',
        network: selectedVehicle.status === 'offline' ? 'critical' : 'healthy',
        assignment: 'healthy',
        shipment: 'healthy',
      },
      quickMetrics: {
        todayDistance: 420,
        averageSpeed: selectedVehicle.speed > 0 ? selectedVehicle.speed * 0.9 : 45,
        maxSpeed: 89,
        idleTime: '1h 45m',
        drivingTime: '4h 12m',
      },
    }
    : null;

  if (loading) {
    return <LoadingState />;
  }

  if (error) {
    return (
      <div className={cn('flex', 'h-full', 'items-center', 'justify-center', 'rounded-2xl', 'border', 'border-white/5', 'bg-[#0E1320]', 'p-12', 'text-center')}>
        <p className={cn('text-sm', 'text-red-400')}>{error}</p>
      </div>
    );
  }

  return (
    <div className={cn('flex', 'w-full', 'flex-col', 'gap-6', 'font-sans', 'text-left', 'select-none')}>
      <TrackingHeader
        searchValue={searchValue}
        onSearchChange={setSearchValue}
        onRefresh={refresh}
        onFilter={() => { }}
      />

      <TrackingStats stats={stats} />

      <div className={cn('grid', 'grid-cols-1', 'gap-6', 'lg:grid-cols-3')}>
        {/* Left Column - Fleet List (30%) */}
        <div className="lg:col-span-1">
          <FleetList
            vehicles={vehicles}
            selectedVehicleId={selectedVehicle?.id}
            isLoading={loading}
            searchValue={searchValue}
            onSearchChange={setSearchValue}
            onVehicleSelect={handleFleetListSelect}
          />
        </div>

        {/* Right Column - Map and Details (70%) */}
        <div className={cn('relative', 'flex', 'flex-col', 'gap-6', 'lg:col-span-2')}>
          <div className={cn('rounded-2xl', 'border', 'border-white/5', 'bg-[#0E1320]', 'p-6', 'shadow-md')}>
            <div className="space-y-4">
              {/* Row 1: Live Indicator */}
              <div className="flex items-start">
                <LiveIndicator isLive={isLive} lastUpdate={lastUpdate} />
              </div>

              {/* Row 2: Quick Filters */}
              <QuickFilters onFilterChange={handleFilterChange} />

              {/* Toolbar */}
              <MapToolbar vehicles={vehicles} />
            </div>
          </div>
          <SharedTrackingMap vehicles={vehicles} onVehicleSelect={handleVehicleSelect} onMapInit={handleMapInit} getVehicleTrail={getVehicleTrail} vehicleDetails={vehicleDetails} enableRouteMonitoring={true} />
          <TrackingDetails
            details={vehicleDetails}
            performanceDriverId={selectedVehicle?.driverId ?? null}
            performanceAssignmentId={selectedVehicle?.assignmentId ?? null}
          />
          <TrackingTimeline events={events} />
        </div>
      </div>
    </div>
  );
}
