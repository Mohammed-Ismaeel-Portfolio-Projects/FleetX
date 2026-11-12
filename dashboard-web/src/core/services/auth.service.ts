import { axiosClient } from '../api';
import { FLEETX_TOKEN_KEY } from '../auth/sessionLifecycle';

export interface LoginCredentials {
  email: string;
  password: string;
}

export interface AuthResponse {
  data: {
    user: {
      id: number;
      name: string;
      email: string;
      role: {
        id: number;
        name: string;
      };
      created_at?: string;
    };
    access_token: string;
    token_type: string;
    expires_in: number;
  };
}

export interface MeResponse {
  data: {
    id: number;
    name: string;
    email: string;
    role: {
      id: number;
      name: string;
    };
    created_at?: string;
  };
}

function bearerHeaders(): Record<string, string> {
  const token = localStorage.getItem(FLEETX_TOKEN_KEY);
  return token ? { Authorization: `Bearer ${token}` } : {};
}

export const authService = {
  async login(credentials: LoginCredentials): Promise<AuthResponse['data']> {
    const response = await axiosClient.post<AuthResponse>('/auth/login', credentials);
    return response.data.data;
  },

  async logout(): Promise<{ message: string }> {
    const response = await axiosClient.post<{ message: string }>(
      '/auth/logout',
      {},
      { headers: bearerHeaders() }
    );
    return response.data;
  },

  async me(): Promise<MeResponse['data']> {
    const response = await axiosClient.get<MeResponse>('/auth/me', {
      headers: bearerHeaders(),
    });
    return response.data.data;
  },

  async refresh(): Promise<AuthResponse['data']> {
    const response = await axiosClient.post<AuthResponse>(
      '/auth/refresh',
      {},
      { headers: bearerHeaders() }
    );
    return response.data.data;
  },
};
