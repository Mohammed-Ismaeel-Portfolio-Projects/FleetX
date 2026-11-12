import { useState, useEffect, useCallback } from 'react';
import type { Company, CompanyQueryParams } from '../types/company.types';
import { companiesService } from '../services/companies.service';

export function useCompanies(initialParams?: CompanyQueryParams) {
  const [companies, setCompanies] = useState<Company[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [pagination, setPagination] = useState({
    current_page: 1,
    per_page: 15,
    total: 0,
    last_page: 1,
    from: 0,
    to: 0,
  });
  const [params, setParams] = useState<CompanyQueryParams>(initialParams || { page: 1, per_page: 15 });

  const loadCompanies = useCallback(async (queryParams: CompanyQueryParams) => {
    setIsLoading(true);
    setError(null);
    try {
      const data = await companiesService.getCompanies(queryParams);
      setCompanies(data.data);
      setPagination(data.meta);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to fetch companies');
    } finally {
      setIsLoading(false);
    }
  }, []);

  useEffect(() => {
    loadCompanies(params);
  }, [loadCompanies, params]);

  const updateParams = useCallback((newParams: Partial<CompanyQueryParams>) => {
    setParams((current) => ({ ...current, ...newParams }));
  }, []);

  const refresh = useCallback(() => {
    loadCompanies(params);
  }, [loadCompanies, params]);

  const replaceCompany = useCallback((companyId: string, updates: Partial<Company>) => {
    setCompanies((current) => current.map((company) => (
      company.id === companyId ? { ...company, ...updates } : company
    )));
  }, []);

  return {
    companies,
    isLoading,
    error,
    pagination,
    params,
    updateParams,
    refresh,
    replaceCompany,
  };
}
