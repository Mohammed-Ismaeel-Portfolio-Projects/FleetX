import type {
  DriverAssignmentSummary,
  DriverHistoryListItem,
  DriverHistoryPage,
  DriverHistoryPlace,
} from './historyTypes';

type ApiPlace = {
  company?: string | null;
  city?: string | null;
  country?: string | null;
  address?: string | null;
};

type ApiHistoryItem = {
  id: number;
  assignment_code?: string | null;
  status?: { name?: string; key?: string | null } | null;
  outcome?: { key?: string; label?: string } | null;
  execution_phase?: string | null;
  shipment_number?: string | null;
  pickup?: ApiPlace | null;
  delivery?: ApiPlace | null;
  planned_pickup_date?: string | null;
  planned_delivery_date?: string | null;
  assigned_at?: string | null;
  ended_at?: string | null;
};

function mapPlace(raw: ApiPlace | null | undefined): DriverHistoryPlace {
  return {
    company: raw?.company ?? null,
    city: raw?.city ?? null,
    country: raw?.country ?? null,
    address: raw?.address ?? null,
  };
}

export function mapHistoryListItem(raw: unknown): DriverHistoryListItem | null {
  if (raw == null || typeof raw !== 'object' || Array.isArray(raw)) return null;
  const a = raw as ApiHistoryItem;
  if (typeof a.id !== 'number') return null;
  return {
    id: a.id,
    assignmentCode: a.assignment_code ?? null,
    status: a.status
      ? { name: a.status.name ?? 'Unavailable', key: a.status.key ?? null }
      : null,
    outcome: {
      key: a.outcome?.key ?? 'unavailable',
      label: a.outcome?.label ?? 'Unavailable',
    },
    executionPhase: a.execution_phase ?? null,
    shipmentNumber: a.shipment_number ?? null,
    pickup: mapPlace(a.pickup),
    delivery: mapPlace(a.delivery),
    plannedPickupDate: a.planned_pickup_date ?? null,
    plannedDeliveryDate: a.planned_delivery_date ?? null,
    assignedAt: a.assigned_at ?? null,
    endedAt: a.ended_at ?? null,
  };
}

export function mapHistoryPage(raw: unknown): DriverHistoryPage {
  const envelope = raw as {
    data?: unknown[];
    meta?: { current_page?: number; per_page?: number; total?: number; last_page?: number };
  } | null;
  const items = (envelope?.data ?? [])
    .map(mapHistoryListItem)
    .filter((x): x is DriverHistoryListItem => x !== null);
  return {
    items,
    page: envelope?.meta?.current_page ?? 1,
    perPage: envelope?.meta?.per_page ?? 20,
    total: envelope?.meta?.total ?? items.length,
    lastPage: envelope?.meta?.last_page ?? 1,
  };
}

export function mapAssignmentSummary(raw: unknown): DriverAssignmentSummary | null {
  if (raw == null || typeof raw !== 'object' || Array.isArray(raw)) return null;
  const a = raw as {
    id?: number;
    assignment_code?: string | null;
    status?: { name?: string; key?: string | null } | null;
    outcome?: { key?: string; label?: string } | null;
    execution?: { phase?: string | null } | null;
    assigned_at?: string | null;
    ended_at?: string | null;
    shipment?: {
      id?: string;
      shipment_number?: string | null;
      reference_number?: string | null;
      status?: string | null;
      pickup?: ApiPlace | null;
      delivery?: ApiPlace | null;
      planned_pickup_date?: string | null;
      planned_delivery_date?: string | null;
    } | null;
    vehicle?: { plate_number?: string | null; fleet_number?: string | null } | null;
    trailer?: { trailer_code?: string | null; plate_number?: string | null } | null;
    verification?: {
      pickup?: { verified?: boolean; verified_at?: string | null };
      delivery?: { verified?: boolean; verified_at?: string | null };
    };
    proof?: { exists?: boolean; confirmed_at?: string | null; types?: string[] };
    exceptions?: {
      type?: string;
      label?: string;
      description?: string | null;
      status?: string | null;
      created_at?: string | null;
    }[];
    safety?: {
      speed_alert_count?: number;
      harsh_braking_count?: number;
      sudden_stop_count?: number;
      motion_alert_count?: number;
    };
  };
  if (typeof a.id !== 'number') return null;
  return {
    id: a.id,
    assignmentCode: a.assignment_code ?? null,
    status: a.status
      ? { name: a.status.name ?? 'Unavailable', key: a.status.key ?? null }
      : null,
    outcome: {
      key: a.outcome?.key ?? 'unavailable',
      label: a.outcome?.label ?? 'Unavailable',
    },
    executionPhase: a.execution?.phase ?? null,
    assignedAt: a.assigned_at ?? null,
    endedAt: a.ended_at ?? null,
    shipment: a.shipment?.id
      ? {
          id: a.shipment.id,
          shipmentNumber: a.shipment.shipment_number ?? null,
          referenceNumber: a.shipment.reference_number ?? null,
          status: a.shipment.status ?? null,
          pickup: mapPlace(a.shipment.pickup),
          delivery: mapPlace(a.shipment.delivery),
          plannedPickupDate: a.shipment.planned_pickup_date ?? null,
          plannedDeliveryDate: a.shipment.planned_delivery_date ?? null,
        }
      : null,
    vehicle: a.vehicle
      ? {
          plateNumber: a.vehicle.plate_number ?? null,
          fleetNumber: a.vehicle.fleet_number ?? null,
        }
      : null,
    trailer: a.trailer
      ? {
          trailerCode: a.trailer.trailer_code ?? null,
          plateNumber: a.trailer.plate_number ?? null,
        }
      : null,
    verification: {
      pickup: {
        verified: Boolean(a.verification?.pickup?.verified),
        verifiedAt: a.verification?.pickup?.verified_at ?? null,
      },
      delivery: {
        verified: Boolean(a.verification?.delivery?.verified),
        verifiedAt: a.verification?.delivery?.verified_at ?? null,
      },
    },
    proof: {
      exists: Boolean(a.proof?.exists),
      confirmedAt: a.proof?.confirmed_at ?? null,
      types: Array.isArray(a.proof?.types) ? a.proof.types.filter((t) => typeof t === 'string') : [],
    },
    exceptions: (a.exceptions ?? []).map((e) => ({
      type: e.type ?? '',
      label: e.label ?? e.type ?? 'Issue',
      description: e.description ?? null,
      status: e.status ?? null,
      createdAt: e.created_at ?? null,
    })),
    safety: {
      speedAlertCount: Number(a.safety?.speed_alert_count ?? 0),
      harshBrakingCount: Number(a.safety?.harsh_braking_count ?? 0),
      suddenStopCount: Number(a.safety?.sudden_stop_count ?? 0),
      motionAlertCount: Number(
        a.safety?.motion_alert_count ??
          (Number(a.safety?.harsh_braking_count ?? 0) +
            Number(a.safety?.sudden_stop_count ?? 0))
      ),
    },
  };
}

export function formatHistoryPlace(place: DriverHistoryPlace): string {
  const parts = [place.company, place.city, place.country].filter(
    (p) => typeof p === 'string' && p.trim()
  );
  return parts.length ? parts.join(' · ') : 'Unavailable';
}
