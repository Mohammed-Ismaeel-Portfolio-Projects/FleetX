export interface Driver {
  id: string;
  employee_number: string;
  first_name: string;
  last_name: string;
  full_name: string;
  email: string;
  phone: string;
  nationality: string;
  date_of_birth: string | null;
  employment_date: string | null;
  license_number: string;
  license_class: string;
  license_expiry_date: string | null;
  medical_certificate_expiry: string | null;
  years_of_experience: number | null;
  status_id: number;
  is_active: boolean;
  emergency_contact_name: string | null;
  emergency_contact_phone: string | null;
  notes: string | null;
  deleted_at?: string | null;
  created_at: string;
  updated_at: string;
  /** Present when backend eager-loads activeAssignment (GetDriverAction). */
  has_active_assignment?: boolean;
  active_assignment?: {
    id: string | number;
    assignment_code?: string | null;
    is_active?: boolean;
    vehicle?: {
      id?: string;
      operational_code?: string | null;
      plate_number?: string | null;
    } | null;
    shipment?: {
      id?: string;
      shipment_number?: string | null;
      pickup_city?: string | null;
      delivery_city?: string | null;
    } | null;
  } | null;
}

export interface DriverCollection {
  data: Driver[];
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

export type DriverSortField =
  | 'employee_number'
  | 'first_name'
  | 'last_name'
  | 'email'
  | 'employment_date'
  | 'years_of_experience'
  | 'created_at'
  | 'updated_at';

export interface DriverQueryParams {
  page?: number;
  per_page?: number;
  search?: string;
  nationality?: string;
  license_class?: string;
  status_id?: number;
  is_active?: boolean;
  min_experience?: number;
  max_experience?: number;
  sort_by?: DriverSortField;
  sort_direction?: 'asc' | 'desc';
}

export interface DriverFormData {
  employee_number: string;
  first_name: string;
  last_name: string;
  email: string;
  phone: string;
  nationality: string;
  date_of_birth: string;
  employment_date: string;
  license_number: string;
  license_class: string;
  license_expiry_date: string;
  medical_certificate_expiry: string;
  years_of_experience: string;
  status_id: string;
  is_active: boolean;
  emergency_contact_name: string;
  emergency_contact_phone: string;
  notes: string;
}

export interface DriverPayload {
  employee_number: string;
  first_name: string;
  last_name: string;
  email: string;
  phone: string;
  nationality: string;
  date_of_birth: string;
  employment_date: string;
  license_number: string;
  license_class: string;
  license_expiry_date: string;
  medical_certificate_expiry?: string;
  years_of_experience?: number;
  status_id: number;
  is_active?: boolean;
  emergency_contact_name?: string;
  emergency_contact_phone?: string;
  notes?: string;
}

export interface DriverValidationErrors {
  employee_number?: string[];
  first_name?: string[];
  last_name?: string[];
  email?: string[];
  phone?: string[];
  nationality?: string[];
  date_of_birth?: string[];
  employment_date?: string[];
  license_number?: string[];
  license_class?: string[];
  license_expiry_date?: string[];
  medical_certificate_expiry?: string[];
  years_of_experience?: string[];
  status_id?: string[];
  is_active?: string[];
  emergency_contact_name?: string[];
  emergency_contact_phone?: string[];
  notes?: string[];
}

export interface DriverStatusToggleResponse {
  id: string;
  is_active: boolean;
}
