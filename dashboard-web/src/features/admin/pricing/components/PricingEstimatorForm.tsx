import type { Company } from '../../companies/types/company.types';
import type {
  CargoType,
  PricingPolicy,
  PricingPolicySummary,
  ShipmentPriority,
  TrailerType,
} from '../types/pricing.types';
import type { PricingEstimatorFormState } from '../utils/buildPricingEstimatePayload';

const inputClass = 'w-full rounded-xl border border-white/10 bg-[#090D16] px-3 py-2.5 text-sm text-white outline-none placeholder:text-slate-500';
const labelClass = 'mb-1 block text-[10px] uppercase tracking-wide text-slate-500';

const cargoTypes: CargoType[] = ['general', 'refrigerated', 'heavy', 'hazardous', 'fragile'];
const trailerTypes: TrailerType[] = ['dry_van', 'reefer', 'flatbed', 'lowboy', 'tanker'];
const priorities: ShipmentPriority[] = ['standard', 'express', 'urgent', 'emergency'];

interface PricingEstimatorFormProps {
  form: PricingEstimatorFormState;
  companies: Company[];
  policies: PricingPolicySummary[];
  activePolicy: PricingPolicy | null;
  isSubmitting: boolean;
  error: string | null;
  fieldErrors: string[];
  onChange: (updates: Partial<PricingEstimatorFormState>) => void;
  onSubmit: () => void;
}

export default function PricingEstimatorForm({
  form,
  companies,
  policies,
  activePolicy,
  isSubmitting,
  error,
  fieldErrors,
  onChange,
  onSubmit,
}: PricingEstimatorFormProps) {
  const enabledServices = (activePolicy?.service_catalog ?? []).filter((service) => service.enabled);

  const toggleService = (id: number) => {
    const selected = form.selected_service_ids.includes(id)
      ? form.selected_service_ids.filter((item) => item !== id)
      : [...form.selected_service_ids, id];
    onChange({ selected_service_ids: selected });
  };

  return (
    <form
      className="flex flex-col gap-4"
      onSubmit={(event) => {
        event.preventDefault();
        onSubmit();
      }}
    >
      <div className="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-4">
        <div>
          <label className={labelClass}>Pickup latitude</label>
          <input className={inputClass} value={form.pickup_latitude} onChange={(event) => onChange({ pickup_latitude: event.target.value })} />
        </div>
        <div>
          <label className={labelClass}>Pickup longitude</label>
          <input className={inputClass} value={form.pickup_longitude} onChange={(event) => onChange({ pickup_longitude: event.target.value })} />
        </div>
        <div>
          <label className={labelClass}>Delivery latitude</label>
          <input className={inputClass} value={form.delivery_latitude} onChange={(event) => onChange({ delivery_latitude: event.target.value })} />
        </div>
        <div>
          <label className={labelClass}>Delivery longitude</label>
          <input className={inputClass} value={form.delivery_longitude} onChange={(event) => onChange({ delivery_longitude: event.target.value })} />
        </div>
        <div>
          <label className={labelClass}>Cargo type</label>
          <select className={inputClass} value={form.cargo_type} onChange={(event) => onChange({ cargo_type: event.target.value as CargoType })}>
            {cargoTypes.map((type) => <option key={type} value={type}>{type}</option>)}
          </select>
        </div>
        <div>
          <label className={labelClass}>Trailer type</label>
          <select className={inputClass} value={form.trailer_type} onChange={(event) => onChange({ trailer_type: event.target.value as TrailerType })}>
            {trailerTypes.map((type) => <option key={type} value={type}>{type}</option>)}
          </select>
        </div>
        <div>
          <label className={labelClass}>Cargo weight (tons)</label>
          <input className={inputClass} value={form.cargo_weight} onChange={(event) => onChange({ cargo_weight: event.target.value })} />
        </div>
        <div>
          <label className={labelClass}>Shipment priority</label>
          <select className={inputClass} value={form.shipment_priority} onChange={(event) => onChange({ shipment_priority: event.target.value as ShipmentPriority })}>
            {priorities.map((priority) => <option key={priority} value={priority}>{priority}</option>)}
          </select>
        </div>
        <div>
          <label className={labelClass}>Customer company</label>
          <select className={inputClass} value={form.customer_company_id} onChange={(event) => onChange({ customer_company_id: event.target.value })}>
            <option value="">Select company</option>
            {companies.map((company) => (
              <option key={company.id} value={company.id}>{company.company_name}</option>
            ))}
          </select>
        </div>
        <div>
          <label className={labelClass}>Loading wait (min)</label>
          <input className={inputClass} value={form.loading_waiting_time} onChange={(event) => onChange({ loading_waiting_time: event.target.value })} />
        </div>
        <div>
          <label className={labelClass}>Unloading wait (min)</label>
          <input className={inputClass} value={form.unloading_waiting_time} onChange={(event) => onChange({ unloading_waiting_time: event.target.value })} />
        </div>
        <div>
          <label className={labelClass}>Traffic delay (min)</label>
          <input className={inputClass} value={form.traffic_delay} onChange={(event) => onChange({ traffic_delay: event.target.value })} />
        </div>
        <div>
          <label className={labelClass}>Customer delay (min)</label>
          <input className={inputClass} value={form.customer_delay} onChange={(event) => onChange({ customer_delay: event.target.value })} />
        </div>
      </div>

      <div className="flex flex-wrap gap-4 text-sm text-slate-300">
        <label className="flex items-center gap-2">
          <input type="checkbox" checked={form.border_crossing} onChange={(event) => onChange({ border_crossing: event.target.checked })} />
          Border crossing
        </label>
        <label className="flex items-center gap-2">
          <input type="checkbox" checked={form.hazmat} onChange={(event) => onChange({ hazmat: event.target.checked })} />
          Hazmat
        </label>
        <label className="flex items-center gap-2">
          <input type="checkbox" checked={form.insurance_required} onChange={(event) => onChange({ insurance_required: event.target.checked })} />
          Insurance required
        </label>
      </div>

      {enabledServices.length > 0 && (
        <div>
          <p className={labelClass}>Selected services</p>
          <div className="flex flex-wrap gap-3 text-sm text-slate-300">
            {enabledServices.map((service) => (
              <label key={service.id} className="flex items-center gap-2">
                <input
                  type="checkbox"
                  checked={form.selected_service_ids.includes(service.id)}
                  onChange={() => toggleService(service.id)}
                />
                {service.name}
              </label>
            ))}
          </div>
        </div>
      )}

      <div className="rounded-xl border border-dashed border-cyan-500/20 bg-cyan-500/5 p-4">
        <p className="text-xs font-semibold text-cyan-300">Simulation Override</p>
        <p className="mt-1 text-[11px] text-slate-500">
          These fields affect only this estimate request. They do not change persisted pricing rules or policy status.
        </p>
        <div className="mt-3 grid grid-cols-1 gap-3 md:grid-cols-3">
          <div>
            <label className={labelClass}>Customer type override</label>
            <select className={inputClass} value={form.customer_type} onChange={(event) => onChange({ customer_type: event.target.value as PricingEstimatorFormState['customer_type'] })}>
              <option value="">Use company customer type</option>
              <option value="standard">standard</option>
              <option value="corporate">corporate</option>
              <option value="vip">vip</option>
              <option value="long_term">long_term</option>
            </select>
          </div>
          <div>
            <label className={labelClass}>Distance override (km)</label>
            <input className={inputClass} placeholder="Leave blank to use route distance" value={form.distance_km} onChange={(event) => onChange({ distance_km: event.target.value })} />
          </div>
          <div>
            <label className={labelClass}>Pricing policy override</label>
            <select className={inputClass} value={form.pricing_policy_id} onChange={(event) => onChange({ pricing_policy_id: event.target.value })}>
              <option value="">Use active policy</option>
              {policies.map((policy) => (
                <option key={policy.id} value={policy.id}>{policy.name} ({policy.status})</option>
              ))}
            </select>
          </div>
        </div>
      </div>

      {error && <p className="text-sm text-rose-400">{error}</p>}
      {fieldErrors.length > 0 && (
        <ul className="list-disc pl-5 text-xs text-rose-300">
          {fieldErrors.map((message) => <li key={message}>{message}</li>)}
        </ul>
      )}

      <div>
        <button
          type="submit"
          disabled={isSubmitting}
          className="rounded-xl bg-cyan-500 px-4 py-2.5 text-sm font-semibold text-slate-950 hover:bg-cyan-400 disabled:opacity-60"
        >
          {isSubmitting ? 'Calculating…' : 'Calculate Estimate'}
        </button>
      </div>
    </form>
  );
}
