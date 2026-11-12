/**
 * C2.9-C3.9 — Client Profile presentation helpers.
 * Presentation only; never invents account data.
 */

export function deriveProfileInitials(name: string | null | undefined): string {
  const trimmed = (name ?? '').trim();
  if (!trimmed) return 'F';

  const parts = trimmed.split(/\s+/).filter(Boolean);
  if (parts.length === 1) {
    return parts[0].charAt(0).toUpperCase();
  }

  const first = parts[0].charAt(0);
  const last = parts[parts.length - 1].charAt(0);
  return `${first}${last}`.toUpperCase();
}

export function formatCustomerRoleLabel(role: string | null | undefined): string {
  const raw = (role ?? '').trim().toLowerCase();
  if (raw === 'client') return 'Customer';
  if (!raw) return 'Customer';
  return raw.charAt(0).toUpperCase() + raw.slice(1);
}

export function displayProfileName(name: string | null | undefined): string {
  const trimmed = (name ?? '').trim();
  return trimmed || 'FleetX Customer';
}
