import axios from 'axios';
import { axiosClientWithInterceptors, cleanParams } from '../../../../core/api';
import type {
  InvoiceLifecycleErrorPayload,
  MonthlyInvoiceCollection,
  MonthlyInvoiceDetail,
  MonthlyInvoiceQueryParams,
} from '../types/invoice.types';

interface ApiEnvelope<T> {
  success: boolean;
  message: string;
  data: T;
  errors?: InvoiceLifecycleErrorPayload;
}

export class MonthlyInvoiceApiError extends Error {
  status?: number;
  errors?: InvoiceLifecycleErrorPayload;

  constructor(message: string, status?: number, errors?: InvoiceLifecycleErrorPayload) {
    super(message);
    this.name = 'MonthlyInvoiceApiError';
    this.status = status;
    this.errors = errors;
  }
}

function toMonthlyInvoiceApiError(error: unknown, fallback: string): MonthlyInvoiceApiError {
  if (axios.isAxiosError(error)) {
    const data = error.response?.data as {
      message?: string;
      errors?: InvoiceLifecycleErrorPayload;
    } | undefined;

    return new MonthlyInvoiceApiError(
      data?.message || fallback,
      error.response?.status,
      data?.errors,
    );
  }

  return new MonthlyInvoiceApiError(error instanceof Error ? error.message : fallback);
}

export interface StripeCheckoutResponse {
  checkout_url: string;
  session_id: string;
  invoice_id: number;
  status: string;
}

export interface PaymentStatusResponse {
  invoice_id: number;
  status: 'draft' | 'finalized' | 'void' | 'paid';
  paid_at: string | null;
  payment_pending: boolean;
}

export const monthlyInvoicesService = {
  async getMonthlyInvoices(params?: MonthlyInvoiceQueryParams): Promise<MonthlyInvoiceCollection> {
    try {
      const response = await axiosClientWithInterceptors.get<ApiEnvelope<MonthlyInvoiceCollection>>(
        '/monthly-invoices',
        { params: params ? cleanParams(params) : undefined },
      );
      return response.data.data;
    } catch (error) {
      throw toMonthlyInvoiceApiError(error, 'Failed to fetch monthly invoices');
    }
  },

  async getMonthlyInvoice(id: number | string): Promise<MonthlyInvoiceDetail> {
    try {
      const response = await axiosClientWithInterceptors.get<ApiEnvelope<MonthlyInvoiceDetail>>(
        `/monthly-invoices/${id}`,
      );
      return response.data.data;
    } catch (error) {
      throw toMonthlyInvoiceApiError(error, 'Failed to fetch monthly invoice');
    }
  },

  async finalizeMonthlyInvoice(id: number | string): Promise<MonthlyInvoiceDetail> {
    try {
      const response = await axiosClientWithInterceptors.post<ApiEnvelope<MonthlyInvoiceDetail>>(
        `/monthly-invoices/${id}/finalize`,
      );
      return response.data.data;
    } catch (error) {
      throw toMonthlyInvoiceApiError(error, 'Failed to finalize monthly invoice');
    }
  },

  async voidMonthlyInvoice(id: number | string): Promise<MonthlyInvoiceDetail> {
    try {
      const response = await axiosClientWithInterceptors.post<ApiEnvelope<MonthlyInvoiceDetail>>(
        `/monthly-invoices/${id}/void`,
      );
      return response.data.data;
    } catch (error) {
      throw toMonthlyInvoiceApiError(error, 'Failed to void monthly invoice');
    }
  },

  async openMonthlyInvoicePdf(id: number | string): Promise<void> {
    try {
      const response = await axiosClientWithInterceptors.get<Blob>(
        `/monthly-invoices/${id}/pdf`,
        { responseType: 'blob' },
      );

      const blob = new Blob([response.data], { type: 'application/pdf' });
      const url = window.URL.createObjectURL(blob);
      window.open(url, '_blank', 'noopener,noreferrer');
      window.setTimeout(() => window.URL.revokeObjectURL(url), 60_000);
    } catch (error) {
      throw toMonthlyInvoiceApiError(error, 'Failed to open monthly invoice PDF');
    }
  },

  async createCheckoutSession(id: number | string): Promise<StripeCheckoutResponse> {
    try {
      const response = await axiosClientWithInterceptors.post<ApiEnvelope<StripeCheckoutResponse>>(
        `/client/monthly-invoices/${id}/checkout`,
      );
      return response.data.data;
    } catch (error) {
      throw toMonthlyInvoiceApiError(error, 'Failed to create Stripe Checkout session');
    }
  },

  async getPaymentStatus(id: number | string): Promise<PaymentStatusResponse> {
    try {
      const response = await axiosClientWithInterceptors.get<ApiEnvelope<PaymentStatusResponse>>(
        `/client/monthly-invoices/${id}/payment-status`,
      );
      return response.data.data;
    } catch (error) {
      throw toMonthlyInvoiceApiError(error, 'Failed to fetch payment status');
    }
  },

  async getClientMonthlyInvoice(id: number | string): Promise<MonthlyInvoiceDetail> {
    try {
      const response = await axiosClientWithInterceptors.get<ApiEnvelope<MonthlyInvoiceDetail>>(
        `/client/monthly-invoices/${id}`,
      );
      return response.data.data;
    } catch (error) {
      throw toMonthlyInvoiceApiError(error, 'Failed to fetch client monthly invoice');
    }
  },
};
