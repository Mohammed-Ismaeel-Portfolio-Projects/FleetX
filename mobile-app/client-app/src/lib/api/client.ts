import axios, {
  type AxiosError,
  type AxiosInstance,
  type AxiosRequestConfig,
  type InternalAxiosRequestConfig,
} from 'axios';
import { AppConfig } from '../config';
import { clearAccessToken, getAccessToken } from '../auth/tokenStorage';
import {
  bumpAuthSessionEpoch,
  isAuthRefreshExcludedUrl,
  refreshAccessTokenSingleFlight,
} from '../auth/authRefresh';
import { emitSessionInvalid } from '../auth/sessionEvents';

declare module 'axios' {
  export interface AxiosRequestConfig {
    /** When true, 401 must not attempt JWT refresh (login/register/logout/etc.). */
    skipAuthRefresh?: boolean;
    /** Internal: this request is already the post-refresh retry. */
    _authRetry?: boolean;
  }
}

export const apiClient: AxiosInstance = axios.create({
  baseURL: AppConfig.apiBaseUrl,
  timeout: 30000,
  headers: {
    Accept: 'application/json',
    'Content-Type': 'application/json',
  },
});

apiClient.interceptors.request.use(async (config) => {
  const token = await getAccessToken();
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

function shouldAttemptRefresh(config: InternalAxiosRequestConfig | undefined): boolean {
  if (!config) return false;
  if (config.skipAuthRefresh) return false;
  if (config._authRetry) return false;
  if (isAuthRefreshExcludedUrl(config.url)) return false;
  return true;
}

async function failClosedSession(error: AxiosError): Promise<never> {
  bumpAuthSessionEpoch();
  await clearAccessToken();
  emitSessionInvalid('unauthorized');
  return Promise.reject(error);
}

apiClient.interceptors.response.use(
  (response) => response,
  async (error: AxiosError) => {
    const status = error.response?.status;
    const original = error.config;

    if (status !== 401) {
      return Promise.reject(error);
    }

    // No config / already retried / excluded → fail closed (no refresh loop).
    if (!original || !shouldAttemptRefresh(original)) {
      return failClosedSession(error);
    }

    // No stored token → nothing to refresh.
    const existing = await getAccessToken();
    if (!existing) {
      return failClosedSession(error);
    }

    try {
      const refreshed = await refreshAccessTokenSingleFlight();
      const retryConfig: AxiosRequestConfig = {
        ...original,
        _authRetry: true,
        headers: {
          ...original.headers,
          Authorization: `Bearer ${refreshed.accessToken}`,
        },
      };
      return apiClient.request(retryConfig);
    } catch {
      return failClosedSession(error);
    }
  }
);
