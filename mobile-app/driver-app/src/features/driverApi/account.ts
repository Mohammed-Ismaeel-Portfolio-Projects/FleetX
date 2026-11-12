/**
 * Account-facing projection from EXISTING me/identity payloads only.
 * No fabricated settings or Ops fields.
 */

import type { AuthIdentity, AuthUser } from '@/features/messaging/types';

export type DriverAccountView = {
  displayName: string;
  email: string;
  employeeNumber: string | null;
  companyName: string | null;
  companyCode: string | null;
  linked: boolean;
  unlinkReason: string | null;
};

export function buildAccountView(
  user: AuthUser | null,
  identity: AuthIdentity | null
): DriverAccountView {
  return {
    displayName: user?.name?.trim() || 'Driver',
    email: user?.email?.trim() || 'Unavailable',
    employeeNumber: identity?.driver?.employee_number?.trim() || null,
    companyName:
      identity?.company?.company_name?.trim() ||
      user?.company?.company_name?.trim() ||
      null,
    companyCode:
      identity?.company?.company_code?.trim() ||
      user?.company?.company_code?.trim() ||
      null,
    linked: Boolean(identity?.linked),
    unlinkReason: identity?.unlink_reason ?? null,
  };
}
