import { useState, useEffect, useCallback } from 'react';
import type { ReactNode } from 'react';
import { isAxiosError } from 'axios';
import { authService } from '../services/auth.service';
import { AuthContext } from './AuthContext';
import type { User } from './AuthContext';
import {
  classifyAuthRequestFailure,
  clearDashboardSession,
  mapMeToDashboardUser,
  persistDashboardSession,
  readStoredToken,
} from './sessionLifecycle';

interface AuthProviderProps {
  children: ReactNode;
}

export function AuthProvider({ children }: AuthProviderProps) {
  const [user, setUser] = useState<User | null>(null);
  const [token, setToken] = useState<string | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [sessionError, setSessionError] = useState<string | null>(null);

  const applyUnauthenticated = useCallback(() => {
    clearDashboardSession(localStorage);
    setUser(null);
    setToken(null);
    setSessionError(null);
  }, []);

  const restoreSession = useCallback(async () => {
    setIsLoading(true);
    setSessionError(null);
    setUser(null);

    const storedToken = readStoredToken(localStorage);
    if (!storedToken) {
      setToken(null);
      setIsLoading(false);
      return;
    }

    try {
      const me = await authService.me();
      const nextUser = mapMeToDashboardUser(me as Parameters<typeof mapMeToDashboardUser>[0]);
      if (!nextUser) {
        applyUnauthenticated();
        setIsLoading(false);
        return;
      }

      persistDashboardSession(localStorage, nextUser, storedToken);
      setUser(nextUser);
      setToken(storedToken);
      setSessionError(null);
    } catch (error) {
      const status = isAxiosError(error) ? error.response?.status : undefined;
      if (classifyAuthRequestFailure(status) === 'unauthorized') {
        applyUnauthenticated();
      } else {
        setToken(storedToken);
        setUser(null);
        setSessionError('Unable to verify your session. Check your connection and try again.');
      }
    } finally {
      setIsLoading(false);
    }
  }, [applyUnauthenticated]);

  useEffect(() => {
    void restoreSession();
  }, [restoreSession]);

  const login = (newUser: User, newToken: string) => {
    const nextUser =
      mapMeToDashboardUser(newUser as Parameters<typeof mapMeToDashboardUser>[0]) ?? newUser;
    persistDashboardSession(localStorage, nextUser, newToken);
    setUser(nextUser);
    setToken(newToken);
    setSessionError(null);
  };

  const logout = async () => {
    try {
      await authService.logout();
    } catch {
      // 401/expired and transport failures still clear the local session.
    } finally {
      applyUnauthenticated();
    }
  };

  return (
    <AuthContext.Provider
      value={{
        user,
        token,
        login,
        logout,
        isLoading,
        sessionError,
        retrySession: restoreSession,
      }}
    >
      {children}
    </AuthContext.Provider>
  );
}
