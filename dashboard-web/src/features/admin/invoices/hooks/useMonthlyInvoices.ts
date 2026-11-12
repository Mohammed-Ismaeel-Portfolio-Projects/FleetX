import { useCallback, useEffect, useState } from 'react';
import { monthlyInvoicesService, MonthlyInvoiceApiError } from '../services/monthlyInvoices.service';
import type { MonthlyInvoiceCollection, MonthlyInvoiceListItem, MonthlyInvoiceQueryParams } from '../types/invoice.types';

const defaultPagination: MonthlyInvoiceCollection['meta'] = {
  current_page: 1,
  per_page: 15,
  total: 0,
  last_page: 1,
  from: null,
  to: null,
};

export function useMonthlyInvoices(initialParams?: MonthlyInvoiceQueryParams) {
  const [invoices, setInvoices] = useState<MonthlyInvoiceListItem[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [pagination, setPagination] = useState<MonthlyInvoiceCollection['meta']>(defaultPagination);
  const [params, setParams] = useState<MonthlyInvoiceQueryParams>(initialParams || { page: 1, per_page: 15 });

  const loadInvoices = useCallback(async (queryParams: MonthlyInvoiceQueryParams) => {
    setIsLoading(true);
    setError(null);

    try {
      const data = await monthlyInvoicesService.getMonthlyInvoices(queryParams);
      setInvoices(data.data);
      setPagination(data.meta);
    } catch (err) {
      setError(err instanceof MonthlyInvoiceApiError ? err.message : 'Failed to fetch monthly invoices');
    } finally {
      setIsLoading(false);
    }
  }, []);

  useEffect(() => {
    loadInvoices(params);
  }, [loadInvoices, params]);

  const updateParams = useCallback((newParams: Partial<MonthlyInvoiceQueryParams>) => {
    setParams((current) => ({ ...current, ...newParams }));
  }, []);

  const refresh = useCallback(() => {
    loadInvoices(params);
  }, [loadInvoices, params]);

  return {
    invoices,
    isLoading,
    error,
    pagination,
    params,
    updateParams,
    refresh,
  };
}
