export interface Trailer {
  id: string;
  trailer_code: string;
  fleet_number: string;
  plate_number: string;
  trailer_type: string;
  manufacturer: string;
  model: string | null;
  production_year: number | null;
  axle_count: number | null;
  payload_capacity_kg: number | null;
  length_m: number | null;
  width_m: number | null;
  height_m: number | null;
  cargo_volume_m3: number | null;
  current_odometer: number | null;
  gps_device_identifier: string | null;
  insurance_expiry_date: string | null;
  inspection_expiry_date: string | null;
  registration_expiry_date: string | null;
  status_id: number;
  is_active: boolean;
  notes: string | null;
  deleted_at?: string | null;
  created_at: string;
  updated_at: string;
}

export interface TrailerCollection {
  data: Trailer[];
  meta: {
    current_page: number;
    per_page: number;
    total: number;
    last_page: number;
    from: number;
    to: number;
  };
  links: {
    first: string | null;
    last: string | null;
    prev: string | null;
    next: string | null;
  };
}

export type TrailerSortField =
  | 'trailer_code'
  | 'fleet_number'
  | 'plate_number'
  | 'trailer_type'
  | 'manufacturer'
  | 'production_year'
  | 'current_odometer'
  | 'created_at'
  | 'updated_at';

export interface TrailerQueryParams {
  page?: number;
  per_page?: number;
  search?: string;
  trailer_type?: string;
  manufacturer?: string;
  status_id?: number;
  is_active?: boolean;
  sort_by?: TrailerSortField;
  sort_direction?: 'asc' | 'desc';
}

export type TrailerPayload = Partial<Omit<Trailer, 'id' | 'created_at' | 'updated_at' | 'status_id'>> & {
  status_id?: number | null;
};

export type TrailerValidationErrors = Record<string, string[]>;

export interface TrailerFormData {
  trailer_code: string;
  fleet_number: string;
  plate_number: string;
  trailer_type: string;
  manufacturer: string;
  model: string;
  production_year: string;
  axle_count: string;
  payload_capacity_kg: string;
  length_m: string;
  width_m: string;
  height_m: string;
  cargo_volume_m3: string;
  current_odometer: string;
  status_id: string;
  is_active: boolean;
  gps_device_identifier: string;
  insurance_expiry_date: string;
  registration_expiry_date: string;
  inspection_expiry_date: string;
  notes: string;
}

export type TrailerStatusToggleResponse = Pick<Trailer, 'id' | 'is_active'>;
