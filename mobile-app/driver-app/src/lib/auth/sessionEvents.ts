/**
 * Bridges API 401 responses → AuthContext without coupling Axios to React.
 */

export type SessionInvalidReason = 'unauthorized' | 'expired';

type SessionInvalidListener = (reason: SessionInvalidReason) => void;

let listener: SessionInvalidListener | null = null;

export function setSessionInvalidListener(next: SessionInvalidListener | null): void {
  listener = next;
}

export function emitSessionInvalid(reason: SessionInvalidReason = 'unauthorized'): void {
  listener?.(reason);
}
