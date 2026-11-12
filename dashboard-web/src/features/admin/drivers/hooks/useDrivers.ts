import { useCallback, useEffect, useState } from 'react';
import { driversService, DriverApiError } from '../services/drivers.service';
import type { Driver, DriverCollection, DriverQueryParams } from '../types/driver.types';

interface UseDriversReturn {
  drivers: Driver[];
  isLoading: boolean;
  error: string | null;
  pagination: DriverCollection['meta'];
  params: DriverQueryParams;
  updateParams: (params: Partial<DriverQueryParams>) => void;
  refresh: () => void;
  replaceDriver: (driverId: string, updates: Partial<Driver>) => void;
}

const defaultPagination: DriverCollection['meta'] = {
  current_page: 1,
  per_page: 15,
  total: 0,
  last_page: 1,
  from: null,
  to: null,
};

export function useDrivers(initialParams?: DriverQueryParams): UseDriversReturn {
  const [drivers, setDrivers] = useState<Driver[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [pagination, setPagination] = useState<DriverCollection['meta']>(defaultPagination);
  const [params, setParams] = useState<DriverQueryParams>(initialParams || { page: 1, per_page: 15 });

  const fetchDrivers = useCallback(async (queryParams: DriverQueryParams) => {
    setIsLoading(true);
    setError(null);

    try {
      const response = await driversService.getDrivers(queryParams);
      setDrivers(response.data);
      setPagination(response.meta);
    } catch (err) {
      if (err instanceof DriverApiError) {
        setError(err.message);
      } else {
        setError('An unexpected error occurred');
      }
    } finally {
      setIsLoading(false);
    }
  }, []);

  useEffect(() => {
    fetchDrivers(params);
  }, [fetchDrivers, params]);

  const updateParams = useCallback((newParams: Partial<DriverQueryParams>) => {
    setParams((current) => ({ ...current, ...newParams }));
  }, []);

  const refresh = useCallback(() => {
    fetchDrivers(params);
  }, [fetchDrivers, params]);

  const replaceDriver = useCallback((driverId: string, updates: Partial<Driver>) => {
    setDrivers((current) => current.map((driver) => (
      driver.id === driverId ? { ...driver, ...updates } : driver
    )));
  }, []);

  return {
    drivers,
    isLoading,
    error,
    pagination,
    params,
    updateParams,
    refresh,
    replaceDriver,
  };
}
