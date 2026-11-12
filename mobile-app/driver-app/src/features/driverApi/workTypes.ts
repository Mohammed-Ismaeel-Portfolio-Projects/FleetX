/**
 * Driver work types — mirror DriverWorkAssignmentResource (D2).
 */

export type DriverStatusRef = {
  id: number;
  name: string;
  key: string | null;
};

export type DriverPlace = {
  company: string | null;
  address: string | null;
  city: string | null;
  country: string | null;
};

export type DriverShipmentSummary = {
  id: string;
  shipmentNumber: string | null;
  referenceNumber: string | null;
  status: { id: number; name: string } | null;
  pickup: DriverPlace;
  delivery: DriverPlace;
  plannedPickupDate: string | null;
  plannedDeliveryDate: string | null;
};

export type DriverVehicleSummary = {
  id: number;
  fleetNumber: string | null;
  plateNumber: string | null;
  manufacturer: string | null;
  model: string | null;
};

export type DriverTrailerSummary = {
  id: number;
  trailerCode: string | null;
  fleetNumber: string | null;
  plateNumber: string | null;
};

export type DriverExecutionSummary = {
  phase: string | null;
  stage: 'pickup' | 'delivery' | string | null;
  verificationBoundary: boolean;
};

export type DriverWorkAssignment = {
  id: number;
  assignmentCode: string | null;
  isActive: boolean;
  status: DriverStatusRef | null;
  execution: DriverExecutionSummary | null;
  assignedAt: string | null;
  endedAt: string | null;
  createdAt: string | null;
  updatedAt: string | null;
  shipment: DriverShipmentSummary | null;
  vehicle: DriverVehicleSummary | null;
  trailer: DriverTrailerSummary | null;
};

export class DriverWorkApiError extends Error {
  statusCode?: number;
  code?: string | null;

  constructor(message: string, statusCode?: number, code?: string | null) {
    super(message);
    this.name = 'DriverWorkApiError';
    this.statusCode = statusCode;
    this.code = code ?? null;
  }
}

export function isRetryableWorkError(error: unknown): boolean {
  if (!(error instanceof DriverWorkApiError)) return true;
  const s = error.statusCode;
  if (s === 401 || s === 403 || s === 404) return false;
  return true;
}
