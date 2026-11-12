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
import { unregisterDriverPushBestEffort } from '../push/pushRegistration';
import type { AuthIdentity, AuthUser } from '../../features/messaging/types';

export type AuthNoticeKind = 'session_expired' | 'info' | null;

interface AuthContextValue {
  ready: boolean;
  user: AuthUser | null;
  identity: AuthIdentity | null;
  isAuthenticated: boolean;
  roleOk: boolean;
  identityLinked: boolean;
  /** Operational entry: authenticated + driver role + linked employee. */
  canOperate: boolean;
  bootError: string | null;
  authNotice: string | null;
  authNoticeKind: AuthNoticeKind;
  error: string | null;
  login: (email: string, password: string) => Promise<void>;
  logout: () => Promise<void>;
  refreshIdentity: () => Promise<void>;
  retrySessionRestore: () => Promise<void>;
  dismissBootError: () => Promise<void>;
  clearAuthNotice: () => void;
}

const AuthContext = createContext<AuthContextValue | null>(null);

const SESSION_EXPIRED_MESSAGE = 'Your session expired. Please sign in again.';
const WRONG_ROLE_MESSAGE =
  'This app is for FleetX drivers only. Sign in with a linked driver account.';

async function loadSession(): Promise<{
  user: AuthUser;
  identity: AuthIdentity;
}> {
  const [user, identity] = await Promise.all([fetchMe(), fetchIdentity()]);
  return { user, identity };
}

function roleNameOf(user: AuthUser | null, identity: AuthIdentity | null): string | null {
  return user?.role?.name ?? identity?.role ?? null;
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

  const clearAuthenticatedState = useCallback((notice?: string, kind: AuthNoticeKind = 'session_expired') => {
    bumpAuthSessionEpoch();
    mobileRealtime.destroy();
    void unregisterDriverPushBestEffort();
    setUser(null);
    setIdentity(null);
    setError(null);
    setBootError(null);
    if (notice) {
      setAuthNoticeState(notice);
      setAuthNoticeKind(kind);
    }
  }, []);

  const applySession = useCallback(async () => {
    const session = await loadSession();
    const role = roleNameOf(session.user, session.identity);
    if (role !== AppConfig.appRole) {
      await clearLocalSession();
      mobileRealtime.destroy();
      setUser(null);
      setIdentity(null);
      throw new Error(WRONG_ROLE_MESSAGE);
    }
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

      const message = e instanceof Error ? e.message : '';
      if (message === WRONG_ROLE_MESSAGE) {
        await clearLocalSession();
        setAuthNoticeState(WRONG_ROLE_MESSAGE);
        setAuthNoticeKind('info');
        setBootError(null);
      } else if (isUnauthorizedAuthError(e)) {
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
        if (isUnauthorizedAuthError(e) || (e instanceof Error && e.message === WRONG_ROLE_MESSAGE)) {
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
      mobileRealtime.destroy();
      await unregisterDriverPushBestEffort();
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

  const roleName = roleNameOf(user, identity);
  const roleOk = roleName === AppConfig.appRole;
  const identityLinked = Boolean(identity?.linked);
  const canOperate = Boolean(user) && roleOk && identityLinked;

  const value = useMemo<AuthContextValue>(
    () => ({
      ready,
      user,
      identity,
      isAuthenticated: Boolean(user),
      roleOk,
      identityLinked,
      canOperate,
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
    }),
    [
      ready,
      user,
      identity,
      roleOk,
      identityLinked,
      canOperate,
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
