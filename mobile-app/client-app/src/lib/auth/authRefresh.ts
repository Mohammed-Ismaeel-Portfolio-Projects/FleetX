/**
 * C5.2 — Client JWT refresh (single-flight) against existing POST /auth/refresh.
 * Uses a standalone Axios call so the shared apiClient interceptor cannot recurse.
 */

import axios from 'axios';
import { AppConfig } from '../config';
import { clearAccessToken, getAccessToken, setAccessToken } from './tokenStorage';

export type AuthRefreshResult = {
  accessToken: string;
  expiresIn?: number;
};

/** Bumped on logout / local session clear so in-flight refresh cannot resurrect auth. */
let sessionEpoch = 0;

let refreshInFlight: Promise<AuthRefreshResult> | null = null;

export function getAuthSessionEpoch(): number {
  return sessionEpoch;
}

/** Call whenever local credentials are intentionally discarded (logout, hard clear). */
export function bumpAuthSessionEpoch(): void {
  sessionEpoch += 1;
}

function authBaseUrl(): string {
  return String(AppConfig.apiBaseUrl || '').replace(/\/$/, '');
}

/**
 * Paths that must never trigger refresh (unauthenticated or the refresh call itself).
 * Compared against the request URL (absolute or relative).
 */
export function isAuthRefreshExcludedUrl(url: string | undefined): boolean {
  if (!url) return false;
  const path = url.split('?')[0] ?? url;
  return (
    path.includes('/auth/login') ||
    path.includes('/auth/register') ||
    path.includes('/auth/refresh')
  );
}

/**
 * Perform one refresh using the currently stored bearer token.
 * Does not go through apiClient interceptors.
 */
export async function performAccessTokenRefresh(): Promise<AuthRefreshResult> {
  const epochAtStart = sessionEpoch;
  const token = await getAccessToken();
  if (!token) {
    throw new Error('No access token available to refresh.');
  }

  const response = await axios.post<{
    data?: {
      access_token?: string;
      token_type?: string;
      expires_in?: number;
      user?: unknown;
    };
    access_token?: string;
  }>(
    `${authBaseUrl()}/auth/refresh`,
    {},
    {
      timeout: 30000,
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        Authorization: `Bearer ${token}`,
      },
    }
  );

  if (epochAtStart !== sessionEpoch) {
    throw new Error('Session ended during refresh.');
  }

  const payload = response.data?.data ?? response.data;
  const accessToken =
    payload && typeof payload === 'object' && 'access_token' in payload
      ? (payload as { access_token?: unknown }).access_token
      : undefined;

  if (typeof accessToken !== 'string' || !accessToken.trim()) {
    throw new Error('Unexpected refresh response from server.');
  }

  const nextToken = accessToken.trim();

  if (epochAtStart !== sessionEpoch) {
    throw new Error('Session ended during refresh.');
  }

  await setAccessToken(nextToken);

  if (epochAtStart !== sessionEpoch) {
    // Logout won the race after persist — discard credentials.
    await clearAccessToken();
    throw new Error('Session ended during refresh.');
  }

  const expiresIn =
    payload && typeof payload === 'object' && typeof (payload as { expires_in?: unknown }).expires_in === 'number'
      ? (payload as { expires_in: number }).expires_in
      : undefined;

  return { accessToken: nextToken, expiresIn };
}

/**
 * Single-flight refresh: concurrent callers share one Promise.
 */
export function refreshAccessTokenSingleFlight(): Promise<AuthRefreshResult> {
  if (!refreshInFlight) {
    const pending = performAccessTokenRefresh();
    refreshInFlight = pending;
    pending.finally(() => {
      if (refreshInFlight === pending) {
        refreshInFlight = null;
      }
    });
  }
  return refreshInFlight;
}

/** Test/helper — whether a refresh is currently in flight. */
export function hasRefreshInFlight(): boolean {
  return refreshInFlight != null;
}
