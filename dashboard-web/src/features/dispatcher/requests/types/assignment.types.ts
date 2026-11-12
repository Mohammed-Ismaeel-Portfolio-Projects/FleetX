/** Types for the dispatcher assignment workspace on the request details page. */

export interface AssignmentDriver {
  id: string;
  employeeNumber: string;
  fullName: string;
  email: string | null;
  userId: string | null;
  phone: string | null;
  status: string | null;
  dispatchAvailability: string | null;
  hasActiveAssignment: boolean;
  activeAssignmentCode: string | null;
  activeVehicleCode: string | null;
  companyId?: string | null;
}

export interface AssignmentVehicle {
  id: string;
  operationalCode: string | null;
  plateNumber: string;
  manufacturer: string | null;
  model: string | null;
  status: string | null;
  isActive: boolean;
  hasActiveAssignment: boolean;
  payloadCapacityKg: number | null;
  driverId: string | number | null;
  companyId?: string | null;
  driver: {
    id: string | number;
    fullName: string;
    employeeNumber: string;
    phone: string | null;
    userId: string | number | null;
    status: string | null;
    hasActiveAssignment?: boolean;
    activeAssignmentCode?: string | null;
  } | null;
}

export interface AssignmentTrailer {
  id: string;
  trailerCode: string | null;
  plateNumber: string;
  trailerType: string | null;
  manufacturer: string | null;
  status: string | null;
  isActive: boolean;
  payloadCapacityKg: number | null;
  isMatchingRequestedType?: boolean | null;
  companyId?: string | null;
}

export interface AssignmentFormState {
  driverId: string | null;
  vehicleId: string | null;
  trailerId: string | null;
  notes: string;
}

export interface AssignmentCreationPayload {
  shipment_id: string;
  assignment_code: string;
  driver_id: string;
  vehicle_id: string | null;
  trailer_id: string | null;
  status_id: number;
  assigned_at: string;
  notes: string | null;
}
