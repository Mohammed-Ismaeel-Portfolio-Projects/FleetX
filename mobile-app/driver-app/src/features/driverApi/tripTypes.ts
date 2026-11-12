/**
 * Driver trip-map / telemetry types (D6) — mirror Driver trip-map API.
 */

export type DriverGeoPoint = {
  latitude: number;
  longitude: number;
};

export type DriverTripPosition = DriverGeoPoint & {
  accuracy: number | null;
  speed: number | null;
  heading: number | null;
  recordedAt: string | null;
  receivedAt: string | null;
  source: 'server_last_known' | 'device_current' | string;
  movementStatus?: string | null;
  signalQuality?: string | null;
};

export type DriverTripFreshness = {
  state: 'fresh' | 'stale' | 'very_stale' | 'unavailable' | string;
  recordedAt: string | null;
  ageSeconds: number;
};

export type DriverTripMap = {
  assignment: {
    id: number;
    assignmentCode: string | null;
    isActive: boolean;
    status: { name: string; key: string | null } | null;
  };
  execution: {
    phase: string | null;
    stage: string | null;
    verificationBoundary: boolean;
  };
  shipment: {
    id: string;
    shipmentNumber: string | null;
    status: { id: number; name: string } | null;
    pickup: {
      company: string | null;
      address: string | null;
      city: string | null;
      country: string | null;
      coordinates: DriverGeoPoint | null;
    };
    delivery: {
      company: string | null;
      address: string | null;
      city: string | null;
      country: string | null;
      coordinates: DriverGeoPoint | null;
    };
  } | null;
  route: { coordinates: DriverGeoPoint[] } | null;
  routeAvailable: boolean;
  currentPosition: DriverTripPosition | null;
  freshness: DriverTripFreshness;
  trackingStatus: 'active' | 'limited' | 'unavailable' | string;
};

export type DeviceFix = {
  latitude: number;
  longitude: number;
  accuracy: number | null;
  speed: number | null;
  heading: number | null;
  recordedAt: string;
};
