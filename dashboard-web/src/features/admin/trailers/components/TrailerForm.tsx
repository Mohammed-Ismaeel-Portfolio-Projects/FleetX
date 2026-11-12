import type { FormEvent } from 'react';
import type {
  Trailer,
  TrailerFormData,
  TrailerPayload,
  TrailerValidationErrors,
} from '../types/trailer.types';

export interface LookupOption {
  value: string;
  label: string;
}

export const emptyTrailerForm: TrailerFormData = {
  trailer_code: '',
  fleet_number: '',
  plate_number: '',
  trailer_type: '',
  manufacturer: '',
  model: '',
  production_year: '',
  axle_count: '',
  payload_capacity_kg: '',
  length_m: '',
  width_m: '',
  height_m: '',
  cargo_volume_m3: '',
  current_odometer: '',
  status_id: '',
  is_active: true,
  gps_device_identifier: '',
  insurance_expiry_date: '',
  registration_expiry_date: '',
  inspection_expiry_date: '',
  notes: '',
};

export function trailerToFormData(trailer?: Trailer | null): TrailerFormData {
  if (!trailer) {
    return emptyTrailerForm;
  }

  return {
    trailer_code: trailer.trailer_code,
    fleet_number: trailer.fleet_number,
    plate_number: trailer.plate_number,
    trailer_type: trailer.trailer_type,
    manufacturer: trailer.manufacturer,
    model: trailer.model || '',
    production_year: trailer.production_year?.toString() ?? '',
    axle_count: trailer.axle_count?.toString() ?? '',
    payload_capacity_kg: trailer.payload_capacity_kg?.toString() ?? '',
    length_m: trailer.length_m?.toString() ?? '',
    width_m: trailer.width_m?.toString() ?? '',
    height_m: trailer.height_m?.toString() ?? '',
    cargo_volume_m3: trailer.cargo_volume_m3?.toString() ?? '',
    current_odometer: trailer.current_odometer?.toString() ?? '',
    status_id: trailer.status_id.toString(),
    is_active: trailer.is_active,
    gps_device_identifier: trailer.gps_device_identifier || '',
    insurance_expiry_date: trailer.insurance_expiry_date || '',
    registration_expiry_date: trailer.registration_expiry_date || '',
    inspection_expiry_date: trailer.inspection_expiry_date || '',
    notes: trailer.notes || '',
  };
}

export function formDataToPayload(formData: TrailerFormData): TrailerPayload {
  const nullableString = (value: string) => {
    const trimmed = value.trim();
    return trimmed === '' ? null : trimmed;
  };

  const nullableNumber = (value: string) => {
    const trimmed = value.trim();
    return trimmed === '' ? null : Number(trimmed);
  };

  return {
    trailer_code: formData.trailer_code.trim(),
    fleet_number: formData.fleet_number.trim(),
    plate_number: formData.plate_number.trim(),
    trailer_type: formData.trailer_type.trim(),
    manufacturer: formData.manufacturer.trim(),
    model: nullableString(formData.model),
    production_year: nullableNumber(formData.production_year),
    axle_count: nullableNumber(formData.axle_count),
    payload_capacity_kg: nullableNumber(formData.payload_capacity_kg),
    length_m: nullableNumber(formData.length_m),
    width_m: nullableNumber(formData.width_m),
    height_m: nullableNumber(formData.height_m),
    cargo_volume_m3: nullableNumber(formData.cargo_volume_m3),
    current_odometer: nullableNumber(formData.current_odometer),
    status_id: formData.status_id === '' ? null : Number(formData.status_id),
    is_active: formData.is_active,
    gps_device_identifier: nullableString(formData.gps_device_identifier),
    insurance_expiry_date: nullableString(formData.insurance_expiry_date),
    registration_expiry_date: nullableString(formData.registration_expiry_date),
    inspection_expiry_date: nullableString(formData.inspection_expiry_date),
    notes: nullableString(formData.notes),
  };
}

interface TrailerFormProps {
  formData: TrailerFormData;
  errors: TrailerValidationErrors;
  isSubmitting: boolean;
  submitLabel: string;
  trailerTypeOptions: LookupOption[];
  statusOptions: LookupOption[];
  onChange: (formData: TrailerFormData) => void;
  onSubmit: (payload: TrailerPayload) => void;
  onCancel: () => void;
}

const fieldClass = 'w-full rounded-xl border border-white/5 bg-[#090D16] px-3.5 py-2.5 text-sm text-white outline-none transition-all placeholder:text-slate-600 focus:border-[#0250C5]/60 focus:ring-2 focus:ring-[#0250C5]/10 disabled:cursor-not-allowed disabled:opacity-60';
const labelClass = 'text-[10px] font-bold uppercase tracking-widest text-slate-500';

function FieldError({ name, errors }: { name: keyof TrailerFormData; errors: TrailerValidationErrors }) {
  const messages = errors[name];

  if (!messages?.length) {
    return null;
  }

  return <p className="text-[11px] font-medium text-red-300">{messages[0]}</p>;
}

export default function TrailerForm({
  formData,
  errors,
  isSubmitting,
  submitLabel,
  trailerTypeOptions,
  statusOptions,
  onChange,
  onSubmit,
  onCancel,
}: TrailerFormProps) {
  const updateField = <Key extends keyof TrailerFormData>(field: Key, value: TrailerFormData[Key]) => {
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
          <span className={labelClass}>Trailer Code</span>
          <input
            value={formData.trailer_code}
            onChange={(event) => updateField('trailer_code', event.target.value)}
            disabled={isSubmitting}
            className={`${fieldClass} font-mono uppercase`}
            autoFocus
          />
          <FieldError name="trailer_code" errors={errors} />
        </label>

        <label className="flex flex-col gap-2">
          <span className={labelClass}>Fleet Number</span>
          <input
            value={formData.fleet_number}
            onChange={(event) => updateField('fleet_number', event.target.value)}
            disabled={isSubmitting}
            className={`${fieldClass} font-mono uppercase`}
          />
          <FieldError name="fleet_number" errors={errors} />
        </label>

        <label className="flex flex-col gap-2">
          <span className={labelClass}>Plate Number</span>
          <input
            value={formData.plate_number}
            onChange={(event) => updateField('plate_number', event.target.value)}
            disabled={isSubmitting}
            className={`${fieldClass} font-mono uppercase`}
          />
          <FieldError name="plate_number" errors={errors} />
        </label>

        <label className="flex flex-col gap-2">
          <span className={labelClass}>Trailer Type</span>
          <select
            value={formData.trailer_type}
            onChange={(event) => updateField('trailer_type', event.target.value)}
            disabled={isSubmitting}
            className={fieldClass}
          >
            <option value="">Select a trailer type</option>
            {trailerTypeOptions.map((option) => (
              <option key={option.value} value={option.value}>{option.label}</option>
            ))}
          </select>
          <FieldError name="trailer_type" errors={errors} />
        </label>

        <label className="flex flex-col gap-2">
          <span className={labelClass}>Manufacturer</span>
          <input
            value={formData.manufacturer}
            onChange={(event) => updateField('manufacturer', event.target.value)}
            disabled={isSubmitting}
            className={fieldClass}
          />
          <FieldError name="manufacturer" errors={errors} />
        </label>

        <label className="flex flex-col gap-2">
          <span className={labelClass}>Model</span>
          <input
            value={formData.model}
            onChange={(event) => updateField('model', event.target.value)}
            disabled={isSubmitting}
            className={fieldClass}
          />
          <FieldError name="model" errors={errors} />
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
          <span className={labelClass}>Production Year</span>
          <input
            type="number"
            value={formData.production_year}
            onChange={(event) => updateField('production_year', event.target.value)}
            disabled={isSubmitting}
            className={fieldClass}
            min={1980}
            max={new Date().getFullYear() + 1}
          />
          <FieldError name="production_year" errors={errors} />
        </label>

        <label className="flex flex-col gap-2">
          <span className={labelClass}>Axle Count</span>
          <input
            type="number"
            value={formData.axle_count}
            onChange={(event) => updateField('axle_count', event.target.value)}
            disabled={isSubmitting}
            className={fieldClass}
            min={1}
            max={10}
          />
          <FieldError name="axle_count" errors={errors} />
        </label>

        <label className="flex flex-col gap-2">
          <span className={labelClass}>Payload Capacity (kg)</span>
          <input
            type="number"
            value={formData.payload_capacity_kg}
            onChange={(event) => updateField('payload_capacity_kg', event.target.value)}
            disabled={isSubmitting}
            className={fieldClass}
            min={1}
          />
          <FieldError name="payload_capacity_kg" errors={errors} />
        </label>

        <label className="flex flex-col gap-2">
          <span className={labelClass}>Length (m)</span>
          <input
            type="number"
            value={formData.length_m}
            onChange={(event) => updateField('length_m', event.target.value)}
            disabled={isSubmitting}
            className={fieldClass}
            min={1}
            step="0.1"
          />
          <FieldError name="length_m" errors={errors} />
        </label>

        <label className="flex flex-col gap-2">
          <span className={labelClass}>Width (m)</span>
          <input
            type="number"
            value={formData.width_m}
            onChange={(event) => updateField('width_m', event.target.value)}
            disabled={isSubmitting}
            className={fieldClass}
            min={1}
            step="0.1"
          />
          <FieldError name="width_m" errors={errors} />
        </label>

        <label className="flex flex-col gap-2">
          <span className={labelClass}>Height (m)</span>
          <input
            type="number"
            value={formData.height_m}
            onChange={(event) => updateField('height_m', event.target.value)}
            disabled={isSubmitting}
            className={fieldClass}
            min={1}
            step="0.1"
          />
          <FieldError name="height_m" errors={errors} />
        </label>

        <label className="flex flex-col gap-2">
          <span className={labelClass}>Cargo Volume (m³)</span>
          <input
            type="number"
            value={formData.cargo_volume_m3}
            onChange={(event) => updateField('cargo_volume_m3', event.target.value)}
            disabled={isSubmitting}
            className={fieldClass}
            min={1}
            step="0.1"
          />
          <FieldError name="cargo_volume_m3" errors={errors} />
        </label>

        <label className="flex flex-col gap-2">
          <span className={labelClass}>Current Odometer</span>
          <input
            type="number"
            value={formData.current_odometer}
            onChange={(event) => updateField('current_odometer', event.target.value)}
            disabled={isSubmitting}
            className={fieldClass}
            min={0}
          />
          <FieldError name="current_odometer" errors={errors} />
        </label>

        <label className="flex flex-col gap-2">
          <span className={labelClass}>GPS Device Identifier</span>
          <input
            value={formData.gps_device_identifier}
            onChange={(event) => updateField('gps_device_identifier', event.target.value)}
            disabled={isSubmitting}
            className={fieldClass}
          />
          <FieldError name="gps_device_identifier" errors={errors} />
        </label>

        <label className="flex flex-col gap-2">
          <span className={labelClass}>Insurance Expiry</span>
          <input
            type="date"
            value={formData.insurance_expiry_date}
            onChange={(event) => updateField('insurance_expiry_date', event.target.value)}
            disabled={isSubmitting}
            className={fieldClass}
          />
          <FieldError name="insurance_expiry_date" errors={errors} />
        </label>

        <label className="flex flex-col gap-2">
          <span className={labelClass}>Registration Expiry</span>
          <input
            type="date"
            value={formData.registration_expiry_date}
            onChange={(event) => updateField('registration_expiry_date', event.target.value)}
            disabled={isSubmitting}
            className={fieldClass}
          />
          <FieldError name="registration_expiry_date" errors={errors} />
        </label>

        <label className="flex flex-col gap-2">
          <span className={labelClass}>Inspection Expiry</span>
          <input
            type="date"
            value={formData.inspection_expiry_date}
            onChange={(event) => updateField('inspection_expiry_date', event.target.value)}
            disabled={isSubmitting}
            className={fieldClass}
          />
          <FieldError name="inspection_expiry_date" errors={errors} />
        </label>
      </div>

      <label className="flex flex-col gap-2">
        <span className={labelClass}>Notes</span>
        <textarea
          value={formData.notes}
          onChange={(event) => updateField('notes', event.target.value)}
          disabled={isSubmitting}
          className={`${fieldClass} min-h-30 resize-none`}
        />
        <FieldError name="notes" errors={errors} />
      </label>

      <div className="flex flex-col gap-3 sm:flex-row sm:justify-end">
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
          className="inline-flex items-center justify-center gap-2 rounded-xl bg-secondary px-4 py-2.5 text-sm font-bold text-white transition-colors hover:bg-[#0244a5] disabled:cursor-not-allowed disabled:opacity-60"
        >
          {isSubmitting && <span className="h-4 w-4 rounded-full border-2 border-white/40 border-t-transparent animate-spin" />}
          {submitLabel}
        </button>
      </div>
    </form>
  );
}
