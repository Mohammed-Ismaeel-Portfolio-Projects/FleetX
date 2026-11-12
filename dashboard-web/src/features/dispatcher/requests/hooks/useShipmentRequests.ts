import { useCallback, useRef, useState } from 'react';
import type {
  ShipmentRequestListItem,
  ShipmentRequestListMeta,
  ShipmentRequestQueryParams,
} from '../types/request.types';
import { shipmentRequestsService } from '../services/shipmentRequests.service';

export function useShipmentRequests() {
  const [requests, setRequests] = useState<ShipmentRequestListItem[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [pagination, setPagination] = useState<ShipmentRequestListMeta>({
    current_page: 1,
    per_page: 15,
    total: 0,
    last_page: 1,
  });

  const inFlightRef = useRef(false);
  const hasLoadedRef = useRef(false);
  const paramsRef = useRef<ShipmentRequestQueryParams>({});

  const loadRequests = useCallback(async (
    params?: ShipmentRequestQueryParams,
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
      const result = await shipmentRequestsService.getRequests(nextParams);
      setRequests(result.data);
      setPagination({
        current_page: result.meta.current_page || 1,
        per_page: result.meta.per_page || 15,
        total: result.meta.total || 0,
        last_page: result.meta.last_page || 1,
        from: result.meta.from,
        to: result.meta.to,
      });
      hasLoadedRef.current = true;
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to fetch shipment requests');
      setRequests([]);
    } finally {
      inFlightRef.current = false;
      if (!silent) {
        setIsLoading(false);
      }
    }
  }, []);

  return {
    requests,
    isLoading,
    error,
    pagination,
    loadRequests,
  };
}
