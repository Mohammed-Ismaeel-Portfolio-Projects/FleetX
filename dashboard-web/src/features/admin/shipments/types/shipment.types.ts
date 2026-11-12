export interface Shipment {
  id: string;
  shipment_number: string;
  customer_company_id: string | null;
  customer_company?: {
    id: string;
    name: string;
    company_code: string;
  };
  reference_number: string | null;
  priority: 'low' | 'normal' | 'high' | 'urgent';
  status_id: number;
  status?: {
    id: number;
    name: string;
  };
  cargo_type: string | null;
  cargo_description: string | null;
  cargo_weight: number | null;
  cargo_volume: number | null;
  package_count: number | null;
  hazardous_cargo: boolean;
  temperature_controlled: boolean;
  pickup_company: string | null;
  pickup_contact: string | null;
  pickup_phone: string | null;
  pickup_address: string | null;
  pickup_city: string | null;
  pickup_country: string | null;
  delivery_company: string | null;
  delivery_contact: string | null;
  delivery_phone: string | null;
  delivery_address: string | null;
  delivery_city: string | null;
  delivery_country: string | null;
  planned_pickup_date: string | null;
  planned_delivery_date: string | null;
  actual_pickup_date: string | null;
  actual_delivery_date: string | null;
  special_instructions: string | null;
  notes: string | null;
  created_by: string | null;
  creator?: {
    id: string;
    name: string;
    email: string;
  };
  updated_by: string | null;
  updater?: {
    id: string;
    name: string;
    email: string;
  };
  created_at: string;
  updated_at: string;
  deleted_at: string | null;
}

export interface ShipmentCollection {
  data: Shipment[];
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

export type ShipmentSortField = 'shipment_number' | 'priority' | 'planned_pickup_date' | 'planned_delivery_date' | 'created_at' | 'updated_at';

export interface ShipmentQueryParams {
  page?: number;
  per_page?: number;
  search?: string;
  customer_company_id?: string;
  status_id?: number;
  priority?: 'low' | 'normal' | 'high' | 'urgent';
  country?: string;
  pickup_from?: string;
  pickup_to?: string;
  delivery_from?: string;
  delivery_to?: string;
  sort_by?: ShipmentSortField;
  sort_direction?: 'asc' | 'desc';
}

export interface ShipmentCreatePayload {
  customer_company_id: string;
  reference_number?: string;
  priority: 'low' | 'normal' | 'high' | 'urgent';
  status_id: number;
  cargo_type?: string;
  cargo_description?: string;
  cargo_weight?: number;
  cargo_volume?: number;
  package_count?: number;
  hazardous_cargo?: boolean;
  temperature_controlled?: boolean;
  pickup_company?: string;
  pickup_contact?: string;
  pickup_phone?: string;
  pickup_address?: string;
  pickup_city?: string;
  pickup_country?: string;
  delivery_company?: string;
  delivery_contact?: string;
  delivery_phone?: string;
  delivery_address?: string;
  delivery_city?: string;
  delivery_country?: string;
  planned_pickup_date?: string;
  planned_delivery_date?: string;
  actual_pickup_date?: string;
  actual_delivery_date?: string;
  special_instructions?: string;
  notes?: string;
}

export interface ShipmentUpdatePayload {
  customer_company_id?: string;
  reference_number?: string;
  priority?: 'low' | 'normal' | 'high' | 'urgent';
  status_id?: number;
  shipment_number?: string;
  cargo_type?: string;
  cargo_description?: string;
  cargo_weight?: number;
  cargo_volume?: number;
  package_count?: number;
  hazardous_cargo?: boolean;
  temperature_controlled?: boolean;
  pickup_company?: string;
  pickup_contact?: string;
  pickup_phone?: string;
  pickup_address?: string;
  pickup_city?: string;
  pickup_country?: string;
  delivery_company?: string;
  delivery_contact?: string;
  delivery_phone?: string;
  delivery_address?: string;
  delivery_city?: string;
  delivery_country?: string;
  planned_pickup_date?: string;
  planned_delivery_date?: string;
  actual_pickup_date?: string;
  actual_delivery_date?: string;
  special_instructions?: string;
  notes?: string;
}

export interface ShipmentStatusChangePayload {
  status_id: number;
  actual_pickup_date?: string;
  actual_delivery_date?: string;
}

export interface ShipmentValidationError {
  field: string;
  message: string;
}

export interface ShipmentApiError extends Error {
  status?: number;
  errors?: ShipmentValidationError[];
}
