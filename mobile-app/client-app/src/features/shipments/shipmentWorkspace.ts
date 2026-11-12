/**
 * Customer-facing Shipment Workspace presentation (C2.9-C3.6).
 * Maps canonical Client shipment status names only — no Engine stages, no invented ETA.
 */

export const SHIPMENT_STATUS = {
  WAITING_ASSIGNMENT: 'Waiting Assignment',
  ASSIGNED: 'Assigned',
  READY_FOR_PICKUP: 'Ready For Pickup',
  PICKED_UP: 'Picked Up',
  IN_TRANSIT: 'In Transit',
  AT_CUSTOMS: 'At Customs',
  OUT_FOR_DELIVERY: 'Out For Delivery',
  DELIVERED: 'Delivered',
  COMPLETED: 'Completed',
  CANCELLED: 'Cancelled',
} as const;

/**
 * Exact `status.name` values for GET /client/shipments?status=…
 * Source of truth: Client lifecycle catalog (ShipmentSeeder / workspace), not StatusesSeeder snake_case.
 * Labels for chips use customerFacingShipmentStatus — query values remain these exact names.
 */
export const CLIENT_SHIPMENT_STATUS_FILTERS: readonly (typeof SHIPMENT_STATUS)[keyof typeof SHIPMENT_STATUS][] =
  [
    SHIPMENT_STATUS.WAITING_ASSIGNMENT,
    SHIPMENT_STATUS.ASSIGNED,
    SHIPMENT_STATUS.READY_FOR_PICKUP,
    SHIPMENT_STATUS.PICKED_UP,
    SHIPMENT_STATUS.IN_TRANSIT,
    SHIPMENT_STATUS.AT_CUSTOMS,
    SHIPMENT_STATUS.OUT_FOR_DELIVERY,
    SHIPMENT_STATUS.DELIVERED,
    SHIPMENT_STATUS.COMPLETED,
    SHIPMENT_STATUS.CANCELLED,
  ];

/** Exact `priority` values for GET /client/shipments?priority=… (backend contract). */
export const CLIENT_SHIPMENT_PRIORITY_FILTERS = ['low', 'normal', 'high', 'urgent'] as const;

export type ClientShipmentPriorityFilter = (typeof CLIENT_SHIPMENT_PRIORITY_FILTERS)[number];

export function formatShipmentPriorityFilterLabel(value: string): string {
  const trimmed = String(value || '').trim();
  if (!trimmed) return 'Unknown';
  return trimmed.charAt(0).toUpperCase() + trimmed.slice(1).toLowerCase();
}
export interface ShipmentLifecycleStep {
  key: 'waiting' | 'assigned' | 'transit' | 'delivered' | 'completed';
  label: string;
}

export const CUSTOMER_LIFECYCLE_STEPS: ShipmentLifecycleStep[] = [
  { key: 'waiting', label: 'Waiting for Assignment' },
  { key: 'assigned', label: 'Assigned' },
  { key: 'transit', label: 'In Transit' },
  { key: 'delivered', label: 'Delivered' },
  { key: 'completed', label: 'Completed' },
];

const CUSTOMER_STATUS_LABELS: Record<string, string> = {
  [SHIPMENT_STATUS.WAITING_ASSIGNMENT]: 'Waiting for Assignment',
  [SHIPMENT_STATUS.ASSIGNED]: 'Assigned',
  [SHIPMENT_STATUS.READY_FOR_PICKUP]: 'Ready for Pickup',
  [SHIPMENT_STATUS.PICKED_UP]: 'Picked Up',
  [SHIPMENT_STATUS.IN_TRANSIT]: 'In Transit',
  [SHIPMENT_STATUS.AT_CUSTOMS]: 'At Customs',
  [SHIPMENT_STATUS.OUT_FOR_DELIVERY]: 'Out for Delivery',
  [SHIPMENT_STATUS.DELIVERED]: 'Delivered',
  [SHIPMENT_STATUS.COMPLETED]: 'Completed',
  [SHIPMENT_STATUS.CANCELLED]: 'Cancelled',
};

function statusName(raw: string | null | undefined): string | null {
  if (!raw) return null;
  const trimmed = String(raw).trim();
  return trimmed ? trimmed : null;
}

/** Customer-facing status label — never shows Engine stage names. */
export function formatCustomerShipmentStatus(raw: string | null | undefined): string {
  const name = statusName(raw);
  if (!name) return 'Unavailable';
  if (CUSTOMER_STATUS_LABELS[name]) return CUSTOMER_STATUS_LABELS[name];
  return name
    .split(/[_\s]+/)
    .filter(Boolean)
    .map((part) => part.charAt(0).toUpperCase() + part.slice(1).toLowerCase())
    .join(' ');
}

/**
 * Display-layer projection for list/Home/tracking/workspace.
 * Engine operational stages are never shown as a customer status.
 */
export function customerFacingShipmentStatus(raw: string | null | undefined): string {
  if (isEngineStageName(raw)) {
    return formatCustomerShipmentStatus(null);
  }
  return formatCustomerShipmentStatus(raw);
}

export function shipmentStatusGuidance(raw: string | null | undefined): string {
  switch (statusName(raw)) {
    case SHIPMENT_STATUS.WAITING_ASSIGNMENT:
      return 'Your shipment is confirmed and waiting to be assigned.';
    case SHIPMENT_STATUS.ASSIGNED:
      return 'A carrier assignment has been prepared for your shipment.';
    case SHIPMENT_STATUS.READY_FOR_PICKUP:
      return 'Your shipment is ready for pickup.';
    case SHIPMENT_STATUS.PICKED_UP:
      return 'Your shipment has been picked up and is on its way.';
    case SHIPMENT_STATUS.IN_TRANSIT:
      return 'Your shipment is currently on its way.';
    case SHIPMENT_STATUS.AT_CUSTOMS:
      return 'Your shipment is at customs.';
    case SHIPMENT_STATUS.OUT_FOR_DELIVERY:
      return 'Your shipment is out for delivery.';
    case SHIPMENT_STATUS.DELIVERED:
      return 'Your shipment has arrived at the delivery location.';
    case SHIPMENT_STATUS.COMPLETED:
      return 'Your shipment has been completed.';
    case SHIPMENT_STATUS.CANCELLED:
      return 'This shipment was cancelled.';
    default:
      return 'This is the current status of your shipment.';
  }
}

function lifecycleIndex(raw: string | null | undefined): number | null {
  switch (statusName(raw)) {
    case SHIPMENT_STATUS.WAITING_ASSIGNMENT:
      return 0;
    case SHIPMENT_STATUS.ASSIGNED:
    case SHIPMENT_STATUS.READY_FOR_PICKUP:
      return 1;
    case SHIPMENT_STATUS.PICKED_UP:
    case SHIPMENT_STATUS.IN_TRANSIT:
    case SHIPMENT_STATUS.AT_CUSTOMS:
    case SHIPMENT_STATUS.OUT_FOR_DELIVERY:
      return 2;
    case SHIPMENT_STATUS.DELIVERED:
      return 3;
    case SHIPMENT_STATUS.COMPLETED:
      return 4;
    default:
      return null;
  }
}

export function shipmentLifecycleState(raw: string | null | undefined): {
  currentIndex: number | null;
  cancelled: boolean;
} {
  const name = statusName(raw);
  return {
    currentIndex: lifecycleIndex(name),
    cancelled: name === SHIPMENT_STATUS.CANCELLED,
  };
}

export function isEngineStageName(raw: string | null | undefined): boolean {
  const name = statusName(raw);
  if (!name) return false;
  return (
    name === 'Accepted' ||
    name === 'EnRoutePickup' ||
    name === 'ArrivedPickup' ||
    name === 'Loading' ||
    name === 'Loaded' ||
    name === 'Departed' ||
    name === 'EnRouteDelivery' ||
    name === 'ApproachingRestStop' ||
    name === 'Resting' ||
    name === 'ArrivedDelivery' ||
    name === 'Unloading'
  );
}

export type JourneyVisualState = 'completed' | 'current' | 'upcoming';

export interface JourneyDateFacts {
  plannedPickup: string | null;
  actualPickup: string | null;
  plannedDelivery: string | null;
  actualDelivery: string | null;
}

export interface JourneyDateLine {
  kind: 'actual' | 'planned' | 'missing';
  text: string;
}

export interface CustomerJourneyStep {
  key: ShipmentLifecycleStep['key'];
  label: string;
  explanation: string;
  visualState: JourneyVisualState;
  stateLabel: 'Completed' | 'Current' | 'Upcoming';
  dateLine: JourneyDateLine | null;
}

export type CustomerJourneyModel =
  | { kind: 'active'; currentIndex: number; steps: CustomerJourneyStep[] }
  | { kind: 'cancelled' }
  | { kind: 'unknown' };

const STEP_EXPLANATIONS: Record<ShipmentLifecycleStep['key'], string> = {
  waiting: 'Your shipment is confirmed and waiting to be assigned.',
  assigned: 'A carrier assignment has been prepared for your shipment.',
  transit: 'Your shipment is currently on its way.',
  delivered: 'Your shipment has arrived at the delivery location.',
  completed: 'Your shipment has been completed.',
};

function visualStateForIndex(index: number, currentIndex: number): JourneyVisualState {
  if (index < currentIndex) return 'completed';
  if (index === currentIndex) return 'current';
  return 'upcoming';
}

function stateLabelFor(visualState: JourneyVisualState): CustomerJourneyStep['stateLabel'] {
  if (visualState === 'completed') return 'Completed';
  if (visualState === 'current') return 'Current';
  return 'Upcoming';
}

/**
 * Attach only Client show dates. Planned is never presented as an actual event.
 * There is no completion timestamp on the Client show contract.
 */
function dateLineForStep(
  key: ShipmentLifecycleStep['key'],
  visualState: JourneyVisualState,
  facts: JourneyDateFacts
): JourneyDateLine | null {
  if (key === 'assigned') {
    if (visualState === 'current' && facts.plannedPickup && !facts.actualPickup) {
      return { kind: 'planned', text: `Planned pickup ${facts.plannedPickup}` };
    }
    return null;
  }

  if (key === 'transit') {
    if (facts.actualPickup) {
      return { kind: 'actual', text: `Picked up ${facts.actualPickup}` };
    }
    if (visualState === 'completed') {
      return { kind: 'missing', text: 'Pickup time not provided' };
    }
    if (facts.plannedPickup) {
      return { kind: 'planned', text: `Planned pickup ${facts.plannedPickup}` };
    }
    return null;
  }

  if (key === 'delivered') {
    if (facts.actualDelivery) {
      return { kind: 'actual', text: `Delivered ${facts.actualDelivery}` };
    }
    if (visualState === 'completed') {
      return { kind: 'missing', text: 'Delivery time not provided' };
    }
    if (facts.plannedDelivery) {
      return { kind: 'planned', text: `Planned delivery ${facts.plannedDelivery}` };
    }
    return null;
  }

  return null;
}

/** Presentation-only. Does not invent lifecycle position or event history. */
export function buildCustomerJourney(
  rawStatus: string | null | undefined,
  facts: JourneyDateFacts
): CustomerJourneyModel {
  const { currentIndex, cancelled } = shipmentLifecycleState(rawStatus);
  if (cancelled) return { kind: 'cancelled' };
  if (currentIndex == null) return { kind: 'unknown' };

  const steps: CustomerJourneyStep[] = CUSTOMER_LIFECYCLE_STEPS.map((step, index) => {
    const visualState = visualStateForIndex(index, currentIndex);
    return {
      key: step.key,
      label: step.label,
      explanation: STEP_EXPLANATIONS[step.key],
      visualState,
      stateLabel: stateLabelFor(visualState),
      dateLine: dateLineForStep(step.key, visualState, facts),
    };
  });

  return { kind: 'active', currentIndex, steps };
}
