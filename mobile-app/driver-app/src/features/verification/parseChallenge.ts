/**
 * Parse opaque QR challenge from scanned payload (D5).
 * Accepts UUID or fleetx://v1/verify?c=<uuid>
 */

const UUID_RE =
  /[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}/i;

export function parseVerificationChallenge(raw: string | null | undefined): string | null {
  if (raw == null) return null;
  const trimmed = String(raw).trim();
  if (!trimmed) return null;

  if (UUID_RE.test(trimmed) && trimmed.length <= 40) {
    const only = trimmed.match(UUID_RE);
    if (only && only[0].length === trimmed.length) return only[0].toLowerCase();
  }

  const fromQuery = trimmed.match(/[?&]c=([0-9a-f-]{36})/i);
  if (fromQuery?.[1]) return fromQuery[1].toLowerCase();

  const any = trimmed.match(UUID_RE);
  return any ? any[0].toLowerCase() : null;
}
