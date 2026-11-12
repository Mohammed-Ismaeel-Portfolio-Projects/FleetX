/**
 * Presentation-only money/distance formatting for pricing estimate.
 * Never calculates business prices.
 */

export function formatEstimateMoney(
  amount: number | null | undefined,
  currency: string | null | undefined
): string | null {
  if (amount == null || !Number.isFinite(amount) || !currency) return null;
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

export function formatEstimateDistanceKm(km: number | null | undefined): string | null {
  if (km == null || !Number.isFinite(km)) return null;
  return `${km.toFixed(1)} km`;
}

const CHARGE_LABELS: Record<string, string> = {
  freight: 'Freight',
  weight_surcharge: 'Weight surcharge',
  waiting_charge: 'Waiting',
  services: 'Services',
  border: 'Border fee',
  insurance: 'Insurance',
  hazmat: 'Hazmat surcharge',
  heavy_reefer: 'Heavy / reefer surcharge',
  priority: 'Priority surcharge',
};

export function estimateChargeRows(
  charges: Record<string, number> | null | undefined,
  currency: string | null | undefined
): { key: string; label: string; display: string }[] {
  if (!charges || !currency) return [];
  const rows: { key: string; label: string; display: string }[] = [];
  for (const [key, label] of Object.entries(CHARGE_LABELS)) {
    const amount = charges[key];
    if (typeof amount !== 'number' || !Number.isFinite(amount) || amount === 0) continue;
    const display = formatEstimateMoney(amount, currency);
    if (display) rows.push({ key, label, display });
  }
  return rows;
}
