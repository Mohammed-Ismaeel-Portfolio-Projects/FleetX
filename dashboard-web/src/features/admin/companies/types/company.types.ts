export interface Company {
  id: string;
  company_name: string;
  legal_name: string;
  company_code: string;
  tax_number: string | null;
  vat_number: string | null;
  business_registration_number: string | null;
  industry_id: string | null;
  company_size_id: string | null;
  primary_email: string;
  primary_phone: string | null;
  website: string | null;
  country: string;
  state_province: string | null;
  district: string | null;
  city: string;
  building_number: string | null;
  address_line_1: string;
  address_line_2: string | null;
  postal_code: string;
  latitude: number | null;
  longitude: number | null;
  timezone: string | null;
  currency_code: string;
  customer_type?: string | null;
  company_type?: 'internal' | 'client' | 'partner_carrier' | 'hybrid' | string | null;
  language_code: string | null;
  status_id: number;
  is_active: boolean;
  notes: string | null;
  logo_path: string | null;
  created_at: string;
  updated_at: string;
  deleted_at?: string | null;
}

export interface CompanyCollection {
  data: Company[];
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

export interface CompanyQueryParams {
  page?: number;
  per_page?: number;
  search?: string;
  country?: string;
  city?: string;
  company_type?: 'internal' | 'client' | 'partner_carrier' | 'hybrid' | string;
  is_active?: boolean;
  sort_by?: string;
  sort_direction?: 'asc' | 'desc';
}

export interface CompanyFormData {
  company_name: string;
  legal_name: string;
  company_code: string;
  company_type: 'internal' | 'client' | 'partner_carrier' | 'hybrid' | string;
  primary_email: string;
  primary_phone: string;
  website: string;
  country: string;
  state_province: string;
  city: string;
  address_line_1: string;
  postal_code: string;
  currency_code: string;
  language_code: string;
  notes: string;
  status_id: number | '';
  is_active: boolean;
}

export type CompanyPayload = Partial<{
  company_name: string;
  legal_name: string;
  company_code: string;
  company_type: 'internal' | 'client' | 'partner_carrier' | 'hybrid' | string;
  primary_email: string;
  primary_phone: string | null;
  website: string | null;
  country: string;
  state_province: string | null;
  city: string;
  address_line_1: string;
  postal_code: string;
  currency_code: string;
  language_code: string | null;
  notes: string | null;
  status_id: number | null;
  is_active: boolean;
}>;

export type CompanyValidationErrors = Record<string, string[]>;
