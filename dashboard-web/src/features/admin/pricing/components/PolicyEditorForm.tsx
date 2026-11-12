import type { PricingPolicyInput } from '../types/pricing.types';

const inputClass = 'w-full rounded-xl border border-white/10 bg-[#090D16] px-3 py-2.5 text-sm text-white outline-none placeholder:text-slate-500';
const labelClass = 'mb-1 block text-[10px] uppercase tracking-wide text-slate-500';

function toDatetimeLocalValue(value: string | null | undefined): string {
  if (!value) {
    return '';
  }
  return value.replace(' ', 'T').slice(0, 16);
}

function fromDatetimeLocalValue(value: string): string | null {
  if (!value) {
    return null;
  }

  return `${value.replace('T', ' ')}:00`;
}

interface PolicyEditorFormProps {
  draft: PricingPolicyInput;
  isSubmitting: boolean;
  error: string | null;
  fieldErrors: string[];
  onChange: (updates: Partial<PricingPolicyInput>) => void;
  onSubmit: () => void;
  onCancel: () => void;
  submitLabel: string;
}

export default function PolicyEditorForm({
  draft,
  isSubmitting,
  error,
  fieldErrors,
  onChange,
  onSubmit,
  onCancel,
  submitLabel,
}: PolicyEditorFormProps) {
  return (
    <form
      className="flex flex-col gap-4"
      onSubmit={(event) => {
        event.preventDefault();
        onSubmit();
      }}
    >
      <div className="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-3">
        <div>
          <label className={labelClass}>Policy name</label>
          <input className={inputClass} value={draft.name} onChange={(e) => onChange({ name: e.target.value })} />
        </div>
        <div>
          <label className={labelClass}>Currency</label>
          <input className={inputClass} value={draft.currency} maxLength={3} onChange={(e) => onChange({ currency: e.target.value.toUpperCase() })} />
        </div>
        <div>
          <label className={labelClass}>Priority</label>
          <input className={inputClass} type="number" min={0} value={draft.priority} onChange={(e) => onChange({ priority: Number(e.target.value) })} />
        </div>
        <div>
          <label className={labelClass}>Tax rate (%)</label>
          <input className={inputClass} type="number" min={0} step="0.0001" value={draft.tax_rate} onChange={(e) => onChange({ tax_rate: Number(e.target.value) })} />
        </div>
        <div>
          <label className={labelClass}>Minimum fare</label>
          <input className={inputClass} type="number" min={0} value={draft.minimum_fare ?? ''} onChange={(e) => onChange({ minimum_fare: e.target.value === '' ? null : Number(e.target.value) })} />
        </div>
        <div>
          <label className={labelClass}>Effective from</label>
          <input
            className={inputClass}
            type="datetime-local"
            value={toDatetimeLocalValue(draft.effective_from)}
            onChange={(e) => onChange({ effective_from: fromDatetimeLocalValue(e.target.value) })}
          />
        </div>
        <div>
          <label className={labelClass}>Effective until</label>
          <input
            className={inputClass}
            type="datetime-local"
            value={toDatetimeLocalValue(draft.effective_until)}
            onChange={(e) => onChange({ effective_until: fromDatetimeLocalValue(e.target.value) })}
          />
        </div>
        <div className="flex items-end">
          <label className="flex items-center gap-2 text-sm text-slate-300">
            <input type="checkbox" checked={draft.tax_inclusive} onChange={(e) => onChange({ tax_inclusive: e.target.checked })} />
            Tax inclusive
          </label>
        </div>
      </div>

      <div>
        <label className={labelClass}>Description</label>
        <textarea className={`${inputClass} min-h-20`} value={draft.description ?? ''} onChange={(e) => onChange({ description: e.target.value })} />
      </div>

      <div>
        <h3 className="mb-2 text-sm font-semibold text-white">Base rates</h3>
        <div className="overflow-x-auto rounded-xl border border-white/5">
          <table className="min-w-full text-left text-xs text-slate-300">
            <thead className="bg-[#090D16] text-[10px] uppercase tracking-wide text-slate-500">
              <tr>
                <th className="px-3 py-2">Cargo</th>
                <th className="px-3 py-2">Trailer</th>
                <th className="px-3 py-2">Rate / km</th>
              </tr>
            </thead>
            <tbody>
              {draft.base_rates.map((rate, index) => (
                <tr key={`${rate.cargo_type}-${rate.trailer_type ?? 'any'}-${index}`} className="border-t border-white/5">
                  <td className="px-3 py-2">{rate.cargo_type}</td>
                  <td className="px-3 py-2">{rate.trailer_type ?? '—'}</td>
                  <td className="px-3 py-2">
                    <input
                      className={inputClass}
                      type="number"
                      min={0}
                      step="0.0001"
                      value={rate.rate_per_km}
                      onChange={(e) => {
                        const base_rates = [...draft.base_rates];
                        base_rates[index] = { ...rate, rate_per_km: Number(e.target.value) };
                        onChange({ base_rates });
                      }}
                    />
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>

      <div>
        <h3 className="mb-2 text-sm font-semibold text-white">Distance tiers</h3>
        <div className="overflow-x-auto rounded-xl border border-white/5">
          <table className="min-w-full text-left text-xs text-slate-300">
            <thead className="bg-[#090D16] text-[10px] uppercase tracking-wide text-slate-500">
              <tr>
                <th className="px-3 py-2">Min km</th>
                <th className="px-3 py-2">Max km</th>
                <th className="px-3 py-2">Rate / km</th>
              </tr>
            </thead>
            <tbody>
              {draft.distance_tiers.map((tier, index) => (
                <tr key={`tier-${index}`} className="border-t border-white/5">
                  <td className="px-3 py-2">{tier.min_distance_km}</td>
                  <td className="px-3 py-2">{tier.max_distance_km ?? '∞'}</td>
                  <td className="px-3 py-2">
                    <input
                      className={inputClass}
                      type="number"
                      min={0}
                      step="0.0001"
                      value={tier.rate_per_km}
                      onChange={(e) => {
                        const distance_tiers = [...draft.distance_tiers];
                        distance_tiers[index] = { ...tier, rate_per_km: Number(e.target.value) };
                        onChange({ distance_tiers });
                      }}
                    />
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>

      {error && <p className="text-sm text-rose-400">{error}</p>}
      {fieldErrors.length > 0 && (
        <ul className="list-disc space-y-1 pl-5 text-xs text-rose-300">
          {fieldErrors.map((item) => (
            <li key={item}>{item}</li>
          ))}
        </ul>
      )}

      <div className="flex flex-wrap gap-2">
        <button
          type="submit"
          disabled={isSubmitting}
          className="rounded-xl bg-cyan-500/20 px-4 py-2 text-sm font-medium text-cyan-300 hover:bg-cyan-500/30 disabled:opacity-50"
        >
          {isSubmitting ? 'Saving…' : submitLabel}
        </button>
        <button
          type="button"
          onClick={onCancel}
          className="rounded-xl border border-white/10 px-4 py-2 text-sm text-slate-300 hover:text-white"
        >
          Cancel
        </button>
      </div>
    </form>
  );
}
