/**
 * Driver auth API surface — wraps lib/auth/authApi only.
 */
export {
  login,
  logout,
  fetchMe,
  fetchIdentity,
  hasStoredSession,
  clearLocalSession,
} from '@/lib/auth/authApi';
