import { useCallback, useRef } from 'react';

export interface TrailPoint {
  lat: number;
  lng: number;
  timestamp: number;
}

export interface VehicleTrail {
  vehicleId: string;
  points: TrailPoint[];
}

const MAX_TRAIL_POINTS = 15;

export function useVehicleTrail() {
  const trailsRef = useRef<Map<string, TrailPoint[]>>(new Map());

  const updateVehiclePosition = useCallback((vehicleId: string, lat: number, lng: number) => {
    const currentTrail = trailsRef.current.get(vehicleId) || [];
    
    // Add new point
    const newPoint: TrailPoint = {
      lat,
      lng,
      timestamp: Date.now(),
    };
    
    const updatedTrail = [...currentTrail, newPoint];
    
    // Trim to max 15 points
    if (updatedTrail.length > MAX_TRAIL_POINTS) {
      updatedTrail.shift();
    }
    
    trailsRef.current.set(vehicleId, updatedTrail);
    return updatedTrail;
  }, []);

  const getVehicleTrail = useCallback((vehicleId: string): TrailPoint[] => {
    return trailsRef.current.get(vehicleId) || [];
  }, []);

  const clearVehicleTrail = useCallback((vehicleId: string) => {
    trailsRef.current.delete(vehicleId);
  }, []);

  const clearAllTrails = useCallback(() => {
    trailsRef.current.clear();
  }, []);

  return {
    updateVehiclePosition,
    getVehicleTrail,
    clearVehicleTrail,
    clearAllTrails,
  };
}
