/**
 * Tracking Domain Types
 * 
 * Tracking-related domain model following FleetX enterprise architecture.
 * Placeholder interfaces for future implementation.
 */

import type { ShipmentStatus } from './shipment.types';

// TODO: Implement TimelineEvent interface
export interface TimelineEvent {
  // TODO: Add timeline event fields
  id: string;
  shipmentId: string;
  eventType: EventType;
  timestamp: Date;
  // ... additional fields
}

// TODO: Implement EventType enum
export type EventType = 
  | 'location_update'
  | 'pickup_started'
  | 'pickup_completed'
  | 'departure'
  | 'arrival'
  | 'delay_started'
  | 'delay_ended'
  | 'fuel_stop_started'
  | 'fuel_stop_completed'
  | 'rest_stop_started'
  | 'rest_stop_completed'
  | 'border_crossing'
  | 'customs_clearance'
  | 'delivery_started'
  | 'delivery_completed';

// TODO: Implement TrackingSummary interface
export interface TrackingSummary {
  // TODO: Add tracking summary fields
  shipmentId: string;
  currentStatus: ShipmentStatus;
  currentLocation?: GeoPoint;
  estimatedArrival?: Date;
  // ... additional fields
}

// TODO: Implement GeoPoint interface
export interface GeoPoint {
  latitude: number;
  longitude: number;
}
