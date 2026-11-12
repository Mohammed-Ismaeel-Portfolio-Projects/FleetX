import type { FormEvent } from 'react';
import type {
  Company,
  CompanyFormData,
  CompanyPayload,
  CompanyValidationErrors,
} from '../types/company.types';

export const emptyCompanyForm: CompanyFormData = {
  company_name: '',
  legal_name: '',
  company_code: '',
  company_type: 'client',
  primary_email: '',
  primary_phone: '',
  website: '',
  country: '',
  state_province: '',
  city: '',
  address_line_1: '',
  postal_code: '',
  currency_code: '',
  language_code: '',
  notes: '',
  status_id: '',
  is_active: true,
};

export function companyToFormData(company?: Company | null): CompanyFormData {
  if (!company) {
    return emptyCompanyForm;
  }

  return {
    company_name: company.company_name || '',
    legal_name: company.legal_name || '',
    company_code: company.company_code || '',
    company_type: (company.company_type as any) || 'client',
    primary_email: company.primary_email || '',
    primary_phone: company.primary_phone || '',
    website: company.website || '',
    country: company.country || '',
    state_province: company.state_province || '',
    city: company.city || '',
    address_line_1: company.address_line_1 || '',
    postal_code: company.postal_code || '',
    currency_code: company.currency_code || '',
    language_code: company.language_code || '',
    notes: company.notes || '',
    status_id: company.status_id || '',
    is_active: company.is_active,
  };
}

export function formDataToPayload(formData: CompanyFormData): CompanyPayload {
  const nullable = (value: string) => {
    const trimmed = value.trim();
    return trimmed === '' ? null : trimmed;
  };

  return {
    company_name: formData.company_name.trim(),
    legal_name: formData.legal_name.trim(),
    company_code: formData.company_code.trim(),
    company_type: formData.company_type || 'client',
    primary_email: formData.primary_email.trim(),
    primary_phone: nullable(formData.primary_phone),
    website: nullable(formData.website),
    country: formData.country.trim().toUpperCase(),
    state_province: nullable(formData.state_province),
    city: formData.city.trim(),
    address_line_1: formData.address_line_1.trim(),
    postal_code: formData.postal_code.trim(),
    currency_code: formData.currency_code.trim().toUpperCase(),
    language_code: nullable(formData.language_code),
    notes: nullable(formData.notes),
    status_id: formData.status_id === '' ? null : Number(formData.status_id),
    is_active: formData.is_active,
  };
}

interface CompanyFormProps {
  formData: CompanyFormData;
  errors: CompanyValidationErrors;
  isSubmitting: boolean;
  submitLabel: string;
  onChange: (formData: CompanyFormData) => void;
  onSubmit: (payload: CompanyPayload) => void;
  onCancel: () => void;
}

const fieldClass = 'w-full rounded-xl border border-white/5 bg-[#090D16] px-3.5 py-2.5 text-sm text-white outline-none transition-all placeholder:text-slate-600 focus:border-[#0250C5]/60 focus:ring-2 focus:ring-[#0250C5]/10 disabled:cursor-not-allowed disabled:opacity-60';
const labelClass = 'text-[10px] font-bold uppercase tracking-widest text-slate-500';

function FieldError({ name, errors }: { name: keyof CompanyFormData; errors: CompanyValidationErrors }) {
  const messages = errors[name];

  if (!messages?.length) {
    return null;
  }

  return <p className="text-[11px] font-medium text-red-300">{messages[0]}</p>;
}

export default function CompanyForm({
  formData,
  errors,
  isSubmitting,
  submitLabel,
  onChange,
  onSubmit,
  onCancel,
}: CompanyFormProps) {
  const updateField = <Key extends keyof CompanyFormData>(field: Key, value: CompanyFormData[Key]) => {
    onChange({ ...formData, [field]: value });
  };

  const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    onSubmit(formDataToPayload(formData));
  };

  return (
    <form onSubmit={handleSubmit} className="flex flex-col gap-5" noValidate>
      <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
        <label className="flex flex-col gap-2">
          <span className={labelClass}>Company Name</span>
          <input
            value={formData.company_name}
            onChange={(event) => updateField('company_name', event.target.value)}
            disabled={isSubmitting}
            className={fieldClass}
            autoFocus
          />
          <FieldError name="company_name" errors={errors} />
        </label>

        <label className="flex flex-col gap-2">
          <span className={labelClass}>Legal Name</span>
          <input
            value={formData.legal_name}
            onChange={(event) => updateField('legal_name', event.target.value)}
            disabled={isSubmitting}
            className={fieldClass}
          />
          <FieldError name="legal_name" errors={errors} />
        </label>

        <label className="flex flex-col gap-2">
          <span className={labelClass}>Company Code</span>
          <input
            value={formData.company_code}
            onChange={(event) => updateField('company_code', event.target.value)}
            disabled={isSubmitting}
            className={`${fieldClass} font-mono uppercase`}
          />
          <FieldError name="company_code" errors={errors} />
        </label>

        <label className="flex flex-col gap-2">
          <span className={labelClass}>Company Type</span>
          <select
            value={formData.company_type || 'client'}
            onChange={(event) => updateField('company_type', event.target.value)}
            disabled={isSubmitting}
            className={fieldClass}
          >
            <option value="client">Client Company (B2B Shipper)</option>
            <option value="partner_carrier">Partner Carrier (Fleet Provider)</option>
            <option value="hybrid">Hybrid (Client & Partner)</option>
            <option value="internal">Internal FleetX</option>
          </select>
          <FieldError name="company_type" errors={errors} />
        </label>

        <label className="flex flex-col gap-2">
          <span className={labelClass}>Email</span>
          <input
            value={formData.primary_email}
            onChange={(event) => updateField('primary_email', event.target.value)}
            disabled={isSubmitting}
            className={fieldClass}
            type="email"
          />
          <FieldError name="primary_email" errors={errors} />
        </label>

        <label className="flex flex-col gap-2">
          <span className={labelClass}>Phone</span>
          <input
            value={formData.primary_phone}
            onChange={(event) => updateField('primary_phone', event.target.value)}
            disabled={isSubmitting}
            className={fieldClass}
          />
          <FieldError name="primary_phone" errors={errors} />
        </label>

        <label className="flex flex-col gap-2">
          <span className={labelClass}>Website</span>
          <input
            value={formData.website}
            onChange={(event) => updateField('website', event.target.value)}
            disabled={isSubmitting}
            className={fieldClass}
            type="url"
          />
          <FieldError name="website" errors={errors} />
        </label>

        <label className="flex flex-col gap-2">
          <span className={labelClass}>Country</span>
          <input
            value={formData.country}
            onChange={(event) => updateField('country', event.target.value.slice(0, 2).toUpperCase())}
            disabled={isSubmitting}
            className={`${fieldClass} uppercase`}
            placeholder="US"
          />
          <FieldError name="country" errors={errors} />
        </label>

        <label className="flex flex-col gap-2">
          <span className={labelClass}>State</span>
          <input
            value={formData.state_province}
            onChange={(event) => updateField('state_province', event.target.value)}
            disabled={isSubmitting}
            className={fieldClass}
          />
          <FieldError name="state_province" errors={errors} />
        </label>

        <label className="flex flex-col gap-2">
          <span className={labelClass}>City</span>
          <input
            value={formData.city}
            onChange={(event) => updateField('city', event.target.value)}
            disabled={isSubmitting}
            className={fieldClass}
          />
          <FieldError name="city" errors={errors} />
        </label>

        <label className="flex flex-col gap-2">
          <span className={labelClass}>Postal Code</span>
          <input
            value={formData.postal_code}
            onChange={(event) => updateField('postal_code', event.target.value)}
            disabled={isSubmitting}
            className={fieldClass}
          />
          <FieldError name="postal_code" errors={errors} />
        </label>

        <label className="flex flex-col gap-2 md:col-span-2">
          <span className={labelClass}>Address</span>
          <input
            value={formData.address_line_1}
            onChange={(event) => updateField('address_line_1', event.target.value)}
            disabled={isSubmitting}
            className={fieldClass}
          />
          <FieldError name="address_line_1" errors={errors} />
        </label>

        <label className="flex flex-col gap-2">
          <span className={labelClass}>Currency</span>
          <input
            value={formData.currency_code}
            onChange={(event) => updateField('currency_code', event.target.value.slice(0, 3).toUpperCase())}
            disabled={isSubmitting}
            className={`${fieldClass} uppercase`}
            placeholder="USD"
          />
          <FieldError name="currency_code" errors={errors} />
        </label>

        <label className="flex flex-col gap-2">
          <span className={labelClass}>Language</span>
          <input
            value={formData.language_code}
            onChange={(event) => updateField('language_code', event.target.value.slice(0, 5).toLowerCase())}
            disabled={isSubmitting}
            className={fieldClass}
            placeholder="en"
          />
          <FieldError name="language_code" errors={errors} />
        </label>

        <label className="flex flex-col gap-2">
          <span className={labelClass}>Status</span>
          <input
            value={formData.status_id}
            onChange={(event) => updateField('status_id', event.target.value === '' ? '' : Number(event.target.value))}
            disabled={isSubmitting}
            className={fieldClass}
            type="number"
            min="1"
          />
          <FieldError name="status_id" errors={errors} />
        </label>

        <label className="flex items-center justify-between gap-4 rounded-xl border border-white/5 bg-[#090D16] px-4 py-3">
          <span className="flex flex-col gap-1">
            <span className={labelClass}>Active Toggle</span>
            <span className="text-xs text-slate-500">Enable company access immediately.</span>
          </span>
          <input
            type="checkbox"
            checked={formData.is_active}
            onChange={(event) => updateField('is_active', event.target.checked)}
            disabled={isSubmitting}
            className="h-5 w-5 accent-[#0250C5]"
            aria-label="Company active status"
          />
        </label>

        <label className="flex flex-col gap-2 md:col-span-2">
          <span className={labelClass}>Notes</span>
          <textarea
            value={formData.notes}
            onChange={(event) => updateField('notes', event.target.value)}
            disabled={isSubmitting}
            className={`${fieldClass} min-h-24 resize-y`}
          />
          <FieldError name="notes" errors={errors} />
        </label>
      </div>

      <div className="flex flex-col-reverse gap-3 border-t border-white/5 pt-5 sm:flex-row sm:justify-end">
        <button
          type="button"
          onClick={onCancel}
          disabled={isSubmitting}
          className="rounded-xl border border-white/10 px-4 py-2.5 text-sm font-bold text-slate-300 transition-colors hover:bg-white/5 hover:text-white disabled:cursor-not-allowed disabled:opacity-50"
        >
          Cancel
        </button>
        <button
          type="submit"
          disabled={isSubmitting}
          className="inline-flex items-center justify-center gap-2 rounded-xl bg-[#0250C5] px-4 py-2.5 text-sm font-bold text-white transition-colors hover:bg-[#0244a5] disabled:cursor-not-allowed disabled:opacity-60"
        >
          {isSubmitting && <span className="h-4 w-4 rounded-full border-2 border-white/40 border-t-transparent animate-spin" />}
          {submitLabel}
        </button>
      </div>
    </form>
  );
}
