/** Geographic helpers for Client Request pickup/delivery coordinates. */

export interface GeoCoordinate {
  latitude: number;
  longitude: number;
}

export function isValidLatitude(value: number | null | undefined): value is number {
  return typeof value === 'number' && Number.isFinite(value) && value >= -90 && value <= 90;
}

export function isValidLongitude(value: number | null | undefined): value is number {
  return typeof value === 'number' && Number.isFinite(value) && value >= -180 && value <= 180;
}

export function hasCoordinatePair(
  latitude: number | null | undefined,
  longitude: number | null | undefined
): boolean {
  return isValidLatitude(latitude) && isValidLongitude(longitude);
}

export function asCoordinate(
  latitude: number | null | undefined,
  longitude: number | null | undefined
): GeoCoordinate | null {
  if (!isValidLatitude(latitude) || !isValidLongitude(longitude)) return null;
  return { latitude, longitude };
}

/** Zoomed-out world view used only as an unselected map start. Never treated as a chosen stop. */
export const UNSELECTED_MAP_REGION = {
  latitude: 20,
  longitude: 0,
  latitudeDelta: 80,
  longitudeDelta: 80,
};

export function regionForCoordinate(coordinate: GeoCoordinate, delta = 0.04) {
  return {
    latitude: coordinate.latitude,
    longitude: coordinate.longitude,
    latitudeDelta: delta,
    longitudeDelta: delta,
  };
}

export function selectedLocationSummary(
  hasCoordinates: boolean,
  mapLabel: string,
  city: string,
  country: string
): string | null {
  if (!hasCoordinates) return null;
  const trimmedLabel = mapLabel.trim();
  if (trimmedLabel && trimmedLabel.toLowerCase() !== 'location selected') {
    return trimmedLabel;
  }
  const place = [city, country].map((part) => part.trim()).filter(Boolean).join(', ');
  return place || 'Location selected';
}
