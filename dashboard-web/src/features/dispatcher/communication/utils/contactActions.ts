/**
 * Build safe tel:/mailto: hrefs from verified contact strings.
 * Returns null when the value cannot form a real contact action.
 */

export function normalizePhoneForTel(phone: string | null | undefined): string | null {
  if (!phone || !phone.trim()) return null;
  // Keep leading +, strip other non-digits for tel: URI
  const trimmed = phone.trim();
  const hasPlus = trimmed.startsWith('+');
  const digits = trimmed.replace(/\D/g, '');
  if (digits.length < 7) return null;
  return hasPlus ? `+${digits}` : digits;
}

export function telHref(phone: string | null | undefined): string | null {
  const normalized = normalizePhoneForTel(phone);
  return normalized ? `tel:${normalized}` : null;
}

export function mailtoHref(email: string | null | undefined): string | null {
  if (!email || !email.trim()) return null;
  const value = email.trim();
  // Basic shape check — do not invent addresses
  if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) return null;
  return `mailto:${value}`;
}

export function displayOrUnavailable(value: string | null | undefined): string {
  const trimmed = value?.trim();
  return trimmed ? trimmed : 'Unavailable';
}
