export type MovementStatus = 'Idle' | 'Moving' | 'Stopped';
export type OnlineStatus = 'Online' | 'Offline';
export type SignalQuality = 'Good' | 'Weak' | 'Lost';

export interface TrackingState {
  id: string;
  assignment_id: number;
  shipment_id: string;
  vehicle_id: string;
  driver_id: number;
  trailer_id: string | null;
  latitude: number;
  longitude: number;
  speed: number;
  heading: number | null;
  accuracy: number | null;
  movement_status: MovementStatus;
  online_status: OnlineStatus;
  signal_quality: SignalQuality;
  last_update_at: string | null;
  created_at: string;
  updated_at: string;
  assignment?: Assignment;
  shipment?: Shipment;
  vehicle?: Vehicle;
  driver?: Driver;
  trailer?: Trailer;
}

export interface Assignment {
  id: number;
  shipment_id: string;
  assignment_code: string;
  driver_id: number;
  vehicle_id: string;
  trailer_id: string | null;
  status_id: number;
  is_active: boolean;
  assigned_at: string;
  ended_at: string | null;
}

export interface Shipment {
  id: string;
  shipment_number: string;
  reference_number: string | null;
  priority: 'low' | 'normal' | 'high' | 'urgent';
}

export interface Vehicle {
  id: string;
  fleet_number: string;
  plate_number: string;
  manufacturer: string;
  model: string;
}

export interface Driver {
  id: number;
  employee_number: string;
  full_name: string;
  email: string;
  phone: string;
}

export interface Trailer {
  id: string;
  trailer_code: string;
  fleet_number: string;
  plate_number: string;
}

export interface TrackingStateCollection {
  data: TrackingState[];
  meta: {
    current_page: number;
    per_page: number;
    total: number;
    last_page: number;
    from: number | null;
    to: number | null;
  };
  links: {
    first: string | null;
    last: string | null;
    prev: string | null;
    next: string | null;
  };
}

export type TrackingSortField = 'last_update_at' | 'created_at' | 'updated_at';

export interface TrackingQueryParams {
  page?: number;
  per_page?: number;
  search?: string;
  assignment_id?: number;
  shipment_id?: string;
  vehicle_id?: string;
  driver_id?: number;
  movement_status?: MovementStatus;
  online_status?: OnlineStatus;
  signal_quality?: SignalQuality;
  sort_by?: TrackingSortField;
  sort_direction?: 'asc' | 'desc';
}

export interface TrackingStatistics {
  total: number;
  moving: number;
  stopped: number;
  idle: number;
  offline: number;
}
