import type {
  ClientGeoCoordinates,
  ClientLocationFreshness,
  ClientLocationFreshnessState,
  ClientShipmentTracking,
} from './types';

export type TrackingMapMarkerKind = 'origin' | 'destination' | 'lastKnown';

/** Deliberate geo presentation modes for C5.5-C4.1 empty/partial states. */
export type TrackingMapGeoMode = 'full' | 'hubs' | 'locationOnly' | 'none';

export interface TrackingMapMarker {
  kind: TrackingMapMarkerKind;
  latitude: number;
  longitude: number;
  /** Short legend label */
  label: string;
  /** Screen-reader label */
  accessibilityLabel: string;
}

export interface TrackingMapModel {
  markers: TrackingMapMarker[];
  routeCoordinates: ClientGeoCoordinates[];
  hasGeographicData: boolean;
  geoMode: TrackingMapGeoMode;
  locationFreshness: ClientLocationFreshness;
}

export interface ProjectedPoint {
  x: number;
  y: number;
}

export interface ProjectedTrackingMap {
  markers: Array<TrackingMapMarker & ProjectedPoint>;
  route: ProjectedPoint[];
}

function isFiniteCoordinate(value: unknown): value is number {
  return typeof value === 'number' && Number.isFinite(value);
}

/** UI-resilience filter — does not invent points; drops non-finite / out-of-range values. */
export function isUsableGeoPoint(latitude: unknown, longitude: unknown): boolean {
  if (!isFiniteCoordinate(latitude) || !isFiniteCoordinate(longitude)) {
    return false;
  }
  return latitude >= -90 && latitude <= 90 && longitude >= -180 && longitude <= 180;
}

/**
 * Customer-facing freshness copy — mirrors backend state; does not recalculate age.
 */
export function locationFreshnessLabel(state: ClientLocationFreshnessState): string {
  switch (state) {
    case 'fresh':
      return 'Location updated recently';
    case 'stale':
      return 'Last location update was some time ago';
    case 'very_stale':
      return 'Location update may be outdated';
    case 'unavailable':
    default:
      return 'Location unavailable';
  }
}

export function locationFreshnessAccessibilityLabel(
  state: ClientLocationFreshnessState
): string {
  return `Location freshness: ${state}`;
}

export function locationFreshnessTone(
  state: ClientLocationFreshnessState
): 'neutral' | 'ok' | 'caution' | 'warning' {
  switch (state) {
    case 'fresh':
      return 'ok';
    case 'stale':
      return 'caution';
    case 'very_stale':
      return 'warning';
    case 'unavailable':
    default:
      return 'neutral';
  }
}

function sanitizeRouteCoordinates(
  coordinates: ClientGeoCoordinates[] | undefined | null
): ClientGeoCoordinates[] {
  if (!Array.isArray(coordinates) || coordinates.length < 2) {
    return [];
  }

  const usable: ClientGeoCoordinates[] = [];
  for (const point of coordinates) {
    if (!point || !isUsableGeoPoint(point.latitude, point.longitude)) {
      continue;
    }
    usable.push({
      latitude: point.latitude,
      longitude: point.longitude,
    });
  }

  return usable.length >= 2 ? usable : [];
}

/**
 * Build map model from C5.5-C3 domain tracking only.
 * Does not invent coordinates, route, or freshness.
 */
export function buildTrackingMapModel(tracking: ClientShipmentTracking): TrackingMapModel {
  const markers: TrackingMapMarker[] = [];

  const origin = tracking.origin?.coordinates;
  if (origin && isUsableGeoPoint(origin.latitude, origin.longitude)) {
    markers.push({
      kind: 'origin',
      latitude: origin.latitude,
      longitude: origin.longitude,
      label: 'Pickup',
      accessibilityLabel: 'Pickup location',
    });
  }

  const destination = tracking.destination?.coordinates;
  if (destination && isUsableGeoPoint(destination.latitude, destination.longitude)) {
    markers.push({
      kind: 'destination',
      latitude: destination.latitude,
      longitude: destination.longitude,
      label: 'Delivery',
      accessibilityLabel: 'Delivery location',
    });
  }

  const lastKnown = tracking.lastKnownPosition;
  if (lastKnown && isUsableGeoPoint(lastKnown.latitude, lastKnown.longitude)) {
    markers.push({
      kind: 'lastKnown',
      latitude: lastKnown.latitude,
      longitude: lastKnown.longitude,
      label: 'Last known',
      accessibilityLabel: 'Last known shipment location',
    });
  }

  const routeCoordinates = sanitizeRouteCoordinates(tracking.route?.coordinates ?? null);

  const hasOriginOrDestination = markers.some(
    (m) => m.kind === 'origin' || m.kind === 'destination'
  );
  const hasLastKnown = markers.some((m) => m.kind === 'lastKnown');

  let geoMode: TrackingMapGeoMode = 'none';
  if (routeCoordinates.length >= 2) {
    geoMode = 'full';
  } else if (hasOriginOrDestination) {
    geoMode = 'hubs';
  } else if (hasLastKnown) {
    geoMode = 'locationOnly';
  }

  return {
    markers,
    routeCoordinates,
    hasGeographicData: geoMode !== 'none',
    geoMode,
    locationFreshness: tracking.locationFreshness,
  };
}

/**
 * Concise secondary headline for the map card (C5.5-C4.2).
 */
export function trackingMapModeSummary(geoMode: TrackingMapGeoMode): string {
  switch (geoMode) {
    case 'full':
      return 'Planned route and latest known location';
    case 'hubs':
      return 'Pickup and delivery locations';
    case 'locationOnly':
      return 'Latest known shipment location';
    case 'none':
    default:
      return 'Location details are currently unavailable';
  }
}

/**
 * Supporting explanation of the current geo presentation mode.
 */
export function trackingMapModeHint(geoMode: TrackingMapGeoMode): string {
  switch (geoMode) {
    case 'full':
      return 'Hubs and planned route are shown. This is a snapshot overview, not continuous movement.';
    case 'hubs':
      return 'Route detail is limited. A planned route is not available yet.';
    case 'locationOnly':
      return 'Pickup and delivery geometry is unavailable for this shipment.';
    case 'none':
    default:
      return 'Geographic details will appear here when they become available.';
  }
}

/**
 * Customer-facing planned delivery date (YYYY-MM-DD → "18 Aug 2026").
 * Does not invent timing or calculate arrival.
 */
export function formatPlannedDeliveryLabel(value: string | null | undefined): string | null {
  if (value == null) return null;
  const trimmed = String(value).trim();
  if (!trimmed) return null;

  const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(trimmed);
  if (!match) return trimmed;

  const year = Number(match[1]);
  const month = Number(match[2]);
  const day = Number(match[3]);
  if (!Number.isFinite(year) || !Number.isFinite(month) || !Number.isFinite(day)) {
    return trimmed;
  }

  const date = new Date(Date.UTC(year, month - 1, day));
  if (Number.isNaN(date.getTime())) return trimmed;

  return date.toLocaleDateString('en-GB', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
    timeZone: 'UTC',
  });
}

/** Cap rendered route segments for large polylines (keeps UI lightweight). */
const MAX_RENDERED_ROUTE_POINTS = 64;

function downsampleRoute(points: ClientGeoCoordinates[]): ClientGeoCoordinates[] {
  if (points.length <= MAX_RENDERED_ROUTE_POINTS) {
    return points;
  }

  const result: ClientGeoCoordinates[] = [];
  const lastIndex = points.length - 1;
  const step = lastIndex / (MAX_RENDERED_ROUTE_POINTS - 1);

  for (let i = 0; i < MAX_RENDERED_ROUTE_POINTS; i += 1) {
    const index = i === MAX_RENDERED_ROUTE_POINTS - 1 ? lastIndex : Math.round(i * step);
    result.push(points[index]);
  }

  return result;
}

/**
 * Fit-to-bounds projection for the schematic map canvas.
 * Never invents geometry. Degenerate bounds use a safe minimum span.
 */
export function projectTrackingMap(
  model: TrackingMapModel,
  width: number,
  height: number,
  padding = 28
): ProjectedTrackingMap | null {
  if (!Number.isFinite(width) || !Number.isFinite(height) || width <= 0 || height <= 0) {
    return null;
  }
  if (!model.hasGeographicData) {
    return null;
  }

  const routeForRender = downsampleRoute(model.routeCoordinates);
  const points: Array<{ latitude: number; longitude: number }> = [];

  for (const c of routeForRender) {
    if (isUsableGeoPoint(c.latitude, c.longitude)) {
      points.push(c);
    }
  }
  for (const m of model.markers) {
    if (isUsableGeoPoint(m.latitude, m.longitude)) {
      points.push({ latitude: m.latitude, longitude: m.longitude });
    }
  }

  if (points.length === 0) {
    return null;
  }

  let minLat = points[0].latitude;
  let maxLat = points[0].latitude;
  let minLng = points[0].longitude;
  let maxLng = points[0].longitude;

  for (const p of points) {
    if (p.latitude < minLat) minLat = p.latitude;
    if (p.latitude > maxLat) maxLat = p.latitude;
    if (p.longitude < minLng) minLng = p.longitude;
    if (p.longitude > maxLng) maxLng = p.longitude;
  }

  if (
    !Number.isFinite(minLat) ||
    !Number.isFinite(maxLat) ||
    !Number.isFinite(minLng) ||
    !Number.isFinite(maxLng)
  ) {
    return null;
  }

  const latSpan = Math.max(maxLat - minLat, 0.002);
  const lngSpan = Math.max(maxLng - minLng, 0.002);
  const safePadding = Math.max(12, Math.min(padding, Math.min(width, height) / 4));
  const innerW = Math.max(width - safePadding * 2, 1);
  const innerH = Math.max(height - safePadding * 2, 1);

  const project = (latitude: number, longitude: number): ProjectedPoint => {
    const x = safePadding + ((longitude - minLng) / lngSpan) * innerW;
    const y = safePadding + (1 - (latitude - minLat) / latSpan) * innerH;
    return {
      x: Number.isFinite(x) ? x : safePadding,
      y: Number.isFinite(y) ? y : safePadding,
    };
  };

  return {
    markers: model.markers
      .filter((m) => isUsableGeoPoint(m.latitude, m.longitude))
      .map((m) => ({
        ...m,
        ...project(m.latitude, m.longitude),
      })),
    // Planned route only — never invent a hub-to-hub connector when route is missing.
    route: routeForRender.map((c) => project(c.latitude, c.longitude)),
  };
}
