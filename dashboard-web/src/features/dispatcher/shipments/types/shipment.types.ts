/** Official shipment status vocabulary (ShipmentSeeder / lifecycle). */
export type ShipmentStatus = string;

/** Official shipment priority enum. */
export type ShipmentPriority = 'low' | 'normal' | 'high' | 'urgent';

export interface TimelineEvent {
  time: string;
  title: string;
  desc: string;
}

export interface ShipmentStatusHistoryItem {
  id: string | number;
  fromStatus: string | null;
  toStatus: string | null;
  changedBy: { id: string; name: string; email: string } | null;
  createdAt: string | null;
}

export interface ShipmentActiveAssignment {
  id: string;
  assignmentCode: string | null;
  isActive: boolean;
  status: string | null;
  engineEnrollmentStatus: string | null;
  engineEnrolledAt: string | null;
  driverName: string | null;
  driverEmployeeNumber: string | null;
  driverPhone: string | null;
  vehicleCode: string | null;
  vehiclePlate: string | null;
  trailerPlate: string | null;
  trailerType: string | null;
}

/** Read-only Engine operational snapshot (C2.9-C2.8 Step 6). */
export interface ShipmentEngineOps {
  available: boolean;
  simulationEligible: boolean;
  enrollment: {
    status: string | null;
    enrolledAt: string | null;
    label: string;
    canEnroll: boolean;
    alreadyEnrolled: boolean;
    enrollmentClosed: boolean;
    blockReason: string | null;
    assignmentId: string | null;
  };
  canonicalStatus: string | null;
  operationalStage: string | null;
  previousOperationalStage: string | null;
  transitionReason: string | null;
  isResting: boolean;
  pickupGeofenceReached: boolean;
  deliveryGeofenceReached: boolean;
  drivingSinceRestM: number | null;
  nextRestOffsetM: number | null;
  engineUpdatedAt: string | null;
  routeProgress: {
    completionPercentage: number | null;
    distanceTravelledM: number | null;
    remainingDistanceM: number | null;
    totalDistanceM: number | null;
    completed: boolean;
    heading: number | null;
    lastTick: string | null;
    latitude: number | null;
    longitude: number | null;
  } | null;
  rest: {
    active: boolean;
    message: string;
    restStopCount?: number;
    distanceToNextRestM: number | null;
    remainingRestSeconds: number | null;
    restStartedAt: string | null;
    nextRestOffsetM: number | null;
  };
  eta: {
    estimatedTotalTravelSeconds: number | null;
    estimatedRemainingSeconds: number | null;
    source: string | null;
  } | null;
  journey: {
    totalDistanceM: number | null;
    maxDrivingDistanceM: number | null;
    restDurationSeconds: number | null;
    estimatedTravelSeconds: number | null;
    restStopCount: number;
    plannedAt: string | null;
  } | null;
}

export interface ShipmentSourceRequest {
  id: string;
  requestNumber: string | null;
  referenceNumber: string | null;
  status: string | null;
}

export interface Shipment {
  /** Canonical UUID */
  id: string;
  shipmentNumber: string;
  referenceNumber: string | null;
  customer: string;
  customerCompanyId: string | null;
  customerCompanyCode: string | null;
  origin: string;
  destination: string;
  driver: string | null;
  truck: string | null;
  trailer: string | null;
  priority: ShipmentPriority | string;
  /** Planned delivery date (Y-m-d) — not a fabricated clock ETA */
  plannedPickupDate: string | null;
  plannedDeliveryDate: string | null;
  actualPickupDate: string | null;
  actualDeliveryDate: string | null;
  status: ShipmentStatus;
  statusId: number | string | null;
  region: string | null;
  hasActiveAssignment: boolean;
  activeAssignment: ShipmentActiveAssignment | null;
  latestAssignment?: ShipmentActiveAssignment | null;
  notes?: string | null;
  specialInstructions: string | null;
  timeline: TimelineEvent[];
  statusHistory: ShipmentStatusHistoryItem[];
  /** Site contact fields from ShipmentResource (may be null). */
  pickupCompany: string | null;
  pickupContact: string | null;
  pickupPhone: string | null;
  pickupAddress: string | null;
  pickupCity: string | null;
  pickupCountry: string | null;
  deliveryCompany: string | null;
  deliveryContact: string | null;
  deliveryPhone: string | null;
  deliveryAddress: string | null;
  deliveryCity: string | null;
  deliveryCountry: string | null;
  cargoType: string | null;
  cargoDescription: string | null;
  cargoWeight: string | number | null;
  cargoVolume: string | number | null;
  packageCount: number | null;
  hazardousCargo: boolean;
  temperatureControlled: boolean;
  shipmentOrderId: string | null;
  sourceRequest: ShipmentSourceRequest | null;
  createdAt: string | null;
  updatedAt: string | null;
  /** Partner Fleet collaboration fields */
  executingCompanyId?: string | null;
  executingCompanyName?: string | null;
  executingCompanyCode?: string | null;
  collaborationType?: 'internal' | 'outbound_partner' | 'inbound_partner' | string | null;
  /** Engine operational read model — never mutates Engine from Dispatcher. */
  engineOps: ShipmentEngineOps | null;
}
