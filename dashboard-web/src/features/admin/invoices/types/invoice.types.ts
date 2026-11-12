export type MonthlyInvoiceStatus = 'draft' | 'finalized' | 'void' | 'paid';

export interface InvoiceCompanySummary {
  id: string;
  company_code: string;
  company_name: string;
}

export interface BillingPeriod {
  start: string | null;
  end: string | null;
}

export interface MonthlyInvoiceListItem {
  id: number;
  company?: InvoiceCompanySummary | null;
  billing_period: BillingPeriod;
  currency: string;
  status: MonthlyInvoiceStatus;
  subtotal: number;
  total: number;
  issued_at: string | null;
  paid_at?: string | null;
  stripe_checkout_session_id?: string | null;
  line_item_count?: number;
  created_at: string | null;
  updated_at: string | null;
}

export interface FinalPriceBreakdownAudit {
  id: number;
  status: string | null;
  final_total: number;
  currency: string;
  calculated_at: string | null;
}

export interface InvoiceLineItem {
  id: number;
  shipment_id: string | null;
  shipment_reference: string | null;
  final_price_breakdown_id: number | null;
  description: string | null;
  quantity: number;
  unit_price: number;
  line_total: number;
  currency: string;
  final_price_breakdown?: FinalPriceBreakdownAudit | null;
}

export interface MonthlyInvoiceDetail extends MonthlyInvoiceListItem {
  line_items?: InvoiceLineItem[];
}

export interface MonthlyInvoiceCollection {
  data: MonthlyInvoiceListItem[];
  meta: {
    current_page: number;
    per_page: number;
    total: number;
    last_page: number;
    from: number | null;
    to: number | null;
  };
}

export interface MonthlyInvoiceQueryParams {
  page?: number;
  per_page?: number;
  company_id?: string;
  billing_period_start?: string;
  billing_period_end?: string;
  currency?: string;
  status?: MonthlyInvoiceStatus;
  sort_by?: 'created_at' | 'updated_at' | 'billing_period_start' | 'billing_period_end' | 'total' | 'subtotal' | 'currency' | 'status';
  sort_direction?: 'asc' | 'desc';
}

export interface InvoiceLifecycleErrorPayload {
  code?: string;
  current_status?: string;
  requested_status?: string;
  [key: string]: unknown;
}
