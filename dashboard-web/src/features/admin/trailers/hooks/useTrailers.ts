import { useCallback, useEffect, useState } from 'react';
import { trailersService } from '../services/trailers.service';
import type { Trailer, TrailerCollection, TrailerQueryParams } from '../types/trailer.types';

const defaultPagination: TrailerCollection['meta'] = {
  current_page: 1,
  per_page: 15,
  total: 0,
  last_page: 1,
  from: 0,
  to: 0,
};

export function useTrailers(initialParams?: TrailerQueryParams) {
  const [trailers, setTrailers] = useState<Trailer[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [pagination, setPagination] = useState<TrailerCollection['meta']>(defaultPagination);
  const [params, setParams] = useState<TrailerQueryParams>(initialParams || { page: 1, per_page: 15 });

  const loadTrailers = useCallback(async (queryParams: TrailerQueryParams) => {
    setIsLoading(true);
    setError(null);

    try {
      const data = await trailersService.getTrailers(queryParams);
      setTrailers(data.data);
      setPagination(data.meta);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to fetch trailers');
    } finally {
      setIsLoading(false);
    }
  }, []);

  useEffect(() => {
    loadTrailers(params);
  }, [loadTrailers, params]);

  const updateParams = useCallback((newParams: Partial<TrailerQueryParams>) => {
    setParams((current) => ({ ...current, ...newParams }));
  }, []);

  const refresh = useCallback(() => {
    loadTrailers(params);
  }, [loadTrailers, params]);

  const replaceTrailer = useCallback((id: string, updates: Partial<Trailer>) => {
    setTrailers((current) => current.map((item) => (item.id === id ? { ...item, ...updates } : item)));
  }, []);

  return {
    trailers,
    isLoading,
    error,
    pagination,
    params,
    updateParams,
    refresh,
    replaceTrailer,
  };
}
