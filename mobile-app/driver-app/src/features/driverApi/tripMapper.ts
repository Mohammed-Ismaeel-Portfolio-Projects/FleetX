import type { DriverGeoPoint, DriverTripMap, DriverTripPosition } from './tripTypes';

type ApiTrip = {
  assignment?: {
    id: number;
    assignment_code?: string | null;
    is_active?: boolean;
    status?: { name: string; key?: string | null } | null;
  };
  execution?: {
    phase?: string | null;
    stage?: string | null;
    verification_boundary?: boolean;
  };
  shipment?: {
    id: string;
    shipment_number?: string | null;
    status?: { id: number; name: string } | null;
    pickup?: {
      company?: string | null;
      address?: string | null;
      city?: string | null;
      country?: string | null;
      coordinates?: { latitude?: number; longitude?: number } | null;
    };
    delivery?: {
      company?: string | null;
      address?: string | null;
      city?: string | null;
      country?: string | null;
      coordinates?: { latitude?: number; longitude?: number } | null;
    };
  } | null;
  route?: { coordinates?: { latitude: number; longitude: number }[] } | null;
  route_available?: boolean;
  current_position?: {
    latitude: number;
    longitude: number;
    accuracy?: number | null;
    speed?: number | null;
    heading?: number | null;
    recorded_at?: string | null;
    received_at?: string | null;
    source?: string;
    movement_status?: string | null;
    signal_quality?: string | null;
  } | null;
  freshness?: {
    state?: string;
    recorded_at?: string | null;
    age_seconds?: number;
  };
  tracking_status?: string;
};

function mapPoint(raw: { latitude?: number; longitude?: number } | null | undefined): DriverGeoPoint | null {
  if (!raw || typeof raw.latitude !== 'number' || typeof raw.longitude !== 'number') return null;
  if (!Number.isFinite(raw.latitude) || !Number.isFinite(raw.longitude)) return null;
  return { latitude: raw.latitude, longitude: raw.longitude };
}

function mapPosition(raw: ApiTrip['current_position']): DriverTripPosition | null {
  if (!raw || typeof raw.latitude !== 'number' || typeof raw.longitude !== 'number') return null;
  return {
    latitude: raw.latitude,
    longitude: raw.longitude,
    accuracy: raw.accuracy ?? null,
    speed: raw.speed ?? null,
    heading: raw.heading ?? null,
    recordedAt: raw.recorded_at ?? null,
    receivedAt: raw.received_at ?? null,
    source: raw.source ?? 'server_last_known',
    movementStatus: raw.movement_status ?? null,
    signalQuality: raw.signal_quality ?? null,
  };
}

export function mapDriverTripMap(raw: unknown): DriverTripMap | null {
  if (raw == null || typeof raw !== 'object' || Array.isArray(raw)) return null;
  const t = raw as ApiTrip;
  if (!t.assignment || typeof t.assignment.id !== 'number') return null;

  const routeCoords = (t.route?.coordinates ?? [])
    .map((c) => mapPoint(c))
    .filter((c): c is DriverGeoPoint => c != null);

  return {
    assignment: {
      id: t.assignment.id,
      assignmentCode: t.assignment.assignment_code ?? null,
      isActive: Boolean(t.assignment.is_active),
      status: t.assignment.status
        ? { name: t.assignment.status.name, key: t.assignment.status.key ?? null }
        : null,
    },
    execution: {
      phase: t.execution?.phase ?? null,
      stage: t.execution?.stage ?? null,
      verificationBoundary: Boolean(t.execution?.verification_boundary),
    },
    shipment: t.shipment
      ? {
          id: t.shipment.id,
          shipmentNumber: t.shipment.shipment_number ?? null,
          status: t.shipment.status ?? null,
          pickup: {
            company: t.shipment.pickup?.company ?? null,
            address: t.shipment.pickup?.address ?? null,
            city: t.shipment.pickup?.city ?? null,
            country: t.shipment.pickup?.country ?? null,
            coordinates: mapPoint(t.shipment.pickup?.coordinates),
          },
          delivery: {
            company: t.shipment.delivery?.company ?? null,
            address: t.shipment.delivery?.address ?? null,
            city: t.shipment.delivery?.city ?? null,
            country: t.shipment.delivery?.country ?? null,
            coordinates: mapPoint(t.shipment.delivery?.coordinates),
          },
        }
      : null,
    route: routeCoords.length >= 2 ? { coordinates: routeCoords } : null,
    routeAvailable: Boolean(t.route_available) && routeCoords.length >= 2,
    currentPosition: mapPosition(t.current_position),
    freshness: {
      state: t.freshness?.state ?? 'unavailable',
      recordedAt: t.freshness?.recorded_at ?? null,
      ageSeconds: t.freshness?.age_seconds ?? 0,
    },
    trackingStatus: t.tracking_status ?? 'unavailable',
  };
}
