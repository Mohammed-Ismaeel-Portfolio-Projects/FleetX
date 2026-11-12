/**
 * Shipment Constants
 * 
 * Shipment-related constants following FleetX enterprise architecture.
 * Placeholder constants for future implementation.
 */

// TODO: Implement ShipmentStatus constants
export const SHIPMENT_STATUS = {
  DRAFT: 'draft',
  PENDING: 'pending',
  APPROVED: 'approved',
  SCHEDULED: 'scheduled',
  ASSIGNED: 'assigned',
  PICKUP: 'pickup',
  IN_TRANSIT: 'in_transit',
  DELAYED: 'delayed',
  CUSTOMS_HOLD: 'customs_hold',
  CUSTOMS_CLEARED: 'customs_cleared',
  ARRIVED: 'arrived',
  UNLOADING: 'unloading',
  DELIVERED: 'delivered',
  COMPLETED: 'completed',
  CANCELLED: 'cancelled',
  REJECTED: 'rejected',
} as const;

// TODO: Implement ShipmentPriority constants
export const SHIPMENT_PRIORITY = {
  STANDARD: 'standard',
  HIGH: 'high',
  URGENT: 'urgent',
  EMERGENCY: 'emergency',
} as const;

// TODO: Implement Shipment color mapping for UI
export const SHIPMENT_STATUS_COLORS = {
  draft: 'gray',
  pending: 'yellow',
  approved: 'blue',
  scheduled: 'cyan',
  assigned: 'indigo',
  pickup: 'purple',
  in_transit: 'green',
  delayed: 'orange',
  customs_hold: 'red',
  customs_cleared: 'green',
  arrived: 'blue',
  unloading: 'purple',
  delivered: 'green',
  completed: 'green',
  cancelled: 'gray',
  rejected: 'red',
} as const;

// TODO: Implement Shipment filter options
export const SHIPMENT_FILTERS = {
  STATUS: 'status',
  PRIORITY: 'priority',
  CLIENT: 'client',
  DATE_RANGE: 'date_range',
  TRUCK: 'truck',
  DRIVER: 'driver',
} as const;
