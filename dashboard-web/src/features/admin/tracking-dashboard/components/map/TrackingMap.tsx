import { memo, useMemo, useEffect, useState, useCallback } from 'react';
import { MapContainer, TileLayer, useMapEvents } from 'react-leaflet';
import 'leaflet/dist/leaflet.css';
import { useTrackingMap } from './hooks/useTrackingMap';
import VehicleMarker from './VehicleMarker';
import VehicleTrail from './VehicleTrail';
import MapControls from './MapControls';
import MapLegend from './MapLegend';
import ClusterMarker from './ClusterMarker';
import LiveStatusFooter from './LiveStatusFooter';
import RouteMonitoringOverlay from './route-monitoring/RouteMonitoringOverlay';
import type { FleetVehicle } from '../../types/tracking-dashboard.types';
import type { TrailPoint } from '../../hooks/useVehicleTrail';

type FilterType = 'moving' | 'idle' | 'offline' | 'alerts' | 'trucks' | 'trailers' | 'all';

interface TrackingMapProps {
  vehicles: FleetVehicle[];
  onVehicleSelect: (vehicle: FleetVehicle) => void;
  onMapInit?: (map: any) => void;
  getVehicleTrail: (vehicleId: string) => TrailPoint[];
  vehicleDetails: any; // VehicleDetails from tracking.types
}

function MapZoomTracker({ onZoomChange }: { onZoomChange: (zoom: number) => void }) {
  const map = useMapEvents({
    zoomend: () => {
      onZoomChange(map.getZoom());
    },
  });
  
  useEffect(() => {
    onZoomChange(map.getZoom());
  }, [map, onZoomChange]);
  
  return null;
}

function TrackingMapComponent({ vehicles, onVehicleSelect, onMapInit, getVehicleTrail, vehicleDetails }: TrackingMapProps) {
  const [zoom, setZoom] = useState(4);
  const [activeFilters] = useState<FilterType[]>(['all']);
  const [lastRefresh, setLastRefresh] = useState(new Date());

  const {
    selectedVehicleId,
    fitBounds,
    flyToVehicle,
    handleVehicleSelect,
  } = useTrackingMap(vehicles);

  const handleMapInitInternal = useCallback((event: any) => {
    onMapInit?.(event.target);
    fitBounds();
  }, [onMapInit, fitBounds]);

  const vehiclesWithAlerts = useMemo(() => {
    return vehicles.reduce((acc, vehicle) => {
      acc[vehicle.id] = false;
      return acc;
    }, {} as Record<string, boolean>);
  }, [vehicles]);

  const filteredVehicles = useMemo(() => {
    if (activeFilters.includes('all')) return vehicles;

    return vehicles.filter(vehicle => {
      if (activeFilters.includes('moving') && vehicle.status === 'moving') return true;
      if (activeFilters.includes('idle') && vehicle.status === 'idle') return true;
      if (activeFilters.includes('offline') && vehicle.status === 'offline') return true;
      return false;
    });
  }, [vehicles, activeFilters]);

  const selectedVehicle = useMemo(() => {
    return vehicles.find(v => v.id === selectedVehicleId) || null;
  }, [vehicles, selectedVehicleId]);

  const handleMarkerClick = useCallback((vehicle: FleetVehicle) => {
    handleVehicleSelect(vehicle);
    onVehicleSelect(vehicle);
    flyToVehicle(vehicle);
  }, [handleVehicleSelect, onVehicleSelect, flyToVehicle]);


  const handleRefresh = useCallback(() => {
    setLastRefresh(new Date());
  }, []);

  // Use clustering when many vehicles
  const useClustering = filteredVehicles.length > 50;
  const clusteredVehicles = useMemo(() => {
    if (!useClustering) return [];
    
    // Simple clustering logic - group nearby vehicles
    const clusters: { position: [number, number]; vehicles: any[]; count: number }[] = [];
    const processed = new Set<string>();
    
    filteredVehicles.forEach(vehicle => {
      if (processed.has(vehicle.id)) return;
      
      const nearby = filteredVehicles.filter(v => {
        if (v.id === vehicle.id) return false;
        const dist = Math.sqrt(
          Math.pow((v.latitude || 0) - (vehicle.latitude || 0), 2) +
          Math.pow((v.longitude || 0) - (vehicle.longitude || 0), 2)
        );
        return dist < 0.5; // Cluster vehicles within 0.5 degrees
      });
      
      if (nearby.length >= 3) {
        const clusterVehicles = [vehicle, ...nearby];
        const avgLat = clusterVehicles.reduce((sum, v) => sum + (v.latitude || 0), 0) / clusterVehicles.length;
        const avgLng = clusterVehicles.reduce((sum, v) => sum + (v.longitude || 0), 0) / clusterVehicles.length;
        
        clusters.push({
          position: [avgLat, avgLng],
          vehicles: clusterVehicles,
          count: clusterVehicles.length,
        });
        
        clusterVehicles.forEach(v => processed.add(v.id));
      }
    });
    
    return clusters;
  }, [filteredVehicles, useClustering]);

  const nonClusteredVehicles = useMemo(() => {
    if (!useClustering) return filteredVehicles;
    
    const clusteredIds = new Set(clusteredVehicles.flatMap(c => c.vehicles.map(v => v.id)));
    return filteredVehicles.filter(v => !clusteredIds.has(v.id));
  }, [filteredVehicles, clusteredVehicles, useClustering]);

  // Memoize vehicle trails to prevent recalculating on every render
  const vehicleTrails = useMemo(() => {
    return nonClusteredVehicles.reduce((acc, vehicle) => {
      acc[vehicle.id] = getVehicleTrail(vehicle.id);
      return acc;
    }, {} as Record<string, import('../../hooks/useVehicleTrail').TrailPoint[]>);
  }, [nonClusteredVehicles, getVehicleTrail]);

  if (vehicles.length === 0) {
    return (
      <div className="flex h-full min-h-[400px] items-center justify-center rounded-2xl border border-white/5 bg-[#0E1320]">
        <div className="text-center">
          <p className="text-lg font-semibold text-white">No Active Tracking</p>
          <p className="mt-2 text-sm text-slate-400">
            No vehicles with active tracking data.
          </p>
        </div>
      </div>
    );
  }

  return (
    <div className="relative h-full min-h-[600px] w-full rounded-2xl overflow-hidden border border-white/5 bg-[#0E1320]">
      <MapContainer
        style={{ height: '100%', width: '100%' }}
        {...({ center: [50, 10] as [number, number], zoom: 5, minZoom: 3, maxZoom: 18 } as any)}
        maxBounds={[[-90, -180], [90, 180]]}
        maxBoundsViscosity={1.0}
        whenCreated={handleMapInitInternal}
      >
        <MapZoomTracker onZoomChange={setZoom} />
        <TileLayer
          url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
        />

        {/* Route Monitoring Overlay for Selected Vehicle */}
        <RouteMonitoringOverlay
          selectedVehicle={selectedVehicle}
          vehicleDetails={vehicleDetails}
        />

        <>
          {nonClusteredVehicles.map((vehicle) => {
            const trail = vehicleTrails[vehicle.id];
            return (
              <div key={vehicle.id}>
                <VehicleTrail
                  trail={trail}
                  status={vehicle.status}
                  isSelected={vehicle.id === selectedVehicleId}
                />
                <VehicleMarker
                  vehicle={vehicle}
                  isSelected={vehicle.id === selectedVehicleId}
                  hasAlert={vehiclesWithAlerts[vehicle.id]}
                  onClick={handleMarkerClick}
                />
              </div>
            );
          })}
            
            {clusteredVehicles.map((cluster, index) => (
              <ClusterMarker
                key={`cluster-${index}`}
                vehicles={cluster.vehicles}
                position={cluster.position}
                count={cluster.count}
                onClick={() => {}}
              />
            ))}
          </>

        <MapControls onFitBounds={fitBounds} />
        <MapLegend />
      </MapContainer>

   
      {/* Live Status Footer */}
      <LiveStatusFooter
        vehicles={vehicles}
        visibleCount={filteredVehicles.length}
        zoom={zoom}
        selectedVehicle={selectedVehicle}
        lastRefresh={lastRefresh}
        onRefresh={handleRefresh}
      />
    </div>
  );
}

export default memo(TrackingMapComponent);
