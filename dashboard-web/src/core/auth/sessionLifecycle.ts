export const FLEETX_TOKEN_KEY = 'fleetx_token';
export const FLEETX_USER_KEY = 'fleetx_user';

export type DashboardRole = 'admin' | 'dispatcher';

export interface DashboardUser {
  id: number;
  name: string;
  role: DashboardRole;
  companyId?: string | null;
  companyName?: string | null;
  companyType?: string | null;
  isPartnerCarrier?: boolean;
}

export type AuthFailureKind = 'unauthorized' | 'unavailable';

/**
 * 401 (and 403) mean the session is invalid for the dashboard.
 * Missing status (network) and 5xx mean the API could not be reached — keep the token.
 */
export function classifyAuthRequestFailure(status: number | undefined): AuthFailureKind {
  if (status === 401 || status === 403) {
    return 'unauthorized';
  }
  return 'unavailable';
}

export function mapDashboardRole(roleName: unknown): DashboardRole | null {
  if (roleName === 'admin' || roleName === 'dispatcher') {
    return roleName;
  }
  return null;
}

export function isPartnerCarrierCompanyType(companyType: unknown): boolean {
  return companyType === 'partner_carrier' || companyType === 'hybrid';
}

export function mapMeToDashboardUser(me: {
  id: number;
  name: string;
  company_id?: string | null;
  companyId?: string | null;
  company_name?: string | null;
  companyName?: string | null;
  company_type?: string | null;
  companyType?: string | null;
  isPartnerCarrier?: boolean;
  role?: { name?: string } | DashboardRole | string | null;
  company?: {
    id?: string | null;
    company_name?: string | null;
    name?: string | null;
    company_type?: string | null;
    is_active?: boolean | null;
  } | null;
}): DashboardUser | null {
  const rawRoleName =
    typeof me.role === 'object' && me.role !== null ? me.role.name : me.role;
  const role = mapDashboardRole(rawRoleName);
  if (!role) {
    return null;
  }

  const companyId = me.company_id ?? me.company?.id ?? me.companyId ?? null;
  const companyName =
    me.company?.company_name ?? me.company?.name ?? me.company_name ?? me.companyName ?? null;
  const companyType = me.company?.company_type ?? me.company_type ?? me.companyType ?? null;

  const isPartnerCarrier =
    typeof me.isPartnerCarrier === 'boolean'
      ? me.isPartnerCarrier
      : isPartnerCarrierCompanyType(companyType);

  return {
    id: me.id,
    name: me.name,
    role,
    companyId,
    companyName,
    companyType,
    isPartnerCarrier,
  };
}

export function shouldSkipGlobalAuthRedirect(url: string | undefined): boolean {
  if (!url) {
    return false;
  }
  const path = url.split('?')[0] ?? url;
  return (
    path.includes('/auth/logout') ||
    path.includes('/auth/me') ||
    path.includes('/auth/login') ||
    path.includes('/auth/register')
  );
}

export function readStoredToken(storage: Pick<Storage, 'getItem'>): string | null {
  const token = storage.getItem(FLEETX_TOKEN_KEY);
  return token && token.length > 0 ? token : null;
}

export function persistDashboardSession(
  storage: Pick<Storage, 'setItem'>,
  user: DashboardUser,
  token: string
): void {
  storage.setItem(FLEETX_USER_KEY, JSON.stringify(user));
  storage.setItem(FLEETX_TOKEN_KEY, token);
}

export function clearDashboardSession(storage: Pick<Storage, 'removeItem'>): void {
  storage.removeItem(FLEETX_TOKEN_KEY);
  storage.removeItem(FLEETX_USER_KEY);
}
