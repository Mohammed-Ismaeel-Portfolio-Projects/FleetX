import { useCallback, useEffect, useState } from 'react';
import { shipmentsService, ShipmentApiError } from '../services/shipments.service';
import type { Shipment, ShipmentCollection, ShipmentQueryParams } from '../types/shipment.types';

const defaultPagination: ShipmentCollection['meta'] = {
  current_page: 1,
  per_page: 15,
  total: 0,
  last_page: 1,
  from: null,
  to: null,
};

export function useShipments(initialParams?: ShipmentQueryParams) {
  const [shipments, setShipments] = useState<Shipment[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [pagination, setPagination] = useState<ShipmentCollection['meta']>(defaultPagination);
  const [params, setParams] = useState<ShipmentQueryParams>(initialParams || { page: 1, per_page: 15 });

  const loadShipments = useCallback(async (queryParams: ShipmentQueryParams) => {
    setIsLoading(true);
    setError(null);

    try {
      const data = await shipmentsService.getShipments(queryParams);
      setShipments(data.data);
      setPagination(data.meta);
    } catch (err) {
      setError(err instanceof ShipmentApiError ? err.message : 'Failed to fetch shipments');
    } finally {
      setIsLoading(false);
    }
  }, []);

  useEffect(() => {
    loadShipments(params);
  }, [loadShipments, params]);

  const updateParams = useCallback((newParams: Partial<ShipmentQueryParams>) => {
    setParams((current) => ({ ...current, ...newParams }));
  }, []);

  const refresh = useCallback(() => {
    loadShipments(params);
  }, [loadShipments, params]);

  return {
    shipments,
    isLoading,
    error,
    pagination,
    params,
    updateParams,
    refresh,
  };
}
