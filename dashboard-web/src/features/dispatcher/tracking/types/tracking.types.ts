export type VehicleStatus = 'Active' | 'Idle' | 'In Transit' | 'Delayed' | 'Offline';

export interface Coordinates {
  lat: number;
  lng: number;
}

/**
 * Live fleet vehicle — React identity is always `id` === `vehicleId`.
 * `trackingStateId` is metadata only and must never be used as a list/selection key.
 */
export interface LiveVehicle {
  /** Stable React / selection identity — always equals vehicleId */
  id: string;
  vehicleId: string;
  assignmentId: string;
  trackingStateId?: string | null;
  driverId: string;
  driverName: string;
  shipmentId: string;
  shipmentOrderId?: string | null;
  shipmentNumber: string;
  pickupCity?: string;
  deliveryCity?: string;
  status: VehicleStatus;
  speed: number;
  latitude: number;
  longitude: number;
  heading: number;
  movementStatus: string;
  onlineStatus: string;
  signalQuality: number;
  lastUpdateAt: string;
  vehicleCode: string;
  plateNumber: string;
  routeProgress?: {
    routeId?: string | null;
    completionPercentage?: number | null;
    distanceTravelledM?: number | null;
    remainingDistanceM?: number | null;
    segmentIndex?: number | null;
    completed?: boolean;
    provider?: string | null;
  } | null;
}

export interface TrackingActivity {
  id: string;
  time: string;
  message: string;
  status: 'info' | 'warning' | 'success' | 'error';
}

export interface FleetKPIs {
  total_fleet: number;
  available: number;
  available_percentage: number;
  assigned: number;
  assigned_percentage: number;
  offline: number;
  offline_percentage: number;
  maintenance: number;
  maintenance_percentage: number;
  utilization: number;
}
