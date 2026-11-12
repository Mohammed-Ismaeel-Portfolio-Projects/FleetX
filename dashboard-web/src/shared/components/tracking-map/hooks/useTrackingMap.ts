import { useCallback, useEffect, useRef, useState } from 'react';
import L from 'leaflet';
import { calculateBounds, boundsToLeaflet } from '../utils/map.utils';
import type { TrackingMapVehicle } from '../types';

export function useTrackingMap(vehicles: TrackingMapVehicle[]) {
  const mapRef = useRef<L.Map | null>(null);
  const [selectedVehicleId, setSelectedVehicleId] = useState<string | null>(null);
  const hasInitializedRef = useRef(false);

  const fitBounds = useCallback(() => {
    if (mapRef.current && vehicles.length > 0) {
      const bounds = calculateBounds(vehicles);
      if (bounds) {
        const leafletBounds = boundsToLeaflet(bounds);
        mapRef.current.fitBounds(leafletBounds, { 
          padding: [60, 60], 
          maxZoom: 12,
          duration: 1.5,
          easeLinearity: 0.25
        });
        
        setTimeout(() => {
          if (mapRef.current) {
            const currentZoom = mapRef.current.getZoom();
            if (currentZoom < 3) {
              mapRef.current.setZoom(3);
            }
          }
        }, 1600);
      }
    }
  }, [vehicles]);

  const flyToVehicle = useCallback((vehicle: TrackingMapVehicle) => {
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

  useEffect(() => {
    if (mapRef.current && vehicles.length > 0 && !selectedVehicleId && !hasInitializedRef.current) {
      fitBounds();
      hasInitializedRef.current = true;
    }
  }, [vehicles, selectedVehicleId, fitBounds]);

  const handleVehicleSelect = useCallback((vehicle: TrackingMapVehicle) => {
    setSelectedVehicleId(vehicle.id);
  }, []);

  const handleMapInit = useCallback((event: any) => {
    mapRef.current = event.target;
    fitBounds();
  }, [fitBounds]);

  return {
    mapRef,
    selectedVehicleId,
    setSelectedVehicleId,
    fitBounds,
    flyToVehicle,
    handleVehicleSelect,
    handleMapInit,
  };
}
