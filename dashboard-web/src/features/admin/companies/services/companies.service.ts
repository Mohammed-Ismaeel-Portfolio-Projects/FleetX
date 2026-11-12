import axios from 'axios';
import { axiosClient, cleanParams } from '../../../../core/api';
import type {
  Company,
  CompanyCollection,
  CompanyPayload,
  CompanyQueryParams,
  CompanyValidationErrors,
} from '../types/company.types';

interface ApiEnvelope<T> {
  success: boolean;
  message: string;
  data: T;
}

export class CompanyApiError extends Error {
  status?: number;
  validationErrors: CompanyValidationErrors;

  constructor(message: string, status?: number, validationErrors: CompanyValidationErrors = {}) {
    super(message);
    this.name = 'CompanyApiError';
    this.status = status;
    this.validationErrors = validationErrors;
  }
}

function toCompanyApiError(error: unknown, fallback: string): CompanyApiError {
  if (axios.isAxiosError(error)) {
    const data = error.response?.data as {
      message?: string;
      errors?: CompanyValidationErrors;
    } | undefined;

    return new CompanyApiError(
      data?.message || fallback,
      error.response?.status,
      data?.errors || {},
    );
  }

  return new CompanyApiError(error instanceof Error ? error.message : fallback);
}

export const companiesService = {
  async getCompanies(params?: CompanyQueryParams): Promise<CompanyCollection> {
    try {
      const response = await axiosClient.get<ApiEnvelope<CompanyCollection>>('/companies', {
        params: params ? cleanParams(params) : undefined,
      });
      return response.data.data;
    } catch (error) {
      throw toCompanyApiError(error, 'Failed to fetch companies');
    }
  },

  async getCompanyById(id: string): Promise<Company> {
    try {
      const response = await axiosClient.get<ApiEnvelope<Company>>(`/companies/${id}`);
      return response.data.data;
    } catch (error) {
      throw toCompanyApiError(error, 'Failed to fetch company details');
    }
  },

  async createCompany(payload: CompanyPayload): Promise<Company> {
    try {
      const response = await axiosClient.post<ApiEnvelope<Company>>('/companies', payload);
      return response.data.data;
    } catch (error) {
      throw toCompanyApiError(error, 'Failed to create company');
    }
  },

  async updateCompany(id: string, payload: CompanyPayload): Promise<Company> {
    try {
      const response = await axiosClient.put<ApiEnvelope<Company>>(`/companies/${id}`, payload);
      return response.data.data;
    } catch (error) {
      throw toCompanyApiError(error, 'Failed to update company');
    }
  },

  async deleteCompany(id: string): Promise<void> {
    try {
      await axiosClient.delete<ApiEnvelope<null>>(`/companies/${id}`);
    } catch (error) {
      throw toCompanyApiError(error, 'Failed to delete company');
    }
  },

  async restoreCompany(id: string): Promise<Company> {
    try {
      const response = await axiosClient.patch<ApiEnvelope<Company>>(`/companies/${id}/restore`);
      return response.data.data;
    } catch (error) {
      throw toCompanyApiError(error, 'Failed to restore company');
    }
  },

  async toggleCompanyStatus(id: string, isActive: boolean): Promise<Company> {
    try {
      const response = await axiosClient.patch<ApiEnvelope<Company>>(`/companies/${id}/status`, {
        is_active: isActive,
      });
      return response.data.data;
    } catch (error) {
      throw toCompanyApiError(error, 'Failed to update company status');
    }
  },
};
