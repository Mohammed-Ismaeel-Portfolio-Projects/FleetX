import Constants from 'expo-constants';

/**
 * Runtime config for FleetX Driver App (D1).
 *
 * Production (EXPO_PUBLIC_ENV=production):
 * - EXPO_PUBLIC_API_BASE_URL is required
 * - must be https://
 * - localhost / 127.0.0.1 are rejected
 */
const extra = (Constants.expoConfig?.extra ?? {}) as Record<string, string | undefined>;

const appVersion =
  (typeof Constants.expoConfig?.version === 'string' && Constants.expoConfig.version.trim()) ||
  '1.0.0';

const isProduction = process.env.EXPO_PUBLIC_ENV === 'production';

function resolveApiBaseUrl(): string {
  const configured =
    (process.env.EXPO_PUBLIC_API_BASE_URL || extra.apiBaseUrl || '').trim() || null;
  const fallback = 'http://localhost:8000/api/v1';
  const url = configured || fallback;

  if (isProduction) {
    if (!configured) {
      throw new Error(
        'EXPO_PUBLIC_API_BASE_URL is required when EXPO_PUBLIC_ENV=production.'
      );
    }
    if (/localhost|127\.0\.0\.1/i.test(configured)) {
      throw new Error(
        'Production Driver builds must not target localhost / 127.0.0.1. Set EXPO_PUBLIC_API_BASE_URL to the real HTTPS API.'
      );
    }
    if (!/^https:\/\//i.test(configured)) {
      throw new Error(
        'Production Driver builds require an https:// EXPO_PUBLIC_API_BASE_URL.'
      );
    }
  }

  return url;
}

function resolveRealtimeEnabled(): boolean {
  const requested =
    (process.env.EXPO_PUBLIC_REALTIME_ENABLED || extra.realtimeEnabled || 'false') === 'true';
  if (isProduction) {
    if (requested) {
      throw new Error(
        'Production Driver builds must keep EXPO_PUBLIC_REALTIME_ENABLED=false. Realtime is not deployed for the current Driver release.'
      );
    }
    return false;
  }
  return requested;
}

export const AppConfig = {
  apiBaseUrl: resolveApiBaseUrl(),
  realtimeEnabled: resolveRealtimeEnabled(),
  reverbKey:
    process.env.EXPO_PUBLIC_REVERB_APP_KEY ||
    extra.reverbKey ||
    'fleetx-reverb-key',
  reverbHost:
    process.env.EXPO_PUBLIC_REVERB_HOST || extra.reverbHost || 'localhost',
  reverbPort: Number(
    process.env.EXPO_PUBLIC_REVERB_PORT || extra.reverbPort || 8080
  ),
  reverbScheme:
    process.env.EXPO_PUBLIC_REVERB_SCHEME || extra.reverbScheme || 'http',
  appRole: 'driver' as const,
  appVersion,
  isProduction,
  easProjectId: (
    process.env.EXPO_PUBLIC_EAS_PROJECT_ID ||
    extra.easProjectId ||
    ''
  ).trim(),
};
