import type { BillingPeriod, MonthlyInvoiceStatus } from '../types/invoice.types';

export function formatInvoiceMoney(amount: number, currency: string): string {
  try {
    return new Intl.NumberFormat(undefined, {
      style: 'currency',
      currency,
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    }).format(amount);
  } catch {
    return `${currency} ${amount.toFixed(2)}`;
  }
}

export function formatBillingPeriod(period: BillingPeriod): string {
  const start = period.start ?? '—';
  const end = period.end ?? '—';
  return `${start} → ${end}`;
}

export function formatInvoiceStatusLabel(status: MonthlyInvoiceStatus): string {
  return status.toUpperCase();
}

export function formatDateTime(value: string | null | undefined): string {
  if (!value) {
    return '—';
  }
  return value;
}
