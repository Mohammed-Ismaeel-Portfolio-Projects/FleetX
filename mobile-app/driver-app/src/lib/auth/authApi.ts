import { apiClient } from '../api/client';
import { bumpAuthSessionEpoch } from './authRefresh';
import { clearAccessToken, getAccessToken, setAccessToken } from './tokenStorage';
import type { AuthIdentity, AuthUser } from '../../features/messaging/types';

interface ApiEnvelope<T> {
  success?: boolean;
  data: T;
  message?: string;
}

export async function login(
  email: string,
  password: string
): Promise<{ token: string; user: AuthUser }> {
  const { data } = await apiClient.post<
    ApiEnvelope<{ access_token: string; token_type?: string; user: AuthUser }>
  >('/auth/login', { email: email.trim(), password }, { skipAuthRefresh: true });

  const payload = data?.data;
  const token = payload?.access_token;
  if (!token || typeof token !== 'string') {
    throw new Error('Unexpected login response from server.');
  }
  if (!payload.user || typeof payload.user !== 'object') {
    throw new Error('Unexpected login response from server.');
  }

  await setAccessToken(token);
  return { token, user: payload.user };
}

export async function fetchMe(): Promise<AuthUser> {
  const { data } = await apiClient.get<ApiEnvelope<AuthUser>>('/auth/me');
  if (!data?.data || typeof data.data !== 'object') {
    throw new Error('Unexpected /auth/me response from server.');
  }
  return data.data;
}

export async function fetchIdentity(): Promise<AuthIdentity> {
  const { data } = await apiClient.get<ApiEnvelope<AuthIdentity>>('/auth/identity');
  if (!data?.data || typeof data.data !== 'object') {
    throw new Error('Unexpected /auth/identity response from server.');
  }
  return data.data;
}

export async function logout(): Promise<void> {
  const token = await getAccessToken();
  bumpAuthSessionEpoch();
  try {
    await apiClient.post('/auth/logout', undefined, {
      skipAuthRefresh: true,
      headers: token ? { Authorization: `Bearer ${token}` } : undefined,
    });
  } catch {
    // Token may already be invalid — still clear local session.
  }
  await clearAccessToken();
}

export async function hasStoredSession(): Promise<boolean> {
  const token = await getAccessToken();
  return Boolean(token);
}

export async function clearLocalSession(): Promise<void> {
  bumpAuthSessionEpoch();
  await clearAccessToken();
}
