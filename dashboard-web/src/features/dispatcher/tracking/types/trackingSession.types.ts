export type TrackingSessionStatus = 'INACTIVE' | 'ACTIVE' | 'PAUSED' | 'COMPLETED' | 'TERMINATED';

export interface TrackingSessionDriver {
  id: string;
  name: string | null;
  first_name?: string;
  last_name?: string;
  employee_number?: string;
}

export interface TrackingSessionVehicle {
  id: string;
  plate_number: string;
  operational_code?: string;
}

export interface TrackingSessionTrailer {
  id: string;
  code: string;
  plate_number?: string;
}

export interface TrackingSessionSimulation {
  forced_speed_kmh: number | null;
  forced_deceleration: boolean;
  forced_route_deviation: boolean;
  speed_multiplier?: number;
}

export interface TrackingSessionTimestamps {
  started_at: string | null;
  paused_at: string | null;
  ended_at: string | null;
}

export interface TrackingSession {
  id: string;
  status: TrackingSessionStatus;
  shipment_order_id: string;
  shipment_id?: string | null;
  assignment_id?: string | null;
  driver: TrackingSessionDriver | null;
  vehicle: TrackingSessionVehicle | null;
  trailer: TrackingSessionTrailer | null;
  simulation: TrackingSessionSimulation;
  timestamps: TrackingSessionTimestamps;
}

export interface TrackingSessionOverridePayload {
  forced_speed_kmh?: number | null;
  forced_deceleration?: boolean;
  forced_route_deviation?: boolean;
}

export interface TrackingSessionEventItem {
  id: number;
  tracking_session_id: string;
  event_type: string;
  payload: Record<string, unknown> | null;
  created_at: string;
}
