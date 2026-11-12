export function formatPricingMoney(amount: number | null | undefined, currency: string): string {
  if (amount === null || amount === undefined || Number.isNaN(amount)) {
    return '—';
  }

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

export function formatPricingNumber(value: number | null | undefined, digits = 2): string {
  if (value === null || value === undefined || Number.isNaN(value)) {
    return '—';
  }

  return value.toLocaleString(undefined, {
    minimumFractionDigits: digits,
    maximumFractionDigits: digits,
  });
}

export function formatTaxRate(rate: number | null | undefined): string {
  if (rate === null || rate === undefined) {
    return '—';
  }

  return `${formatPricingNumber(rate, 2)}%`;
}

export function formatDateTime(value: string | null | undefined): string {
  return value || '—';
}

export function flattenPricingErrors(errors?: Record<string, string[] | string | undefined>): string[] {
  if (!errors) {
    return [];
  }

  return Object.values(errors).flatMap((value) => {
    if (Array.isArray(value)) {
      return value;
    }

    return value ? [value] : [];
  });
}
