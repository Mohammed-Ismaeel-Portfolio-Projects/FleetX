/**
 * Alerts Center deep-link query key on `/dispatcher/notifications`.
 * BrowserRouter supports search params on the existing route — no `/notifications/:id`.
 */
export const ALERTS_ISSUE_QUERY_KEY = 'issue';

/**
 * Parse `?issue=` into a canonical Issue.id string.
 * Accepts positive integer IDs only — invalid/missing → null (normal Alerts behavior).
 */
export function parseIssueIdQueryParam(raw: string | null | undefined): string | null {
  if (raw == null) return null;
  const text = String(raw).trim();
  if (!text) return null;
  if (!/^\d+$/.test(text)) return null;
  const n = Number(text);
  if (!Number.isFinite(n) || n <= 0) return null;
  return String(n);
}

/** Build Alerts Center href that selects a real Issue.id via search param. */
export function alertsCenterIssueHref(issueId: number | string): string {
  const id = parseIssueIdQueryParam(String(issueId));
  if (!id) return '/dispatcher/notifications';
  return `/dispatcher/notifications?${ALERTS_ISSUE_QUERY_KEY}=${encodeURIComponent(id)}`;
}
