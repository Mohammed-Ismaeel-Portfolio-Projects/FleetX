import React, {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useRef,
  useState,
} from 'react';
import {
  clearLocalSession,
  fetchIdentity,
  fetchMe,
  hasStoredSession,
  login as apiLogin,
  logout as apiLogout,
} from './authApi';
import {
  getAuthErrorMessage,
  isNetworkAuthError,
  isUnauthorizedAuthError,
} from './authErrors';
import { bumpAuthSessionEpoch } from './authRefresh';
import { setSessionInvalidListener } from './sessionEvents';
import { AppConfig } from '../config';
import { mobileRealtime } from '../../features/messaging/realtime';
import type { AuthIdentity, AuthUser } from '../../features/messaging/types';

export type AuthNoticeKind = 'session_expired' | 'registered' | 'info' | null;

interface AuthContextValue {
  ready: boolean;
  user: AuthUser | null;
  identity: AuthIdentity | null;
  isAuthenticated: boolean;
  roleOk: boolean;
  identityLinked: boolean;
  /** Transient restore failure when a token exists but me/identity could not load (e.g. network). */
  bootError: string | null;
  /** Honest notice shown on Login (session expired, post-register, etc.). */
  authNotice: string | null;
  authNoticeKind: AuthNoticeKind;
  error: string | null;
  login: (email: string, password: string) => Promise<void>;
  logout: () => Promise<void>;
  refreshIdentity: () => Promise<void>;
  retrySessionRestore: () => Promise<void>;
  /** Clears a failed restore so the Login form is usable (drops local token). */
  dismissBootError: () => Promise<void>;
  clearAuthNotice: () => void;
  setAuthNotice: (message: string, kind?: AuthNoticeKind) => void;
}

const AuthContext = createContext<AuthContextValue | null>(null);

const SESSION_EXPIRED_MESSAGE = 'Your session expired. Please sign in again.';

async function loadSession(): Promise<{
  user: AuthUser;
  identity: AuthIdentity;
}> {
  const [user, identity] = await Promise.all([fetchMe(), fetchIdentity()]);
  return { user, identity };
}

export function AuthProvider({ children }: { children: React.ReactNode }) {
  const [ready, setReady] = useState(false);
  const [user, setUser] = useState<AuthUser | null>(null);
  const [identity, setIdentity] = useState<AuthIdentity | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [bootError, setBootError] = useState<string | null>(null);
  const [authNotice, setAuthNoticeState] = useState<string | null>(null);
  const [authNoticeKind, setAuthNoticeKind] = useState<AuthNoticeKind>(null);
  const loginInFlight = useRef(false);
  const logoutInFlight = useRef(false);
  const restoring = useRef(false);

  const clearAuthNotice = useCallback(() => {
    setAuthNoticeState(null);
    setAuthNoticeKind(null);
  }, []);

  const setAuthNotice = useCallback((message: string, kind: AuthNoticeKind = 'info') => {
    setAuthNoticeState(message);
    setAuthNoticeKind(kind);
  }, []);

  const clearAuthenticatedState = useCallback(
    (notice?: string, kind: AuthNoticeKind = 'session_expired') => {
      // Invalidate any in-flight JWT refresh so it cannot restore this session.
      bumpAuthSessionEpoch();
      mobileRealtime.destroy();
      setUser(null);
      setIdentity(null);
      setError(null);
      setBootError(null);
      if (notice) {
        setAuthNoticeState(notice);
        setAuthNoticeKind(kind);
      }
    },
    []
  );

  const applySession = useCallback(async () => {
    const session = await loadSession();
    setUser(session.user);
    setIdentity(session.identity);
    setError(null);
    setBootError(null);
  }, []);

  const restoreSession = useCallback(async () => {
    if (restoring.current) return;
    restoring.current = true;
    setBootError(null);
    try {
      if (!(await hasStoredSession())) {
        setUser(null);
        setIdentity(null);
        return;
      }
      await applySession();
    } catch (e) {
      mobileRealtime.destroy();
      setUser(null);
      setIdentity(null);

      if (isUnauthorizedAuthError(e)) {
        await clearLocalSession();
        setAuthNoticeState(SESSION_EXPIRED_MESSAGE);
        setAuthNoticeKind('session_expired');
        setBootError(null);
      } else if (await hasStoredSession()) {
        setBootError(
          getAuthErrorMessage(
            e,
            isNetworkAuthError(e)
              ? 'Could not restore your session. Check your connection and retry.'
              : 'Could not restore your session. Please retry.'
          )
        );
      } else {
        setAuthNoticeState(SESSION_EXPIRED_MESSAGE);
        setAuthNoticeKind('session_expired');
      }
    } finally {
      restoring.current = false;
    }
  }, [applySession]);

  useEffect(() => {
    let cancelled = false;

    // Register before restore so mid-boot 401s clear React state, not only the token.
    setSessionInvalidListener(() => {
      clearAuthenticatedState(SESSION_EXPIRED_MESSAGE, 'session_expired');
    });

    (async () => {
      await restoreSession();
      if (!cancelled) setReady(true);
    })();

    return () => {
      cancelled = true;
      setSessionInvalidListener(null);
    };
  }, [restoreSession, clearAuthenticatedState]);

  const retrySessionRestore = useCallback(async () => {
    setReady(false);
    await restoreSession();
    setReady(true);
  }, [restoreSession]);

  const dismissBootError = useCallback(async () => {
    mobileRealtime.destroy();
    await clearLocalSession();
    setUser(null);
    setIdentity(null);
    setBootError(null);
    setError(null);
  }, []);

  const login = useCallback(
    async (email: string, password: string) => {
      if (loginInFlight.current) return;
      loginInFlight.current = true;
      setError(null);
      try {
        await apiLogin(email, password);
        await applySession();
        clearAuthNotice();
      } catch (e) {
        mobileRealtime.destroy();
        setUser(null);
        setIdentity(null);
        if (isUnauthorizedAuthError(e)) {
          await clearLocalSession();
        }
        throw e;
      } finally {
        loginInFlight.current = false;
      }
    },
    [applySession, clearAuthNotice]
  );

  const logout = useCallback(async () => {
    if (logoutInFlight.current) return;
    logoutInFlight.current = true;
    try {
      // Epoch bump happens inside apiLogout before the network call.
      mobileRealtime.destroy();
      await apiLogout();
      setUser(null);
      setIdentity(null);
      setError(null);
      setBootError(null);
      clearAuthNotice();
    } finally {
      logoutInFlight.current = false;
    }
  }, [clearAuthNotice]);

  const refreshIdentity = useCallback(async () => {
    const identityData = await fetchIdentity();
    setIdentity(identityData);
  }, []);

  const roleName = user?.role?.name ?? identity?.role ?? null;
  const roleOk = roleName === AppConfig.appRole;
  const identityLinked = Boolean(identity?.linked);

  const value = useMemo<AuthContextValue>(
    () => ({
      ready,
      user,
      identity,
      isAuthenticated: Boolean(user),
      roleOk,
      identityLinked,
      bootError,
      authNotice,
      authNoticeKind,
      error,
      login,
      logout,
      refreshIdentity,
      retrySessionRestore,
      dismissBootError,
      clearAuthNotice,
      setAuthNotice,
    }),
    [
      ready,
      user,
      identity,
      roleOk,
      identityLinked,
      bootError,
      authNotice,
      authNoticeKind,
      error,
      login,
      logout,
      refreshIdentity,
      retrySessionRestore,
      dismissBootError,
      clearAuthNotice,
      setAuthNotice,
    ]
  );

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth(): AuthContextValue {
  const ctx = useContext(AuthContext);
  if (!ctx) {
    throw new Error('useAuth must be used within AuthProvider');
  }
  return ctx;
}
