/**
 * Clean query parameters by removing undefined, null, and empty string values.
 * This prevents sending invalid parameters to the backend.
 */
export function cleanParams(params: Record<string, any>): Record<string, any> {
  const cleaned: Record<string, any> = {};
  for (const [key, value] of Object.entries(params)) {
    if (value !== undefined && value !== null && value !== '') {
      cleaned[key] = value;
    }
  }
  return cleaned;
}
