import { useCallback, useEffect, useState } from 'react';
import { trackingService, TrackingApiError } from '../services/tracking.service';
import type { TrackingState, TrackingStateCollection, TrackingQueryParams, TrackingStatistics } from '../types/tracking.types';

const defaultPagination: TrackingStateCollection['meta'] = {
  current_page: 1,
  per_page: 15,
  total: 0,
  last_page: 1,
  from: null,
  to: null,
};

export function useTracking(initialParams?: TrackingQueryParams) {
  const [trackingStates, setTrackingStates] = useState<TrackingState[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [pagination, setPagination] = useState<TrackingStateCollection['meta']>(defaultPagination);
  const [params, setParams] = useState<TrackingQueryParams>(initialParams || { page: 1, per_page: 15 });
  const [statistics, setStatistics] = useState<TrackingStatistics>({
    total: 0,
    moving: 0,
    stopped: 0,
    idle: 0,
    offline: 0,
  });

  const loadTrackingStates = useCallback(async (queryParams: TrackingQueryParams) => {
    setIsLoading(true);
    setError(null);

    try {
      const data = await trackingService.getTrackingStates(queryParams);
      setTrackingStates(data.data);
      setPagination(data.meta);
      
      // Calculate statistics
      const stats: TrackingStatistics = {
        total: data.data.length,
        moving: data.data.filter(s => s.movement_status === 'Moving').length,
        stopped: data.data.filter(s => s.movement_status === 'Stopped').length,
        idle: data.data.filter(s => s.movement_status === 'Idle').length,
        offline: data.data.filter(s => s.online_status === 'Offline').length,
      };
      setStatistics(stats);
    } catch (err) {
      setError(err instanceof TrackingApiError ? err.message : 'Failed to fetch tracking states');
    } finally {
      setIsLoading(false);
    }
  }, []);

  useEffect(() => {
    loadTrackingStates(params);
  }, [loadTrackingStates, params]);

  const updateParams = useCallback((newParams: Partial<TrackingQueryParams>) => {
    setParams((current) => ({ ...current, ...newParams }));
  }, []);

  const refresh = useCallback(() => {
    loadTrackingStates(params);
  }, [loadTrackingStates, params]);

  return {
    trackingStates,
    isLoading,
    error,
    pagination,
    statistics,
    params,
    updateParams,
    refresh,
  };
}
