import type { FormEvent } from 'react';
import type {
  Vehicle,
  VehicleFormData,
  VehiclePayload,
  VehicleValidationErrors,
} from '../types/vehicle.types';

export interface LookupOption {
  value: string;
  label: string;
}

export const emptyVehicleForm: VehicleFormData = {
  operational_code: '',
  fleet_number: '',
  plate_number: '',
  vin: '',
  manufacturer: '',
  model: '',
  production_year: '',
  vehicle_type_id: '',
  fuel_type_id: '',
  transmission: '',
  axle_count: '',
  payload_capacity_kg: '',
  gross_weight_kg: '',
  fuel_tank_capacity_l: '',
  current_odometer: '',
  status_id: '',
  is_active: true,
  gps_device_identifier: '',
  insurance_expiry_date: '',
  registration_expiry_date: '',
  inspection_expiry_date: '',
  notes: '',
};

export function vehicleToFormData(vehicle?: Vehicle | null): VehicleFormData {
  if (!vehicle) {
    return emptyVehicleForm;
  }

  return {
    operational_code: vehicle.operational_code,
    fleet_number: vehicle.fleet_number,
    plate_number: vehicle.plate_number,
    vin: vehicle.vin || '',
    manufacturer: vehicle.manufacturer,
    model: vehicle.model,
    production_year: vehicle.production_year?.toString() ?? '',
    vehicle_type_id: vehicle.vehicle_type_id.toString(),
    fuel_type_id: vehicle.fuel_type_id.toString(),
    transmission: vehicle.transmission || '',
    axle_count: vehicle.axle_count?.toString() ?? '',
    payload_capacity_kg: vehicle.payload_capacity_kg?.toString() ?? '',
    gross_weight_kg: vehicle.gross_weight_kg?.toString() ?? '',
    fuel_tank_capacity_l: vehicle.fuel_tank_capacity_l?.toString() ?? '',
    current_odometer: vehicle.current_odometer?.toString() ?? '',
    status_id: vehicle.status_id.toString(),
    is_active: vehicle.is_active,
    gps_device_identifier: vehicle.gps_device_identifier || '',
    insurance_expiry_date: vehicle.insurance_expiry_date || '',
    registration_expiry_date: vehicle.registration_expiry_date || '',
    inspection_expiry_date: vehicle.inspection_expiry_date || '',
    notes: vehicle.notes || '',
  };
}

export function formDataToPayload(formData: VehicleFormData): VehiclePayload {
  const nullableString = (value: string) => {
    const trimmed = value.trim();
    return trimmed === '' ? null : trimmed;
  };

  const nullableNumber = (value: string) => {
    const trimmed = value.trim();
    return trimmed === '' ? null : Number(trimmed);
  };

  return {
    operational_code: formData.operational_code.trim(),
    fleet_number: formData.fleet_number.trim(),
    plate_number: formData.plate_number.trim(),
    vin: nullableString(formData.vin)?.toUpperCase() ?? null,
    manufacturer: formData.manufacturer.trim(),
    model: formData.model.trim(),
    production_year: nullableNumber(formData.production_year),
    vehicle_type_id: formData.vehicle_type_id === '' ? null : Number(formData.vehicle_type_id),
    fuel_type_id: formData.fuel_type_id === '' ? null : Number(formData.fuel_type_id),
    transmission: nullableString(formData.transmission),
    axle_count: nullableNumber(formData.axle_count),
    payload_capacity_kg: nullableNumber(formData.payload_capacity_kg),
    gross_weight_kg: nullableNumber(formData.gross_weight_kg),
    fuel_tank_capacity_l: nullableNumber(formData.fuel_tank_capacity_l),
    current_odometer: nullableNumber(formData.current_odometer),
    status_id: formData.status_id === '' ? null : Number(formData.status_id),
    is_active: formData.is_active,
    gps_device_identifier: nullableString(formData.gps_device_identifier)?.toUpperCase() ?? null,
    insurance_expiry_date: nullableString(formData.insurance_expiry_date),
    registration_expiry_date: nullableString(formData.registration_expiry_date),
    inspection_expiry_date: nullableString(formData.inspection_expiry_date),
    notes: nullableString(formData.notes),
  };
}

interface VehicleFormProps {
  formData: VehicleFormData;
  errors: VehicleValidationErrors;
  isSubmitting: boolean;
  submitLabel: string;
  vehicleTypeOptions: LookupOption[];
  fuelTypeOptions: LookupOption[];
  statusOptions: LookupOption[];
  onChange: (formData: VehicleFormData) => void;
  onSubmit: (payload: VehiclePayload) => void;
  onCancel: () => void;
}

const fieldClass = 'w-full rounded-xl border border-white/5 bg-[#090D16] px-3.5 py-2.5 text-sm text-white outline-none transition-all placeholder:text-slate-600 focus:border-[#0250C5]/60 focus:ring-2 focus:ring-[#0250C5]/10 disabled:cursor-not-allowed disabled:opacity-60';
const labelClass = 'text-[10px] font-bold uppercase tracking-widest text-slate-500';

function FieldError({ name, errors }: { name: keyof VehicleFormData; errors: VehicleValidationErrors }) {
  const messages = errors[name];

  if (!messages?.length) {
    return null;
  }

  return <p className="text-[11px] font-medium text-red-300">{messages[0]}</p>;
}

export default function VehicleForm({
  formData,
  errors,
  isSubmitting,
  submitLabel,
  vehicleTypeOptions,
  fuelTypeOptions,
  statusOptions,
  onChange,
  onSubmit,
  onCancel,
}: VehicleFormProps) {
  const updateField = <Key extends keyof VehicleFormData>(field: Key, value: VehicleFormData[Key]) => {
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
          <span className={labelClass}>Operational Code</span>
          <input
            value={formData.operational_code}
            onChange={(event) => updateField('operational_code', event.target.value)}
            disabled={isSubmitting}
            className={`${fieldClass} font-mono uppercase`}
            autoFocus
          />
          <FieldError name="operational_code" errors={errors} />
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
          <span className={labelClass}>VIN</span>
          <input
            value={formData.vin}
            onChange={(event) => updateField('vin', event.target.value)}
            disabled={isSubmitting}
            className={`${fieldClass} font-mono uppercase`}
          />
          <FieldError name="vin" errors={errors} />
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
          <span className={labelClass}>Vehicle Type</span>
          <select
            value={formData.vehicle_type_id}
            onChange={(event) => updateField('vehicle_type_id', event.target.value)}
            disabled={isSubmitting}
            className={fieldClass}
          >
            <option value="">Select a vehicle type</option>
            {vehicleTypeOptions.map((option) => (
              <option key={option.value} value={option.value}>{option.label}</option>
            ))}
          </select>
          <FieldError name="vehicle_type_id" errors={errors} />
        </label>

        <label className="flex flex-col gap-2">
          <span className={labelClass}>Fuel Type</span>
          <select
            value={formData.fuel_type_id}
            onChange={(event) => updateField('fuel_type_id', event.target.value)}
            disabled={isSubmitting}
            className={fieldClass}
          >
            <option value="">Select a fuel type</option>
            {fuelTypeOptions.map((option) => (
              <option key={option.value} value={option.value}>{option.label}</option>
            ))}
          </select>
          <FieldError name="fuel_type_id" errors={errors} />
        </label>

        <label className="flex flex-col gap-2">
          <span className={labelClass}>Transmission</span>
          <select
            value={formData.transmission}
            onChange={(event) => updateField('transmission', event.target.value)}
            disabled={isSubmitting}
            className={fieldClass}
          >
            <option value="">Select transmission</option>
            <option value="manual">Manual</option>
            <option value="automatic">Automatic</option>
            <option value="automated_manual">Automated Manual</option>
          </select>
          <FieldError name="transmission" errors={errors} />
        </label>

        <label className="flex flex-col gap-2">
          <span className={labelClass}>Axle Count</span>
          <input
            type="number"
            value={formData.axle_count}
            onChange={(event) => updateField('axle_count', event.target.value)}
            disabled={isSubmitting}
            className={fieldClass}
            min={2}
            max={12}
          />
          <FieldError name="axle_count" errors={errors} />
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
          <span className={labelClass}>Gross Weight (kg)</span>
          <input
            type="number"
            value={formData.gross_weight_kg}
            onChange={(event) => updateField('gross_weight_kg', event.target.value)}
            disabled={isSubmitting}
            className={fieldClass}
            min={1}
          />
          <FieldError name="gross_weight_kg" errors={errors} />
        </label>

        <label className="flex flex-col gap-2">
          <span className={labelClass}>Fuel Tank Capacity (L)</span>
          <input
            type="number"
            value={formData.fuel_tank_capacity_l}
            onChange={(event) => updateField('fuel_tank_capacity_l', event.target.value)}
            disabled={isSubmitting}
            className={fieldClass}
            min={1}
          />
          <FieldError name="fuel_tank_capacity_l" errors={errors} />
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
          <span className={labelClass}>Status</span>
          <select
            value={formData.status_id}
            onChange={(event) => updateField('status_id', event.target.value)}
            disabled={isSubmitting}
            className={fieldClass}
          >
            <option value="">Select a vehicle status</option>
            {statusOptions.map((option) => (
              <option key={option.value} value={option.value}>{option.label}</option>
            ))}
          </select>
          <FieldError name="status_id" errors={errors} />
        </label>

        <label className="flex items-center justify-between gap-4 rounded-xl border border-white/5 bg-[#090D16] px-4 py-3">
          <span className="flex flex-col gap-1">
            <span className={labelClass}>Active</span>
            <span className="text-xs text-slate-500">Toggle vehicle activation state.</span>
          </span>
          <input
            type="checkbox"
            checked={formData.is_active}
            onChange={(event) => updateField('is_active', event.target.checked)}
            disabled={isSubmitting}
            className="h-5 w-5 accent-[#0250C5]"
          />
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

        <label className="flex flex-col gap-2 md:col-span-2">
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
