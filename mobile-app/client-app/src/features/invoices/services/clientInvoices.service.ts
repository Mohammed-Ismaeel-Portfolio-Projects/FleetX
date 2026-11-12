import { apiClient } from '../../../lib/api/client';
import type {
  MonthlyInvoiceCollection,
  MonthlyInvoiceDetail,
  PaymentStatusResponse,
  StripeCheckoutResponse,
} from '../types/invoice.types';

interface ApiEnvelope<T> {
  success: boolean;
  message: string;
  data: T;
}

export const clientInvoicesService = {
  async getInvoices(params?: { page?: number; per_page?: number; status?: string }): Promise<MonthlyInvoiceCollection> {
    const response = await apiClient.get<ApiEnvelope<MonthlyInvoiceCollection>>('/client/monthly-invoices', {
      params,
    });
    return response.data.data;
  },

  async getInvoice(id: number | string): Promise<MonthlyInvoiceDetail> {
    const response = await apiClient.get<ApiEnvelope<MonthlyInvoiceDetail>>(`/client/monthly-invoices/${id}`);
    return response.data.data;
  },

  async createCheckoutSession(id: number | string, returnUrl?: string): Promise<StripeCheckoutResponse> {
    const payload = returnUrl ? { return_url: returnUrl } : {};
    const response = await apiClient.post<ApiEnvelope<StripeCheckoutResponse>>(
      `/client/monthly-invoices/${id}/checkout`,
      payload,
      returnUrl ? { headers: { 'X-Return-Url': returnUrl } } : undefined,
    );
    return response.data.data;
  },

  async getPaymentStatus(id: number | string): Promise<PaymentStatusResponse> {
    const response = await apiClient.get<ApiEnvelope<PaymentStatusResponse>>(`/client/monthly-invoices/${id}/payment-status`);
    return response.data.data;
  },
};
