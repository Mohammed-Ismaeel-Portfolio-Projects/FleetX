import axiosClient from './axios';
import { shouldSkipGlobalAuthRedirect, FLEETX_TOKEN_KEY, FLEETX_USER_KEY } from '../../auth/sessionLifecycle';

/**
 * Request Interceptor
 *
 * Adds JWT token to request headers from localStorage.
 */
axiosClient.interceptors.request.use(
  (config) => {
    const token = localStorage.getItem(FLEETX_TOKEN_KEY);
    if (token) {
      config.headers.Authorization = `Bearer ${token}`;
    }

    return config;
  },
  (error) => {
    return Promise.reject(error);
  }
);

/**
 * Response Interceptor
 *
 * Handles 401 Unauthorized by clearing auth state and redirecting to login.
 * Auth lifecycle endpoints (/auth/me, /auth/logout, /auth/login) are owned by
 * AuthProvider so a 401 cannot create a redirect loop during restore or logout.
 */
axiosClient.interceptors.response.use(
  (response) => {
    return response;
  },
  (error) => {
    const status = error.response?.status;
    const requestUrl = String(error.config?.url ?? '');

    if (status === 401 && !shouldSkipGlobalAuthRedirect(requestUrl)) {
      localStorage.removeItem(FLEETX_TOKEN_KEY);
      localStorage.removeItem(FLEETX_USER_KEY);
      window.location.href = '/login';
    }

    return Promise.reject(error);
  }
);

export default axiosClient;
