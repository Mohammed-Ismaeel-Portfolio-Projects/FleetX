import { useState, useCallback, useRef } from 'react';
import type { Shipment } from '../types/shipment.types';
import { shipmentsService } from '../services/shipments.service';

export type ShipmentQueryParams = {
  search?: string;
  status_id?: string;
  status?: string;
  priority?: string;
  customer_company_id?: string;
  country?: string;
  page?: number;
  per_page?: number;
  sort_by?: string;
  sort_direction?: string;
};

export function useShipments() {
  const [shipments, setShipments] = useState<Shipment[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [pagination, setPagination] = useState({
    current_page: 1,
    per_page: 15,
    total: 0,
    last_page: 1,
  });

  const inFlightRef = useRef(false);
  const hasLoadedRef = useRef(false);
  const paramsRef = useRef<ShipmentQueryParams>({});

  const loadShipments = useCallback(async (
    params?: ShipmentQueryParams,
    options?: { silent?: boolean }
  ) => {
    if (inFlightRef.current) return;
    inFlightRef.current = true;

    const silent = Boolean(options?.silent) && hasLoadedRef.current;
    const nextParams = { ...paramsRef.current, ...params };
    paramsRef.current = nextParams;

    if (!silent) {
      setIsLoading(true);
    }
    setError(null);

    try {
      const result = await shipmentsService.getShipments(nextParams);
      setShipments(result.data);
      if (result.meta) {
        setPagination({
          current_page: result.meta.current_page || 1,
          per_page: result.meta.per_page || 15,
          total: result.meta.total || 0,
          last_page: result.meta.last_page || 1,
        });
      }
      hasLoadedRef.current = true;
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to fetch shipments');
    } finally {
      inFlightRef.current = false;
      if (!silent) {
        setIsLoading(false);
      }
    }
  }, []);

  return {
    shipments,
    isLoading,
    error,
    pagination,
    loadShipments,
  };
}
