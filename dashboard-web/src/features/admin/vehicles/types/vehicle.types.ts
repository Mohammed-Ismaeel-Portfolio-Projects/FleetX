export interface Vehicle {
  id: string;
  operational_code: string;
  fleet_number: string;
  plate_number: string;
  vin: string | null;
  manufacturer: string;
  model: string;
  production_year: number | null;
  vehicle_type_id: number;
  driver_id?: number | null;
  driver?: {
    id: number;
    full_name?: string;
    first_name?: string;
    last_name?: string;
    employee_number?: string;
    phone?: string;
    license_number?: string;
    status?: string;
  } | null;
  fuel_type_id: number;
  transmission: string | null;
  axle_count: number | null;
  payload_capacity_kg: number | null;
  gross_weight_kg: number | null;
  fuel_tank_capacity_l: number | null;
  current_odometer: number | null;
  status_id: number;
  is_active: boolean;
  gps_device_identifier: string | null;
  insurance_expiry_date: string | null;
  registration_expiry_date: string | null;
  inspection_expiry_date: string | null;
  notes: string | null;
  deleted_at?: string | null;
  created_at: string;
  updated_at: string;
}

export interface VehicleFormData {
  operational_code: string;
  fleet_number: string;
  plate_number: string;
  vin: string;
  manufacturer: string;
  model: string;
  production_year: string;
  vehicle_type_id: string;
  fuel_type_id: string;
  transmission: string;
  axle_count: string;
  payload_capacity_kg: string;
  gross_weight_kg: string;
  fuel_tank_capacity_l: string;
  current_odometer: string;
  status_id: string;
  is_active: boolean;
  gps_device_identifier: string;
  insurance_expiry_date: string;
  registration_expiry_date: string;
  inspection_expiry_date: string;
  notes: string;
}

export interface VehicleCollection {
  data: Vehicle[];
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

export type VehicleSortField =
  | 'operational_code'
  | 'fleet_number'
  | 'plate_number'
  | 'manufacturer'
  | 'model'
  | 'production_year'
  | 'current_odometer'
  | 'created_at'
  | 'updated_at';

export interface VehicleQueryParams {
  page?: number;
  per_page?: number;
  search?: string;
  vehicle_type_id?: number;
  fuel_type_id?: number;
  status_id?: number;
  manufacturer?: string;
  is_active?: boolean;
  sort_by?: VehicleSortField;
  sort_direction?: 'asc' | 'desc';
}

export type VehiclePayload = Partial<Omit<Vehicle, 'id' | 'created_at' | 'updated_at' | 'vehicle_type_id' | 'fuel_type_id' | 'status_id'>> & {
  vehicle_type_id?: number | null;
  fuel_type_id?: number | null;
  status_id?: number | null;
};

export type VehicleValidationErrors = Record<string, string[]>;

export type VehicleStatusToggleResponse = Pick<Vehicle, 'id' | 'is_active'>;
