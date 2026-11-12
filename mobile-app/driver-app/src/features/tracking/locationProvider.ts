/**
 * Testable location provider abstraction (D6).
 */

import type { DeviceFix } from '@/features/driverApi/tripTypes';

export type LocationPermissionState =
  | 'unknown'
  | 'requesting'
  | 'granted'
  | 'denied'
  | 'blocked';

export type LocationSubscription = {
  remove: () => void;
};

export type LocationProvider = {
  getPermissionState: () => Promise<LocationPermissionState>;
  requestPermission: () => Promise<LocationPermissionState>;
  watchPosition: (
    onFix: (fix: DeviceFix) => void,
    onError?: (message: string) => void
  ) => Promise<LocationSubscription>;
};

function toFix(coords: {
  latitude: number;
  longitude: number;
  accuracy: number | null;
  speed: number | null;
  heading: number | null;
  timestamp?: number;
}): DeviceFix | null {
  if (!Number.isFinite(coords.latitude) || !Number.isFinite(coords.longitude)) return null;
  if (Math.abs(coords.latitude) < 0.00001 && Math.abs(coords.longitude) < 0.00001) return null;
  return {
    latitude: coords.latitude,
    longitude: coords.longitude,
    accuracy: coords.accuracy,
    speed: coords.speed != null && coords.speed >= 0 ? coords.speed * 3.6 : coords.speed, // m/s → km/h
    heading:
      coords.heading != null && coords.heading >= 0 && coords.heading <= 360
        ? coords.heading
        : null,
    recordedAt: new Date(coords.timestamp ?? Date.now()).toISOString(),
  };
}

/** Real Expo Location implementation. */
export function createExpoLocationProvider(): LocationProvider {
  // Lazy require so static tests can mock without loading native module.
  return {
    async getPermissionState() {
      const Location = await import('expo-location');
      const cur = await Location.getForegroundPermissionsAsync();
      if (cur.granted) return 'granted';
      if (!cur.canAskAgain) return 'blocked';
      if (cur.status === 'denied') return 'denied';
      return 'unknown';
    },
    async requestPermission() {
      const Location = await import('expo-location');
      const res = await Location.requestForegroundPermissionsAsync();
      if (res.granted) return 'granted';
      if (!res.canAskAgain) return 'blocked';
      return 'denied';
    },
    async watchPosition(onFix, onError) {
      const Location = await import('expo-location');
      const sub = await Location.watchPositionAsync(
        {
          accuracy: Location.Accuracy.Balanced,
          timeInterval: 5000,
          distanceInterval: 25,
        },
        (pos) => {
          const fix = toFix({
            latitude: pos.coords.latitude,
            longitude: pos.coords.longitude,
            accuracy: pos.coords.accuracy,
            speed: pos.coords.speed,
            heading: pos.coords.heading,
            timestamp: pos.timestamp,
          });
          if (fix) onFix(fix);
        }
      );
      return {
        remove: () => {
          try {
            sub.remove();
          } catch {
            /* ignore */
          }
        },
      };
    },
  };
}
