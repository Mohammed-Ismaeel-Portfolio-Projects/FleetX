import type {
  DriverExecutionSummary,
  DriverPlace,
  DriverShipmentSummary,
  DriverTrailerSummary,
  DriverVehicleSummary,
  DriverWorkAssignment,
} from './workTypes';

type ApiPlace = {
  company?: string | null;
  address?: string | null;
  city?: string | null;
  country?: string | null;
};

type ApiAssignment = {
  id: number;
  assignment_code?: string | null;
  is_active?: boolean;
  status?: { id: number; name: string; key?: string | null } | null;
  execution?: {
    phase?: string | null;
    stage?: string | null;
    verification_boundary?: boolean;
  } | null;
  assigned_at?: string | null;
  ended_at?: string | null;
  created_at?: string | null;
  updated_at?: string | null;
  shipment?: {
    id: string;
    shipment_number?: string | null;
    reference_number?: string | null;
    status?: { id: number; name: string } | null;
    pickup?: ApiPlace | null;
    delivery?: ApiPlace | null;
    planned_pickup_date?: string | null;
    planned_delivery_date?: string | null;
  } | null;
  vehicle?: {
    id: number;
    fleet_number?: string | null;
    plate_number?: string | null;
    manufacturer?: string | null;
    model?: string | null;
  } | null;
  trailer?: {
    id: number;
    trailer_code?: string | null;
    fleet_number?: string | null;
    plate_number?: string | null;
  } | null;
};

function mapPlace(raw: ApiPlace | null | undefined): DriverPlace {
  return {
    company: raw?.company ?? null,
    address: raw?.address ?? null,
    city: raw?.city ?? null,
    country: raw?.country ?? null,
  };
}

function mapShipment(raw: ApiAssignment['shipment']): DriverShipmentSummary | null {
  if (!raw || typeof raw.id !== 'string') return null;
  return {
    id: raw.id,
    shipmentNumber: raw.shipment_number ?? null,
    referenceNumber: raw.reference_number ?? null,
    status: raw.status
      ? { id: raw.status.id, name: raw.status.name }
      : null,
    pickup: mapPlace(raw.pickup),
    delivery: mapPlace(raw.delivery),
    plannedPickupDate: raw.planned_pickup_date ?? null,
    plannedDeliveryDate: raw.planned_delivery_date ?? null,
  };
}

function mapVehicle(raw: ApiAssignment['vehicle']): DriverVehicleSummary | null {
  if (!raw || typeof raw.id !== 'number') return null;
  return {
    id: raw.id,
    fleetNumber: raw.fleet_number ?? null,
    plateNumber: raw.plate_number ?? null,
    manufacturer: raw.manufacturer ?? null,
    model: raw.model ?? null,
  };
}

function mapTrailer(raw: ApiAssignment['trailer']): DriverTrailerSummary | null {
  if (!raw || typeof raw.id !== 'number') return null;
  return {
    id: raw.id,
    trailerCode: raw.trailer_code ?? null,
    fleetNumber: raw.fleet_number ?? null,
    plateNumber: raw.plate_number ?? null,
  };
}

function mapExecution(raw: ApiAssignment['execution']): DriverExecutionSummary | null {
  if (raw == null || typeof raw !== 'object') {
    return { phase: null, stage: null, verificationBoundary: false };
  }
  return {
    phase: raw.phase ?? null,
    stage: raw.stage ?? null,
    verificationBoundary: Boolean(raw.verification_boundary),
  };
}

export function mapDriverWorkAssignment(raw: unknown): DriverWorkAssignment | null {
  if (raw == null || typeof raw !== 'object' || Array.isArray(raw)) return null;
  const a = raw as ApiAssignment;
  if (typeof a.id !== 'number') return null;

  return {
    id: a.id,
    assignmentCode: a.assignment_code ?? null,
    isActive: Boolean(a.is_active),
    status: a.status
      ? {
          id: a.status.id,
          name: a.status.name,
          key: a.status.key ?? null,
        }
      : null,
    execution: mapExecution(a.execution),
    assignedAt: a.assigned_at ?? null,
    endedAt: a.ended_at ?? null,
    createdAt: a.created_at ?? null,
    updatedAt: a.updated_at ?? null,
    shipment: mapShipment(a.shipment),
    vehicle: mapVehicle(a.vehicle),
    trailer: mapTrailer(a.trailer),
  };
}

export function formatPlaceLine(place: DriverPlace): string {
  const parts = [place.company, place.city, place.country].filter(
    (p) => typeof p === 'string' && p.trim()
  );
  return parts.length ? parts.join(' · ') : 'Unavailable';
}
