/**
 * C5.6 — Client UX Consistency contract (customer-facing copy + retry rules).
 * Lightweight helpers only — no new UI framework.
 */

export const ClientUxCopy = {
  networkTitle: 'Connection problem',
  networkBody: 'Unable to reach FLeetX. Check your connection and try again.',
  /** Prefer for API-layer network mapping (keeps existing Request/Shipments wording). */
  networkBodyShort: 'Network error. Check your connection and try again.',
  accessTitle: 'Access unavailable',
  notFoundTitle: 'Unavailable',
  sessionExpired: 'Your session expired. Please sign in again.',
  serverFailure: 'FLeetX could not complete this operation. Please try again.',
  tooManyAttempts: 'Too many attempts. Please wait a moment and try again.',
  conflictRefresh: 'This was updated elsewhere. Refresh and try again.',
  retry: 'Retry',
} as const;

export type CustomerResourceKind =
  | 'shipment'
  | 'request'
  | 'notification'
  | 'conversation'
  | 'messages'
  | 'generic';

/**
 * Map HTTP / network failures to customer-safe copy.
 * Does not invent domain data — only presents failure semantics.
 */
export function customerHttpMessage(
  statusCode: number | undefined,
  opts: {
    isNetwork?: boolean;
    resource?: CustomerResourceKind;
    fallback: string;
  }
): string {
  if (opts.isNetwork) return ClientUxCopy.networkBodyShort;

  const resource = opts.resource ?? 'generic';

  if (statusCode === 401) return ClientUxCopy.sessionExpired;
  if (statusCode === 403) {
    switch (resource) {
      case 'shipment':
        return 'You do not have access to this shipment.';
      case 'request':
        return 'You do not have access to this request.';
      case 'notification':
        return 'You do not have access to these notifications.';
      case 'conversation':
      case 'messages':
        return 'You do not have access to this conversation.';
      default:
        return 'You do not have access to this information.';
    }
  }
  if (statusCode === 404) {
    switch (resource) {
      case 'shipment':
        return 'This shipment could not be found for your company.';
      case 'request':
        return 'This shipment request could not be found.';
      case 'notification':
        return 'This notification could not be found.';
      case 'conversation':
        return 'This conversation is unavailable.';
      case 'messages':
        return 'Support messages could not be found.';
      default:
        return 'The requested information could not be found.';
    }
  }
  if (statusCode === 409) return ClientUxCopy.conflictRefresh;
  if (statusCode === 429) return ClientUxCopy.tooManyAttempts;
  if (statusCode != null && statusCode >= 500) return ClientUxCopy.serverFailure;

  return opts.fallback;
}

/** Full-screen / section load failures: retry for network & server, not for 403/404. */
export function isRetryableLoadFailure(
  statusCode: number | undefined,
  isNetwork?: boolean
): boolean {
  if (isNetwork) return true;
  if (statusCode === 401 || statusCode === 403 || statusCode === 404) return false;
  return true;
}

export function screenErrorTitle(opts: {
  statusCode?: number;
  isNetwork?: boolean;
  loadFallbackTitle?: string;
}): string {
  if (opts.isNetwork) return ClientUxCopy.networkTitle;
  if (opts.statusCode === 403) return ClientUxCopy.accessTitle;
  if (opts.statusCode === 404) return ClientUxCopy.notFoundTitle;
  return opts.loadFallbackTitle ?? 'Could not load';
}

export type AxiosLikeError = {
  response?: { status?: number; data?: { message?: string } };
  message?: string;
  code?: string;
};

export function detectNetworkFailure(error: unknown): boolean {
  const err = error as AxiosLikeError;
  if (err?.response) return false;
  return (
    err?.code === 'ERR_NETWORK' ||
    err?.code === 'ECONNABORTED' ||
    err?.message === 'Network Error' ||
    (typeof err?.message === 'string' && err.message.toLowerCase().includes('timeout'))
  );
}
