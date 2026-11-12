import type { FormEvent } from 'react';
import type {
  Driver,
  DriverFormData,
  DriverPayload,
  DriverValidationErrors,
} from '../types/driver.types';

export interface LookupOption {
  value: string;
  label: string;
}

export const emptyDriverForm: DriverFormData = {
  employee_number: '',
  first_name: '',
  last_name: '',
  email: '',
  phone: '',
  nationality: '',
  date_of_birth: '',
  employment_date: '',
  license_number: '',
  license_class: '',
  license_expiry_date: '',
  medical_certificate_expiry: '',
  years_of_experience: '',
  status_id: '',
  is_active: true,
  emergency_contact_name: '',
  emergency_contact_phone: '',
  notes: '',
};

export function driverToFormData(driver?: Driver | null): DriverFormData {
  if (!driver) {
    return emptyDriverForm;
  }

  return {
    employee_number: driver.employee_number,
    first_name: driver.first_name,
    last_name: driver.last_name,
    email: driver.email,
    phone: driver.phone,
    nationality: driver.nationality,
    date_of_birth: driver.date_of_birth ?? '',
    employment_date: driver.employment_date ?? '',
    license_number: driver.license_number,
    license_class: driver.license_class,
    license_expiry_date: driver.license_expiry_date ?? '',
    medical_certificate_expiry: driver.medical_certificate_expiry ?? '',
    years_of_experience: driver.years_of_experience?.toString() ?? '',
    status_id: driver.status_id.toString(),
    is_active: driver.is_active,
    emergency_contact_name: driver.emergency_contact_name ?? '',
    emergency_contact_phone: driver.emergency_contact_phone ?? '',
    notes: driver.notes ?? '',
  };
}

export function formDataToPayload(formData: DriverFormData): DriverPayload {
  const nullableString = (value: string) => {
    const trimmed = value.trim();
    return trimmed === '' ? undefined : trimmed;
  };

  return {
    employee_number: formData.employee_number.trim(),
    first_name: formData.first_name.trim(),
    last_name: formData.last_name.trim(),
    email: formData.email.trim().toLowerCase(),
    phone: formData.phone.trim(),
    nationality: formData.nationality.trim(),
    date_of_birth: formData.date_of_birth.trim(),
    employment_date: formData.employment_date.trim(),
    license_number: formData.license_number.trim().toUpperCase(),
    license_class: formData.license_class.trim(),
    license_expiry_date: formData.license_expiry_date.trim(),
    medical_certificate_expiry: nullableString(formData.medical_certificate_expiry),
    years_of_experience: formData.years_of_experience.trim() === '' ? undefined : Number(formData.years_of_experience),
    status_id: Number(formData.status_id),
    is_active: formData.is_active,
    emergency_contact_name: nullableString(formData.emergency_contact_name),
    emergency_contact_phone: nullableString(formData.emergency_contact_phone),
    notes: nullableString(formData.notes),
  };
}

interface DriverFormProps {
  formData: DriverFormData;
  errors: DriverValidationErrors;
  isSubmitting: boolean;
  submitLabel: string;
  statusOptions: LookupOption[];
  onChange: (formData: DriverFormData) => void;
  onSubmit: (payload: DriverPayload) => void;
  onCancel: () => void;
}

const fieldClass = 'w-full rounded-xl border border-white/5 bg-[#090D16] px-3.5 py-2.5 text-sm text-white outline-none transition-all placeholder:text-slate-600 focus:border-[#0250C5]/60 focus:ring-2 focus:ring-[#0250C5]/10 disabled:cursor-not-allowed disabled:opacity-60';
const labelClass = 'text-[10px] font-bold uppercase tracking-widest text-slate-500';

function FieldError({ name, errors }: { name: keyof DriverValidationErrors; errors: DriverValidationErrors }) {
  const messages = errors[name];

  if (!messages?.length) {
    return null;
  }

  return <p className="text-[11px] font-medium text-red-300">{messages[0]}</p>;
}

export default function DriverForm({
  formData,
  errors,
  isSubmitting,
  submitLabel,
  statusOptions,
  onChange,
  onSubmit,
  onCancel,
}: DriverFormProps) {
  const updateField = <Key extends keyof DriverFormData>(field: Key, value: DriverFormData[Key]) => {
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
          <span className={labelClass}>Employee Number</span>
          <input
            value={formData.employee_number}
            onChange={(event) => updateField('employee_number', event.target.value)}
            disabled={isSubmitting}
            className={`${fieldClass} font-mono uppercase`}
            autoFocus
          />
          <FieldError name="employee_number" errors={errors} />
        </label>

        <label className="flex flex-col gap-2">
          <span className={labelClass}>First Name</span>
          <input
            value={formData.first_name}
            onChange={(event) => updateField('first_name', event.target.value)}
            disabled={isSubmitting}
            className={fieldClass}
          />
          <FieldError name="first_name" errors={errors} />
        </label>

        <label className="flex flex-col gap-2">
          <span className={labelClass}>Last Name</span>
          <input
            value={formData.last_name}
            onChange={(event) => updateField('last_name', event.target.value)}
            disabled={isSubmitting}
            className={fieldClass}
          />
          <FieldError name="last_name" errors={errors} />
        </label>

        <label className="flex flex-col gap-2">
          <span className={labelClass}>Email</span>
          <input
            type="email"
            value={formData.email}
            onChange={(event) => updateField('email', event.target.value)}
            disabled={isSubmitting}
            className={fieldClass}
          />
          <FieldError name="email" errors={errors} />
        </label>

        <label className="flex flex-col gap-2">
          <span className={labelClass}>Phone</span>
          <input
            value={formData.phone}
            onChange={(event) => updateField('phone', event.target.value)}
            disabled={isSubmitting}
            className={fieldClass}
          />
          <FieldError name="phone" errors={errors} />
        </label>

        <label className="flex flex-col gap-2">
          <span className={labelClass}>Nationality</span>
          <input
            value={formData.nationality}
            onChange={(event) => updateField('nationality', event.target.value)}
            disabled={isSubmitting}
            className={fieldClass}
          />
          <FieldError name="nationality" errors={errors} />
        </label>

        <label className="flex flex-col gap-2">
          <span className={labelClass}>Date of Birth</span>
          <input
            type="date"
            value={formData.date_of_birth}
            onChange={(event) => updateField('date_of_birth', event.target.value)}
            disabled={isSubmitting}
            className={fieldClass}
          />
          <FieldError name="date_of_birth" errors={errors} />
        </label>

        <label className="flex flex-col gap-2">
          <span className={labelClass}>Employment Date</span>
          <input
            type="date"
            value={formData.employment_date}
            onChange={(event) => updateField('employment_date', event.target.value)}
            disabled={isSubmitting}
            className={fieldClass}
          />
          <FieldError name="employment_date" errors={errors} />
        </label>

        <label className="flex flex-col gap-2">
          <span className={labelClass}>License Number</span>
          <input
            value={formData.license_number}
            onChange={(event) => updateField('license_number', event.target.value)}
            disabled={isSubmitting}
            className={`${fieldClass} font-mono uppercase`}
          />
          <FieldError name="license_number" errors={errors} />
        </label>

        <label className="flex flex-col gap-2">
          <span className={labelClass}>License Class</span>
          <input
            value={formData.license_class}
            onChange={(event) => updateField('license_class', event.target.value)}
            disabled={isSubmitting}
            className={fieldClass}
          />
          <FieldError name="license_class" errors={errors} />
        </label>

        <label className="flex flex-col gap-2">
          <span className={labelClass}>License Expiry Date</span>
          <input
            type="date"
            value={formData.license_expiry_date}
            onChange={(event) => updateField('license_expiry_date', event.target.value)}
            disabled={isSubmitting}
            className={fieldClass}
          />
          <FieldError name="license_expiry_date" errors={errors} />
        </label>

        <label className="flex flex-col gap-2">
          <span className={labelClass}>Medical Expiry Date</span>
          <input
            type="date"
            value={formData.medical_certificate_expiry}
            onChange={(event) => updateField('medical_certificate_expiry', event.target.value)}
            disabled={isSubmitting}
            className={fieldClass}
          />
          <FieldError name="medical_certificate_expiry" errors={errors} />
        </label>

        <label className="flex flex-col gap-2">
          <span className={labelClass}>Years of Experience</span>
          <input
            type="number"
            value={formData.years_of_experience}
            onChange={(event) => updateField('years_of_experience', event.target.value)}
            disabled={isSubmitting}
            className={fieldClass}
            min={0}
            max={50}
          />
          <FieldError name="years_of_experience" errors={errors} />
        </label>

        <label className="flex flex-col gap-2">
          <span className={labelClass}>Status</span>
          <select
            value={formData.status_id}
            onChange={(event) => updateField('status_id', event.target.value)}
            disabled={isSubmitting}
            className={fieldClass}
          >
            <option value="">Select a status</option>
            {statusOptions.map((option) => (
              <option key={option.value} value={option.value}>{option.label}</option>
            ))}
          </select>
          <FieldError name="status_id" errors={errors} />
        </label>

        <label className="flex flex-col gap-2">
          <span className={labelClass}>Active</span>
          <select
            value={formData.is_active ? 'true' : 'false'}
            onChange={(event) => updateField('is_active', event.target.value === 'true')}
            disabled={isSubmitting}
            className={fieldClass}
          >
            <option value="true">Active</option>
            <option value="false">Inactive</option>
          </select>
          <FieldError name="is_active" errors={errors} />
        </label>

        <label className="flex flex-col gap-2">
          <span className={labelClass}>Emergency Contact Name</span>
          <input
            value={formData.emergency_contact_name}
            onChange={(event) => updateField('emergency_contact_name', event.target.value)}
            disabled={isSubmitting}
            className={fieldClass}
          />
          <FieldError name="emergency_contact_name" errors={errors} />
        </label>

        <label className="flex flex-col gap-2">
          <span className={labelClass}>Emergency Contact Phone</span>
          <input
            value={formData.emergency_contact_phone}
            onChange={(event) => updateField('emergency_contact_phone', event.target.value)}
            disabled={isSubmitting}
            className={fieldClass}
          />
          <FieldError name="emergency_contact_phone" errors={errors} />
        </label>
      </div>

      <label className="flex flex-col gap-2">
        <span className={labelClass}>Notes</span>
        <textarea
          value={formData.notes}
          onChange={(event) => updateField('notes', event.target.value)}
          disabled={isSubmitting}
          className={`${fieldClass} min-h-[120px] resize-none`}
        />
        <FieldError name="notes" errors={errors} />
      </label>

      <div className="flex flex-col gap-3 border-t border-white/5 pt-4 xl:flex-row xl:justify-end xl:gap-4">
        <button
          type="button"
          onClick={onCancel}
          disabled={isSubmitting}
          className="rounded-xl border border-white/10 px-4 py-3 text-sm font-bold text-slate-300 transition-colors hover:bg-white/5 hover:text-white disabled:cursor-not-allowed disabled:opacity-50"
        >
          Cancel
        </button>
        <button
          type="submit"
          disabled={isSubmitting}
          className="rounded-xl bg-[#0250C5] px-4 py-3 text-sm font-bold text-white transition-colors hover:bg-[#0244a5] disabled:cursor-not-allowed disabled:opacity-60"
        >
          {submitLabel}
        </button>
      </div>
    </form>
  );
}
