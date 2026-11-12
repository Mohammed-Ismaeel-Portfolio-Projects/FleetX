export type VehicleStatus =
  | 'Available'
  | 'Assigned'
  | 'In Transit'
  | 'Loading'
  | 'Unloading'
  | 'At Customs'
  | 'Maintenance'
  | 'Out of Service'
  | 'Inactive';

export type AvailabilityStatus = 'Available' | 'Unavailable';

export interface VehicleAssignmentInfo {
  id: string;
  assignmentCode?: string;
  isActive: boolean;
  driverName?: string;
  driverPhone?: string;
  shipmentNumber?: string;
  trailerType?: string;
  trailerPlate?: string;
}

export interface Vehicle {
  id: string;
  truckId: string;
  plateNumber: string;
  truckType?: string;
  trailerType?: string;
  driver?: string;
  currentShipment?: string;
  currentLocation?: string;
  status?: VehicleStatus;
  mileage?: number;
  gpsStatus?: 'Active' | 'Inactive';
  availability: AvailabilityStatus;
  hasActiveAssignment: boolean;
  assignment?: VehicleAssignmentInfo;
  onlineStatus?: string;
  signalQuality?: string;
  speed?: number;
  movementStatus?: string;
  specifications: {
    make: string;
    model: string;
    year: number;
    vin: string;
    capacityKg?: number;
    fuelCapacityL?: number;
  };
  assignedDriver?: {
    id: string;
    name: string;
    phone?: string;
  };
  insuranceExpiry?: string;
  registrationExpiry?: string;
  companyId?: string | null;
}

/** Tracking + commitment metrics only — no invented diagnostics. */
export interface FleetHealth {
  status?: VehicleStatus;
  availability: AvailabilityStatus;
  hasActiveAssignment: boolean;
  onlineStatus?: string;
  signalQuality?: string;
  speed?: number;
  movementStatus?: string;
  odometer?: number;
}

export interface Maintenance {
  id: string;
  type: 'Oil Change' | 'Tire Service' | 'Brake Service' | 'Engine Repair' | 'Inspection' | 'Other';
  status: 'Upcoming' | 'Overdue' | 'Completed';
  dueDate: string;
  priority: 'Low' | 'Medium' | 'High' | 'Critical';
  description: string;
  estimatedCost?: number;
}

export interface VehicleTimelineEvent {
  id: string;
  type: 'vehicle_assigned' | 'route_started' | 'checkpoint' | 'maintenance' | 'inspection' | 'fuel_refill' | 'delivery_completed' | 'vehicle_returned';
  message: string;
  time: string;
  status: 'success' | 'warning' | 'error' | 'info';
  details?: string;
}
