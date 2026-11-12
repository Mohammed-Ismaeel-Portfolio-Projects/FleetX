import Constants from 'expo-constants';

/**
 * Runtime config for FleetX Customer (Client) App.
 * Prefer EXPO_PUBLIC_* (Expo SDK 49+). Fallbacks suit local Laravel + Reverb on Expo Web.
 *
 * Native (Android emulator / device / iOS simulator): override via .env — see .env.example.
 * API and Reverb hosts must be reachable from the device (not localhost on physical hardware).
 *
 * Production (EXPO_PUBLIC_ENV=production):
 * - EXPO_PUBLIC_API_BASE_URL is required
 * - must be https://
 * - localhost / 127.0.0.1 are rejected
 */
const extra = (Constants.expoConfig?.extra ?? {}) as Record<string, string | undefined>;

/** Expo app version — real Client App version for About (C3.9). */
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
        'Production Client builds must not target localhost / 127.0.0.1. Set EXPO_PUBLIC_API_BASE_URL to the real HTTPS API.'
      );
    }
    if (!/^https:\/\//i.test(configured)) {
      throw new Error(
        'Production Client builds require an https:// EXPO_PUBLIC_API_BASE_URL.'
      );
    }
  }

  return url;
}

export const AppConfig = {
  apiBaseUrl: resolveApiBaseUrl(),
  realtimeEnabled:
    (process.env.EXPO_PUBLIC_REALTIME_ENABLED || extra.realtimeEnabled || 'false') === 'true',
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
  appRole: 'client' as const,
  /** Configured Expo app version (not a fabricated marketing build number). */
  appVersion,
  /** True when EXPO_PUBLIC_ENV=production was set at bundle time. */
  isProduction,
};
