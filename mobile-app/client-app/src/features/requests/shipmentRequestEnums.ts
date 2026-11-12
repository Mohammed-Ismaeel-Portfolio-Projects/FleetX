/**
 * Authoritative Client shipment-request enums (SHIPMENT-REQUEST-02).
 * Values must match App\Enums\CargoType and App\Enums\TrailerType.
 * Labels are UI-only and must never be submitted as API values.
 */

export interface EnumOption<T extends string = string> {
  value: T;
  label: string;
}

export const CARGO_TYPE_OPTIONS = [
  { value: 'general', label: 'General Freight' },
  { value: 'refrigerated', label: 'Refrigerated' },
  { value: 'heavy', label: 'Heavy Cargo' },
  { value: 'hazardous', label: 'Hazardous' },
  { value: 'fragile', label: 'Fragile' },
] as const satisfies readonly EnumOption[];

export type CargoTypeValue = (typeof CARGO_TYPE_OPTIONS)[number]['value'];

export const TRAILER_TYPE_OPTIONS = [
  { value: 'dry_van', label: 'Dry Van' },
  { value: 'reefer', label: 'Reefer' },
  { value: 'flatbed', label: 'Flatbed' },
  { value: 'lowboy', label: 'Lowboy' },
  { value: 'tanker', label: 'Tanker' },
] as const satisfies readonly EnumOption[];

export type TrailerTypeValue = (typeof TRAILER_TYPE_OPTIONS)[number]['value'];

const CARGO_VALUES = new Set<string>(CARGO_TYPE_OPTIONS.map((o) => o.value));
const TRAILER_VALUES = new Set<string>(TRAILER_TYPE_OPTIONS.map((o) => o.value));

export function isCargoTypeValue(value: string): value is CargoTypeValue {
  return CARGO_VALUES.has(value);
}

export function isTrailerTypeValue(value: string): value is TrailerTypeValue {
  return TRAILER_VALUES.has(value);
}

export function cargoTypeLabel(value: string | null | undefined): string | null {
  if (!value) return null;
  const match = CARGO_TYPE_OPTIONS.find((o) => o.value === value);
  return match?.label ?? value;
}

export function trailerTypeLabel(value: string | null | undefined): string | null {
  if (!value) return null;
  const match = TRAILER_TYPE_OPTIONS.find((o) => o.value === value);
  return match?.label ?? value;
}
