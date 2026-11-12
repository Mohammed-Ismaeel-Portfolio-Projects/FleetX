export type MonthlyInvoiceStatus = 'draft' | 'finalized' | 'void' | 'paid';

export interface BillingPeriod {
  start: string | null;
  end: string | null;
}

export interface MonthlyInvoiceListItem {
  id: number;
  company_id?: string | null;
  billing_period: BillingPeriod;
  currency: string;
  status: MonthlyInvoiceStatus;
  subtotal: number;
  tax?: number;
  total: number;
  issued_at: string | null;
  paid_at?: string | null;
  line_item_count?: number;
  created_at: string | null;
}

export interface InvoiceLineItem {
  id: number;
  shipment_id: string | null;
  shipment_reference: string | null;
  description: string | null;
  quantity: number;
  unit_price: number;
  line_total: number;
  currency: string;
}

export interface MonthlyInvoiceDetail extends MonthlyInvoiceListItem {
  line_items?: InvoiceLineItem[];
  stripe_checkout_session_id?: string | null;
  stripe_payment_intent_id?: string | null;
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

export interface StripeCheckoutResponse {
  checkout_url: string;
  session_id: string;
  invoice_id: number;
  status: string;
}

export interface PaymentStatusResponse {
  invoice_id: number;
  status: MonthlyInvoiceStatus;
  paid_at: string | null;
  payment_pending: boolean;
  stripe_checkout_session_id?: string | null;
  stripe_payment_intent_id?: string | null;
}
