/**
 * Route API Service
 *
 * Service for requesting shipment routes from the backend Route API.
 * The frontend is a consumer of backend routing only —
 * no route calculation or geometry generation here.
 */

import { axiosClientWithInterceptors } from '../../../../../core/api';

export interface RouteApiResponse {
  success: boolean;
  message: string;
  data: RouteDataResponse | null;
}

export interface RouteDataResponse {
  id: string;
  origin: {
    latitude: number;
    longitude: number;
    city?: string;
  };
  destination: {
    latitude: number;
    longitude: number;
    city?: string;
  };
  geometry: {
    encoded: string;
    coordinates: Array<{ latitude: number; longitude: number }>;
    bounds: [number, number, number, number] | number[];
  };
  distance: {
    meters: number;
    kilometers: number;
    miles: number;
  };
  duration: {
    seconds: number;
    minutes: number;
    hours: number;
  };
  legs: RouteLegResponse[];
  steps: RouteStepResponse[];
  waypoints: RouteWaypointResponse[];
  metadata: {
    provider: string;
    travel_mode: string;
    pickup_city?: string;
    delivery_city?: string;
    shipment_number?: string;
    shipment_id?: string;
    route_status?: 'ON_ROUTE' | 'DEVIATED' | string;
    deviation_distance?: number;
    rerouting_available?: boolean;
    is_reroute?: boolean;
    [key: string]: unknown;
  };
}

export interface RouteLegResponse {
  from: { latitude: number; longitude: number } | null;
  to: { latitude: number; longitude: number } | null;
  distance: {
    meters: number;
    kilometers: number;
    miles: number;
  };
  duration: {
    seconds: number;
    minutes: number;
    hours: number;
  };
  summary: string;
  steps_count: number;
}

export interface RouteStepResponse {
  instruction: string;
  distance: {
    meters: number;
    kilometers: number;
  };
  duration: {
    seconds: number;
    minutes: number;
  };
  maneuver_type: string;
  maneuver_modifier?: string;
  road_name: string;
  road_ref?: string;
}

export interface RouteWaypointResponse {
  id: string;
  coordinate: {
    latitude: number;
    longitude: number;
  };
  name: string;
  distance: number;
  sequence: number;
}

interface BackendEnvelope {
  success: boolean;
  message: string;
  data?: {
    route?: Record<string, unknown> | null;
    metadata?: Record<string, unknown>;
  } | RouteDataResponse | null;
}

function normalizeGeometry(raw: unknown): RouteDataResponse['geometry'] {
  const geometry = (raw ?? {}) as Record<string, unknown>;
  const nestedPolyline = (geometry.polyline ?? {}) as Record<string, unknown>;

  const coordinates =
    (geometry.coordinates as Array<{ latitude: number; longitude: number }> | undefined) ??
    (nestedPolyline.coordinates as Array<{ latitude: number; longitude: number }> | undefined) ??
    [];

  return {
    encoded:
      (geometry.encoded as string | undefined) ??
      (nestedPolyline.encoded as string | undefined) ??
      '',
    coordinates: Array.isArray(coordinates) ? coordinates : [],
    bounds: (geometry.bounds as number[] | undefined) ?? [0, 0, 0, 0],
  };
}

/**
 * Backend returns { route, metadata } inside data.
 * Normalize to the flat RouteDataResponse expected by adapters/UI.
 */
function normalizeRoutePayload(envelope: BackendEnvelope): RouteApiResponse {
  if (!envelope.success || !envelope.data) {
    return {
      success: envelope.success,
      message: envelope.message,
      data: null,
    };
  }

  const payload = envelope.data as {
    route?: Record<string, unknown> | null;
    metadata?: Record<string, unknown>;
  } & Partial<RouteDataResponse>;

  const nestedRoute = payload.route;
  const routeSource = (nestedRoute && typeof nestedRoute === 'object'
    ? nestedRoute
    : payload) as Record<string, unknown>;

  if (!routeSource.origin || !routeSource.destination) {
    return {
      success: false,
      message: envelope.message || 'Route payload missing origin/destination',
      data: null,
    };
  }

  const metadata = {
    provider: String(
      (routeSource.provider as string | undefined) ??
        (payload.metadata?.provider_name as string | undefined) ??
        'unknown'
    ),
    travel_mode: String(
      (routeSource.travel_mode as string | undefined) ?? 'driving'
    ),
    ...(payload.metadata ?? {}),
  };

  const distance = (routeSource.distance ?? { meters: 0, kilometers: 0, miles: 0 }) as RouteDataResponse['distance'];
  const duration = (routeSource.duration ?? { seconds: 0, minutes: 0, hours: 0 }) as RouteDataResponse['duration'];

  return {
    success: true,
    message: envelope.message,
    data: {
      id: String(routeSource.id ?? ''),
      origin: routeSource.origin as RouteDataResponse['origin'],
      destination: routeSource.destination as RouteDataResponse['destination'],
      geometry: normalizeGeometry(routeSource.geometry),
      distance,
      duration,
      legs: (routeSource.legs as RouteLegResponse[] | undefined) ?? [],
      steps: (routeSource.steps as RouteStepResponse[] | undefined) ?? [],
      waypoints: (routeSource.waypoints as RouteWaypointResponse[] | undefined) ?? [],
      metadata,
    },
  };
}

export class RouteApiService {
  /**
   * Get route for a shipment.
   * Final request URL: {baseURL}/shipments/{shipmentId}/route
   * where baseURL defaults to http://localhost:8000/api/v1
   */
  async getShipmentRoute(shipmentId: string): Promise<RouteApiResponse> {
    const url = `/shipments/${shipmentId}/route`;

    try {
      const response = await axiosClientWithInterceptors.get<BackendEnvelope>(url);
      return normalizeRoutePayload(response.data);
    } catch (error) {
      console.error('[RouteApiService] Failed to fetch shipment route:', error);
      throw new Error(`Failed to fetch route for shipment ${shipmentId}`);
    }
  }

  cancelRequests(): void {
    // Reserved for AbortController-based cancellation
  }
}

export const routeApiService = new RouteApiService();
