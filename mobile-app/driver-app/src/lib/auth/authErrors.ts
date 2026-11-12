/**
 * Honest auth error messages for Driver App (D1).
 */

export function isNetworkAuthError(error: unknown): boolean {
  if (!error || typeof error !== 'object') return false;
  const e = error as { code?: string; message?: string; response?: unknown };
  if (e.response) return false;
  return (
    e.code === 'ERR_NETWORK' ||
    e.code === 'ECONNABORTED' ||
    e.message === 'Network Error' ||
    (typeof e.message === 'string' && e.message.toLowerCase().includes('timeout'))
  );
}

export function isUnauthorizedAuthError(error: unknown): boolean {
  if (!error || typeof error !== 'object') return false;
  const status = (error as { response?: { status?: number } }).response?.status;
  return status === 401;
}

function firstValidationMessage(data: unknown): string | null {
  if (!data || typeof data !== 'object') return null;
  const errors = (data as { errors?: Record<string, string[] | string> }).errors;
  if (!errors || typeof errors !== 'object') return null;
  for (const value of Object.values(errors)) {
    if (Array.isArray(value) && typeof value[0] === 'string' && value[0].trim()) {
      return value[0];
    }
    if (typeof value === 'string' && value.trim()) return value;
  }
  return null;
}

export function getAuthErrorMessage(error: unknown, fallback: string): string {
  if (isNetworkAuthError(error)) {
    return 'Network error. Check your connection and try again.';
  }

  if (!error || typeof error !== 'object') {
    return fallback;
  }

  const e = error as {
    response?: { status?: number; data?: { message?: string; errors?: unknown } };
    message?: string;
  };

  const status = e.response?.status;
  const data = e.response?.data;

  const validation = firstValidationMessage(data);
  if (validation) return validation;

  if (typeof data?.message === 'string' && data.message.trim()) {
    return data.message;
  }

  if (status === 401) {
    return 'Invalid credentials or your session has expired.';
  }

  if (status === 403) {
    return 'You do not have permission to perform this action.';
  }

  if (status === 404) {
    return 'The requested resource was not found.';
  }

  if (status && status >= 500) {
    return 'Server error. Please try again shortly.';
  }

  if (typeof e.message === 'string' && e.message.trim() && e.message !== 'Network Error') {
    if (!e.message.startsWith('Request failed')) return e.message;
  }

  return fallback;
}
