import type {
  Shipment,
  ShipmentActiveAssignment,
  ShipmentEngineOps,
  ShipmentSourceRequest,
  ShipmentStatusHistoryItem,
  TimelineEvent,
} from '../types/shipment.types';

/** Raw show/list fields from GET /shipments (ops ShipmentResource). */
export interface ShipmentApiResponse {
  id: string;
  shipment_number: string;
  customer_company_id?: string | null;
  executing_company_id?: string | null;
  collaboration_type?: string | null;
  shipment_order_id?: string | null;
  reference_number?: string | null;
  priority: string;
  status_id: number | string;
  pickup_city?: string | null;
  pickup_country?: string | null;
  pickup_address?: string | null;
  delivery_city?: string | null;
  delivery_country?: string | null;
  delivery_address?: string | null;
  planned_pickup_date?: string | null;
  planned_delivery_date?: string | null;
  actual_pickup_date?: string | null;
  actual_delivery_date?: string | null;
  pickup_company?: string | null;
  pickup_contact?: string | null;
  pickup_phone?: string | null;
  delivery_company?: string | null;
  delivery_contact?: string | null;
  delivery_phone?: string | null;
  cargo_type?: string | null;
  cargo_description?: string | null;
  cargo_weight?: string | number | null;
  cargo_volume?: string | number | null;
  package_count?: number | null;
  hazardous_cargo?: boolean;
  temperature_controlled?: boolean;
  special_instructions?: string | null;
  customer_company?: {
    id: string;
    name?: string;
    company_name?: string;
    company_code?: string;
  } | null;
  executing_company?: {
    id: string;
    name?: string;
    company_name?: string;
    company_code?: string;
  } | null;
  shipment_order?: {
    id: string;
    request_number?: string | null;
    reference_number?: string | null;
    status?: { id: number | string; name: string; type?: string } | null;
  } | null;
  status?: {
    id: number | string;
    name: string;
    type?: string;
  } | null;
  active_assignment?: {
    id: string;
    assignment_code?: string;
    is_active?: boolean;
    status_id?: number | string;
    engine_enrollment_status?: string | null;
    engine_enrolled_at?: string | null;
    status?: { id: number | string; name: string; type?: string } | null;
    driver?: {
      id: string;
      first_name: string;
      last_name: string;
      employee_number?: string;
      phone?: string;
    } | null;
    vehicle?: {
      id: string;
      operational_code: string;
      plate_number: string;
    } | null;
    trailer?: {
      id: string;
      plate_number?: string;
      trailer_type?: string;
    } | null;
  } | null;
  latest_assignment?: {
    id: string;
    assignment_code?: string;
    is_active?: boolean;
    status_id?: number | string;
    engine_enrollment_status?: string | null;
    engine_enrolled_at?: string | null;
    status?: { id: number | string; name: string; type?: string } | null;
    driver?: {
      id: string;
      first_name: string;
      last_name: string;
      employee_number?: string;
      phone?: string;
    } | null;
    vehicle?: {
      id: string;
      operational_code: string;
      plate_number: string;
    } | null;
    trailer?: {
      id: string;
      plate_number?: string;
      trailer_type?: string;
    } | null;
  } | null;
  engine_ops?: {
    available?: boolean;
    simulation_eligible?: boolean;
    enrollment?: {
      status?: string | null;
      enrolled_at?: string | null;
      label?: string;
      can_enroll?: boolean;
      already_enrolled?: boolean;
      enrollment_closed?: boolean;
      block_reason?: string | null;
      assignment_id?: string | null;
    };
    canonical_status?: string | null;
    operational_stage?: string | null;
    previous_operational_stage?: string | null;
    transition_reason?: string | null;
    is_resting?: boolean;
    pickup_geofence_reached?: boolean;
    delivery_geofence_reached?: boolean;
    driving_since_rest_m?: number | null;
    next_rest_offset_m?: number | null;
    engine_updated_at?: string | null;
    route_progress?: {
      completion_percentage?: number | null;
      distance_travelled_m?: number | null;
      remaining_distance_m?: number | null;
      total_distance_m?: number | null;
      completed?: boolean;
      heading?: number | null;
      last_tick?: string | null;
      current_coordinate?: { latitude?: number | null; longitude?: number | null } | null;
    } | null;
    rest?: {
      active?: boolean;
      message?: string;
      rest_stop_count?: number;
      distance_to_next_rest_m?: number | null;
      remaining_rest_seconds?: number | null;
      rest_started_at?: string | null;
      next_rest_stop?: { route_offset_m?: number } | null;
    };
    eta?: {
      estimated_total_travel_seconds?: number | null;
      estimated_remaining_seconds?: number | null;
      source?: string | null;
    } | null;
    journey?: {
      total_distance_m?: number | null;
      max_driving_distance_m?: number | null;
      rest_duration_seconds?: number | null;
      estimated_travel_seconds?: number | null;
      rest_stop_count?: number;
      planned_at?: string | null;
    } | null;
  } | null;
  status_history?: Array<{
    id: string | number;
    from_status?: { name?: string } | null;
    to_status?: { name?: string } | null;
    changed_by?: { id: string; name: string; email: string } | null;
    created_at?: string | null;
  }>;
  notes?: string | null;
  tracking_logs?: Array<{
    id: string;
    latitude?: number | null;
    longitude?: number | null;
    speed?: number | null;
    recorded_at: string;
  }>;
  created_at?: string | null;
  updated_at?: string | null;
}

function historyStatusName(value: unknown): string | null {
  if (value == null) return null;
  if (typeof value === 'string') return value;
  if (typeof value === 'object' && value !== null && 'name' in value) {
    const name = (value as { name?: unknown }).name;
    return typeof name === 'string' ? name : null;
  }
  return null;
}

function mapActiveAssignment(
  assignment: NonNullable<ShipmentApiResponse['active_assignment']>
): ShipmentActiveAssignment {
  const driver = assignment.driver ?? null;
  const vehicle = assignment.vehicle ?? null;
  const trailer = assignment.trailer ?? null;

  return {
    id: assignment.id,
    assignmentCode: assignment.assignment_code ?? null,
    isActive: Boolean(assignment.is_active),
    status: assignment.status?.name ?? null,
    engineEnrollmentStatus: assignment.engine_enrollment_status ?? null,
    engineEnrolledAt: assignment.engine_enrolled_at ?? null,
    driverName: driver ? `${driver.first_name} ${driver.last_name}`.trim() : null,
    driverEmployeeNumber: driver?.employee_number ?? null,
    driverPhone: driver?.phone ?? null,
    vehicleCode: vehicle?.operational_code ?? null,
    vehiclePlate: vehicle?.plate_number ?? null,
    trailerPlate: trailer?.plate_number ?? null,
    trailerType: trailer?.trailer_type ?? null,
  };
}

function mapEngineOps(raw: NonNullable<ShipmentApiResponse['engine_ops']>): ShipmentEngineOps {
  const route = raw.route_progress ?? null;
  const rest = raw.rest ?? {};
  const eta = raw.eta ?? null;
  const journey = raw.journey ?? null;

  return {
    available: Boolean(raw.available),
    simulationEligible: Boolean(raw.simulation_eligible),
    enrollment: {
      status: raw.enrollment?.status ?? null,
      enrolledAt: raw.enrollment?.enrolled_at ?? null,
      label: raw.enrollment?.label || 'Engine state unavailable',
      canEnroll: Boolean(raw.enrollment?.can_enroll),
      alreadyEnrolled: Boolean(raw.enrollment?.already_enrolled),
      enrollmentClosed: Boolean(raw.enrollment?.enrollment_closed),
      blockReason: raw.enrollment?.block_reason ?? null,
      assignmentId: raw.enrollment?.assignment_id
        ? String(raw.enrollment.assignment_id)
        : null,
    },
    canonicalStatus: raw.canonical_status ?? null,
    operationalStage: raw.operational_stage ?? null,
    previousOperationalStage: raw.previous_operational_stage ?? null,
    transitionReason: raw.transition_reason ?? null,
    isResting: Boolean(raw.is_resting),
    pickupGeofenceReached: Boolean(raw.pickup_geofence_reached),
    deliveryGeofenceReached: Boolean(raw.delivery_geofence_reached),
    drivingSinceRestM:
      raw.driving_since_rest_m != null ? Number(raw.driving_since_rest_m) : null,
    nextRestOffsetM: raw.next_rest_offset_m != null ? Number(raw.next_rest_offset_m) : null,
    engineUpdatedAt: raw.engine_updated_at ?? null,
    routeProgress: route
      ? {
          completionPercentage:
            route.completion_percentage != null ? Number(route.completion_percentage) : null,
          distanceTravelledM:
            route.distance_travelled_m != null ? Number(route.distance_travelled_m) : null,
          remainingDistanceM:
            route.remaining_distance_m != null ? Number(route.remaining_distance_m) : null,
          totalDistanceM: route.total_distance_m != null ? Number(route.total_distance_m) : null,
          completed: Boolean(route.completed),
          heading: route.heading != null ? Number(route.heading) : null,
          lastTick: route.last_tick ?? null,
          latitude:
            route.current_coordinate?.latitude != null
              ? Number(route.current_coordinate.latitude)
              : null,
          longitude:
            route.current_coordinate?.longitude != null
              ? Number(route.current_coordinate.longitude)
              : null,
        }
      : null,
    rest: {
      active: Boolean(rest.active),
      message: rest.message || 'No active rest stop',
      restStopCount: rest.rest_stop_count,
      distanceToNextRestM:
        rest.distance_to_next_rest_m != null ? Number(rest.distance_to_next_rest_m) : null,
      remainingRestSeconds:
        rest.remaining_rest_seconds != null ? Number(rest.remaining_rest_seconds) : null,
      restStartedAt: rest.rest_started_at ?? null,
      nextRestOffsetM:
        rest.next_rest_stop?.route_offset_m != null
          ? Number(rest.next_rest_stop.route_offset_m)
          : null,
    },
    eta: eta
      ? {
          estimatedTotalTravelSeconds:
            eta.estimated_total_travel_seconds != null
              ? Number(eta.estimated_total_travel_seconds)
              : null,
          estimatedRemainingSeconds:
            eta.estimated_remaining_seconds != null
              ? Number(eta.estimated_remaining_seconds)
              : null,
          source: eta.source ?? null,
        }
      : null,
    journey: journey
      ? {
          totalDistanceM:
            journey.total_distance_m != null ? Number(journey.total_distance_m) : null,
          maxDrivingDistanceM:
            journey.max_driving_distance_m != null
              ? Number(journey.max_driving_distance_m)
              : null,
          restDurationSeconds:
            journey.rest_duration_seconds != null ? Number(journey.rest_duration_seconds) : null,
          estimatedTravelSeconds:
            journey.estimated_travel_seconds != null
              ? Number(journey.estimated_travel_seconds)
              : null,
          restStopCount: journey.rest_stop_count ?? 0,
          plannedAt: journey.planned_at ?? null,
        }
      : null,
  };
}

function mapStatusHistory(
  rows: NonNullable<ShipmentApiResponse['status_history']>
): ShipmentStatusHistoryItem[] {
  return rows.map((row) => ({
    id: row.id,
    fromStatus: historyStatusName(row.from_status),
    toStatus: historyStatusName(row.to_status),
    changedBy: row.changed_by
      ? {
          id: row.changed_by.id,
          name: row.changed_by.name,
          email: row.changed_by.email,
        }
      : null,
    createdAt: row.created_at ?? null,
  }));
}

function mapTrackingTimeline(
  logs: NonNullable<ShipmentApiResponse['tracking_logs']>
): TimelineEvent[] {
  return logs.map((log) => {
    const coords =
      log.latitude != null && log.longitude != null
        ? `${log.latitude}, ${log.longitude}`
        : 'Location unavailable';
    const speed = log.speed != null ? `Speed ${log.speed}` : '';
    return {
      time: log.recorded_at,
      title: 'Tracking update',
      desc: [coords, speed].filter(Boolean).join(' · '),
    };
  });
}

function mapSourceRequest(api: ShipmentApiResponse): ShipmentSourceRequest | null {
  if (api.shipment_order) {
    return {
      id: api.shipment_order.id,
      requestNumber: api.shipment_order.request_number ?? null,
      referenceNumber: api.shipment_order.reference_number ?? null,
      status: api.shipment_order.status?.name ?? null,
    };
  }
  if (api.shipment_order_id) {
    return {
      id: api.shipment_order_id,
      requestNumber: null,
      referenceNumber: null,
      status: null,
    };
  }
  return null;
}

/**
 * Pass-through mapper: display backend truth without remapping business meaning.
 */
export function mapApiShipmentToShipment(apiShipment: ShipmentApiResponse): Shipment {
  const rawActive = apiShipment.active_assignment ?? null;
  const rawLatest = apiShipment.latest_assignment ?? null;
  const effectiveAssignment = rawActive ?? rawLatest ?? null;

  const mappedActive = rawActive ? mapActiveAssignment(rawActive) : null;
  const mappedLatest = rawLatest ? mapActiveAssignment(rawLatest) : null;
  const mappedEffective = effectiveAssignment ? mapActiveAssignment(effectiveAssignment) : null;

  const customerName =
    apiShipment.customer_company?.company_name ||
    apiShipment.customer_company?.name ||
    'Unavailable';

  const executingName =
    apiShipment.executing_company?.company_name ||
    apiShipment.executing_company?.name ||
    null;

  return {
    id: apiShipment.id,
    shipmentNumber: apiShipment.shipment_number,
    referenceNumber: apiShipment.reference_number ?? null,
    customer: customerName,
    customerCompanyId: apiShipment.customer_company?.id || apiShipment.customer_company_id || null,
    customerCompanyCode: apiShipment.customer_company?.company_code ?? null,
    executingCompanyId: apiShipment.executing_company?.id || apiShipment.executing_company_id || null,
    executingCompanyName: executingName,
    executingCompanyCode: apiShipment.executing_company?.company_code ?? null,
    collaborationType: apiShipment.collaboration_type ?? 'internal',
    origin:
      [apiShipment.pickup_city, apiShipment.pickup_country].filter(Boolean).join(', ') ||
      'Unavailable',
    destination:
      [apiShipment.delivery_city, apiShipment.delivery_country].filter(Boolean).join(', ') ||
      'Unavailable',
    driver: mappedEffective?.driverName ?? null,
    truck: mappedEffective?.vehicleCode || mappedEffective?.vehiclePlate || null,
    trailer: mappedEffective?.trailerType || mappedEffective?.trailerPlate || null,
    priority: apiShipment.priority,
    plannedPickupDate: apiShipment.planned_pickup_date ?? null,
    plannedDeliveryDate: apiShipment.planned_delivery_date ?? null,
    actualPickupDate: apiShipment.actual_pickup_date ?? null,
    actualDeliveryDate: apiShipment.actual_delivery_date ?? null,
    status: apiShipment.status?.name || 'Unavailable',
    statusId: apiShipment.status_id ?? null,
    region: apiShipment.pickup_country || null,
    hasActiveAssignment: Boolean(rawActive),
    activeAssignment: mappedActive,
    latestAssignment: mappedLatest,
    notes: apiShipment.notes ?? null,
    specialInstructions: apiShipment.special_instructions ?? null,
    timeline: mapTrackingTimeline(apiShipment.tracking_logs || []),
    statusHistory: mapStatusHistory(apiShipment.status_history || []),
    pickupCompany: apiShipment.pickup_company ?? null,
    pickupContact: apiShipment.pickup_contact ?? null,
    pickupPhone: apiShipment.pickup_phone ?? null,
    pickupAddress: apiShipment.pickup_address ?? null,
    pickupCity: apiShipment.pickup_city ?? null,
    pickupCountry: apiShipment.pickup_country ?? null,
    deliveryCompany: apiShipment.delivery_company ?? null,
    deliveryContact: apiShipment.delivery_contact ?? null,
    deliveryPhone: apiShipment.delivery_phone ?? null,
    deliveryAddress: apiShipment.delivery_address ?? null,
    deliveryCity: apiShipment.delivery_city ?? null,
    deliveryCountry: apiShipment.delivery_country ?? null,
    cargoType: apiShipment.cargo_type ?? null,
    cargoDescription: apiShipment.cargo_description ?? null,
    cargoWeight: apiShipment.cargo_weight ?? null,
    cargoVolume: apiShipment.cargo_volume ?? null,
    packageCount: apiShipment.package_count ?? null,
    hazardousCargo: Boolean(apiShipment.hazardous_cargo),
    temperatureControlled: Boolean(apiShipment.temperature_controlled),
    shipmentOrderId: apiShipment.shipment_order_id ?? null,
    sourceRequest: mapSourceRequest(apiShipment),
    createdAt: apiShipment.created_at ?? null,
    updatedAt: apiShipment.updated_at ?? null,
    engineOps: apiShipment.engine_ops ? mapEngineOps(apiShipment.engine_ops) : null,
  };
}
