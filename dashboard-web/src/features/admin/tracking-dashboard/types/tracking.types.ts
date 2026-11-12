export type VehicleStatus = 'moving' | 'idle' | 'offline';
export type SignalQuality = 'good' | 'weak' | 'lost';

export interface FleetVehicle {
  id: string;
  fleetNumber: string;
  plateNumber: string;
  driver: string;
  /** Authoritative backend driver_id when numeric. */
  driverId: string;
  /** Authoritative backend assignment_id when numeric. */
  assignmentId?: string;
  shipment: string;
  shipmentId: string;
  status: VehicleStatus;
  speed: number;
  heading: number;
  signalQuality: SignalQuality;
  lastUpdate: string;
  latitude?: number;
  longitude?: number;
}

export interface TrackingStats {
  movingVehicles: number;
  idleVehicles: number;
  offlineVehicles: number;
  activeAssignments: number;
  openAlerts: number;
  averageSpeed: number;
  // Secondary metrics
  movingVehiclesChange?: number;
  movingVehiclesPercentage?: number;
  idleVehiclesLongestIdle?: string;
  offlineVehiclesLastDisconnect?: string;
  activeAssignmentsUtilization?: number;
  openAlertsCritical?: number;
  openAlertsWarning?: number;
  averageSpeedPeak?: number;
}

export interface TrackingEvent {
  id: string;
  type: 'location' | 'speed' | 'status' | 'alert';
  vehicleId: string;
  vehicleName: string;
  message: string;
  timestamp: string;
  severity: 'info' | 'warning' | 'error';
}

export interface VehicleDetails {
  vehicle: {
    id: string;
    fleetNumber: string;
    plateNumber: string;
    manufacturer: string;
    model: string;
    year: number;
    vehicleType?: string;
    connectionStatus?: string;
    engineStatus?: string;
  };
  driver: {
    id: string;
    name: string;
    employeeNumber: string;
    phone: string;
    email: string;
  };
  shipment: {
    id: string;
    shipmentNumber: string;
    origin: string;
    destination: string;
    status: string;
  };
  assignment: {
    id: string;
    code: string;
    startedAt: string;
    estimatedCompletion: string;
  };
  tracking: {
    status: VehicleStatus;
    coordinates: {
      latitude: number;
      longitude: number;
    };
    speed: number;
    heading: number;
    signalQuality: SignalQuality;
    movementStatus: string;
    lastUpdate: string;
  };
  // Enterprise operations data
  tripProgress?: {
    originCity: string;
    destinationCity: string;
    progressPercent: number;
    distanceRemaining: number;
    estimatedDistance: number;
    estimatedArrival: string;
    elapsedTripTime: string;
    averageSpeed: number;
    currentSpeed: number;
  };
  liveTracking?: {
    gpsCoordinates: string;
    heading: number;
    signalQuality: SignalQuality;
    gpsAccuracy: string;
    lastUpdate: string;
    lastCommunication: string;
    onlineDuration: string;
  };
  operationalHealth?: {
    gps: 'healthy' | 'warning' | 'critical';
    engine: 'healthy' | 'warning' | 'critical';
    network: 'healthy' | 'warning' | 'critical';
    assignment: 'healthy' | 'warning' | 'critical';
    shipment: 'healthy' | 'warning' | 'critical';
  };
  quickMetrics?: {
    todayDistance: number;
    averageSpeed: number;
    maxSpeed: number;
    idleTime: string;
    drivingTime: string;
  };
}

export interface TrackingDataResponse {
  vehicles: FleetVehicle[];
  stats: TrackingStats;
  events: TrackingEvent[];
}
