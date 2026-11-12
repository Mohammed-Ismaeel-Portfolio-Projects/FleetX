import { useCallback, useEffect, useState } from 'react';
import type { ShipmentRequestDetail } from '../types/request.types';
import { shipmentRequestsService } from '../services/shipmentRequests.service';

export function useShipmentRequestDetail(id: string | undefined) {
  const [request, setRequest] = useState<ShipmentRequestDetail | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const loadRequest = useCallback(async (options?: { silent?: boolean }) => {
    if (!id) {
      setIsLoading(false);
      setError('Request id is required');
      setRequest(null);
      return;
    }

    if (!options?.silent) {
      setIsLoading(true);
    }
    setError(null);

    try {
      const data = await shipmentRequestsService.getRequestById(id);
      setRequest(data);
    } catch (err) {
      setRequest(null);
      setError(err instanceof Error ? err.message : 'Failed to load request');
    } finally {
      if (!options?.silent) {
        setIsLoading(false);
      }
    }
  }, [id]);

  useEffect(() => {
    void loadRequest();
  }, [loadRequest]);

  return {
    request,
    setRequest,
    isLoading,
    error,
    refresh: loadRequest,
  };
}
