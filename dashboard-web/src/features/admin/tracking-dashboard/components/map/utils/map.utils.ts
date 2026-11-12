import type { LatLngBounds } from 'leaflet';

export interface MapBounds {
  north: number;
  south: number;
  east: number;
  west: number;
}

export function calculateBounds(
  vehicles: Array<{ latitude?: number; longitude?: number }>
): MapBounds | null {
  if (vehicles.length === 0) return null;

  let minLat = Infinity;
  let maxLat = -Infinity;
  let minLng = Infinity;
  let maxLng = -Infinity;

  for (const vehicle of vehicles) {
    if (vehicle.latitude && vehicle.longitude) {
      minLat = Math.min(minLat, vehicle.latitude);
      maxLat = Math.max(maxLat, vehicle.latitude);
      minLng = Math.min(minLng, vehicle.longitude);
      maxLng = Math.max(maxLng, vehicle.longitude);
    }
  }

  if (minLat === Infinity) return null;

  return {
    north: maxLat,
    south: minLat,
    east: maxLng,
    west: minLng,
  };
}

export function boundsToLeaflet(bounds: MapBounds): LatLngBounds {
  return [
    [bounds.south, bounds.west],
    [bounds.north, bounds.east],
  ] as LatLngBounds;
}

export function getMarkerColor(
  status: 'moving' | 'idle' | 'offline',
  isSelected: boolean,
  hasAlert: boolean
): string {
  if (isSelected) return '#0250C5';
  if (hasAlert) return '#F97316';
  
  switch (status) {
    case 'moving':
      return '#22C55E';
    case 'idle':
      return '#EAB308';
    case 'offline':
      return '#EF4444';
    default:
      return '#64748B';
  }
}

export function formatCoordinates(lat: number, lng: number): string {
  return `${lat.toFixed(6)}, ${lng.toFixed(6)}`;
}

export function formatSpeed(speed: number): string {
  return `${speed.toFixed(1)} km/h`;
}

export function formatHeading(heading: number): string {
  return `${heading.toFixed(1)}°`;
}
