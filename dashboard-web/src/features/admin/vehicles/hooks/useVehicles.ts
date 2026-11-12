import { useCallback, useEffect, useState } from 'react';
import { vehiclesService } from '../services/vehicles.service';
import type { Vehicle, VehicleCollection, VehicleQueryParams } from '../types/vehicle.types';

const defaultPagination: VehicleCollection['meta'] = {
  current_page: 1,
  per_page: 15,
  total: 0,
  last_page: 1,
  from: 0,
  to: 0,
};

export function useVehicles(initialParams?: VehicleQueryParams) {
  const [vehicles, setVehicles] = useState<Vehicle[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [pagination, setPagination] = useState<VehicleCollection['meta']>(defaultPagination);
  const [params, setParams] = useState<VehicleQueryParams>(initialParams || { page: 1, per_page: 15 });

  const loadVehicles = useCallback(async (queryParams: VehicleQueryParams) => {
    setIsLoading(true);
    setError(null);

    try {
      const data = await vehiclesService.getVehicles(queryParams);
      setVehicles(data.data);
      setPagination(data.meta);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to fetch vehicles');
    } finally {
      setIsLoading(false);
    }
  }, []);

  useEffect(() => {
    loadVehicles(params);
  }, [loadVehicles, params]);

  const updateParams = useCallback((newParams: Partial<VehicleQueryParams>) => {
    setParams((current) => ({ ...current, ...newParams }));
  }, []);

  const refresh = useCallback(() => {
    loadVehicles(params);
  }, [loadVehicles, params]);

  const replaceVehicle = useCallback((vehicleId: string, updates: Partial<Vehicle>) => {
    setVehicles((current) => current.map((vehicle) => (
      vehicle.id === vehicleId ? { ...vehicle, ...updates } : vehicle
    )));
  }, []);

  return {
    vehicles,
    isLoading,
    error,
    pagination,
    params,
    updateParams,
    refresh,
    replaceVehicle,
  };
}
