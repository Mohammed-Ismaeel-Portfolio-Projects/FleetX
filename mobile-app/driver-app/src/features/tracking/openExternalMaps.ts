/**
 * External maps deep-link helper (MAP-03).
 * Opens OS navigation apps — no turn-by-turn engine inside FleetX.
 */

import { Linking, Platform } from 'react-native';

export type ExternalMapsTarget = {
  latitude: number;
  longitude: number;
  label?: string | null;
};

function usable(lat: number, lng: number) {
  return (
    Number.isFinite(lat) &&
    Number.isFinite(lng) &&
    lat >= -90 &&
    lat <= 90 &&
    lng >= -180 &&
    lng <= 180 &&
    !(Math.abs(lat) < 0.00001 && Math.abs(lng) < 0.00001)
  );
}

export function buildExternalMapsUrls(target: ExternalMapsTarget): string[] {
  if (!usable(target.latitude, target.longitude)) return [];
  const { latitude, longitude } = target;
  const label = encodeURIComponent((target.label || 'Destination').trim() || 'Destination');

  if (Platform.OS === 'ios') {
    return [
      `http://maps.apple.com/?daddr=${latitude},${longitude}&q=${label}&dirflg=d`,
      `https://www.google.com/maps/dir/?api=1&destination=${latitude},${longitude}`,
    ];
  }
  if (Platform.OS === 'android') {
    return [
      `google.navigation:q=${latitude},${longitude}`,
      `geo:${latitude},${longitude}?q=${latitude},${longitude}(${label})`,
      `https://www.google.com/maps/dir/?api=1&destination=${latitude},${longitude}`,
    ];
  }
  return [`https://www.google.com/maps/dir/?api=1&destination=${latitude},${longitude}`];
}

/**
 * Try platform intents / URLs in order. Returns false if none open.
 */
export async function openExternalMaps(target: ExternalMapsTarget): Promise<boolean> {
  const urls = buildExternalMapsUrls(target);
  if (!urls.length) return false;

  for (const url of urls) {
    try {
      const can = await Linking.canOpenURL(url).catch(() => false);
      if (!can && !url.startsWith('https://') && !url.startsWith('http://')) {
        continue;
      }
      await Linking.openURL(url);
      return true;
    } catch {
      /* try next */
    }
  }
  return false;
}
