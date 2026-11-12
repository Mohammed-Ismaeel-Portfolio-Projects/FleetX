import { memo, useState } from 'react';
import { Edit2, Save, X, Plus } from 'lucide-react';
import type { BaseFreightRate, PricingMethod, PricingStatus } from '../types/pricing.types';

interface BaseFreightRatesProps {
  rates: BaseFreightRate[];
  onUpdateRate: (id: string, updates: Partial<BaseFreightRate>) => void;
}

function BaseFreightRatesComponent({ rates, onUpdateRate }: BaseFreightRatesProps) {
  const [editingId, setEditingId] = useState<string | null>(null);
  const [editForm, setEditForm] = useState<Partial<BaseFreightRate>>({});

  const handleEdit = (rate: BaseFreightRate) => {
    setEditingId(rate.id);
    setEditForm(rate);
  };

  const handleCancel = () => {
    setEditingId(null);
    setEditForm({});
  };

  const handleSave = () => {
    if (editingId && editForm) {
      onUpdateRate(editingId, editForm);
      setEditingId(null);
      setEditForm({});
    }
  };

  const handleChange = (field: keyof BaseFreightRate, value: any) => {
    setEditForm(prev => ({ ...prev, [field]: value }));
  };

  const pricingMethods: PricingMethod[] = ['per_km', 'per_ton', 'flat_rate', 'per_pallet'];
  const statuses: PricingStatus[] = ['active', 'inactive', 'draft'];

  return (
    <div className="rounded-xl border border-white/5 bg-[#0E1320] p-5 shadow-sm">
      <div className="mb-4 flex items-center justify-between">
        <h3 className="text-sm font-semibold text-white">Base Freight Rates</h3>
        <button
          type="button"
          className="inline-flex items-center gap-2 rounded-lg border border-cyan-500/30 bg-cyan-500/10 px-3 py-1.5 text-xs font-medium text-cyan-400 transition-colors hover:bg-cyan-500/20"
        >
          <Plus className="h-3 w-3" />
          Add Rate
        </button>
      </div>

      <div className="overflow-x-auto">
        <table className="w-full">
          <thead>
            <tr className="border-b border-white/5">
              <th className="px-3 py-2 text-left text-[10px] font-semibold uppercase tracking-wider text-slate-500">
                Cargo Type
              </th>
              <th className="px-3 py-2 text-left text-[10px] font-semibold uppercase tracking-wider text-slate-500">
                Trailer Type
              </th>
              <th className="px-3 py-2 text-left text-[10px] font-semibold uppercase tracking-wider text-slate-500">
                Pricing Method
              </th>
              <th className="px-3 py-2 text-left text-[10px] font-semibold uppercase tracking-wider text-slate-500">
                Base Rate
              </th>
              <th className="px-3 py-2 text-left text-[10px] font-semibold uppercase tracking-wider text-slate-500">
                Status
              </th>
              <th className="px-3 py-2 text-right text-[10px] font-semibold uppercase tracking-wider text-slate-500">
                Actions
              </th>
            </tr>
          </thead>
          <tbody>
            {rates.map((rate) => (
              <tr key={rate.id} className="border-b border-white/5 hover:bg-white/[0.02]">
                {editingId === rate.id ? (
                  <>
                    <td className="px-3 py-2">
                      <input
                        type="text"
                        value={editForm.cargoType || ''}
                        onChange={(e) => handleChange('cargoType', e.target.value)}
                        className="w-full rounded border border-white/10 bg-[#0A0E17] px-2 py-1 text-xs text-white outline-none focus:border-cyan-500/50"
                      />
                    </td>
                    <td className="px-3 py-2">
                      <input
                        type="text"
                        value={editForm.trailerType || ''}
                        onChange={(e) => handleChange('trailerType', e.target.value)}
                        className="w-full rounded border border-white/10 bg-[#0A0E17] px-2 py-1 text-xs text-white outline-none focus:border-cyan-500/50"
                      />
                    </td>
                    <td className="px-3 py-2">
                      <select
                        value={editForm.pricingMethod || 'per_km'}
                        onChange={(e) => handleChange('pricingMethod', e.target.value as PricingMethod)}
                        className="w-full rounded border border-white/10 bg-[#0A0E17] px-2 py-1 text-xs text-white outline-none focus:border-cyan-500/50"
                      >
                        {pricingMethods.map((method) => (
                          <option key={method} value={method}>
                            {method.replace('_', ' ')}
                          </option>
                        ))}
                      </select>
                    </td>
                    <td className="px-3 py-2">
                      <input
                        type="number"
                        step="0.01"
                        value={editForm.baseRate || 0}
                        onChange={(e) => handleChange('baseRate', parseFloat(e.target.value))}
                        className="w-full rounded border border-white/10 bg-[#0A0E17] px-2 py-1 text-xs text-white outline-none focus:border-cyan-500/50"
                      />
                    </td>
                    <td className="px-3 py-2">
                      <select
                        value={editForm.status || 'active'}
                        onChange={(e) => handleChange('status', e.target.value as PricingStatus)}
                        className="w-full rounded border border-white/10 bg-[#0A0E17] px-2 py-1 text-xs text-white outline-none focus:border-cyan-500/50"
                      >
                        {statuses.map((status) => (
                          <option key={status} value={status}>
                            {status}
                          </option>
                        ))}
                      </select>
                    </td>
                    <td className="px-3 py-2 text-right">
                      <div className="flex justify-end gap-1">
                        <button
                          type="button"
                          onClick={handleSave}
                          className="rounded bg-green-500/10 p-1.5 text-green-400 transition-colors hover:bg-green-500/20"
                        >
                          <Save className="h-3 w-3" />
                        </button>
                        <button
                          type="button"
                          onClick={handleCancel}
                          className="rounded bg-red-500/10 p-1.5 text-red-400 transition-colors hover:bg-red-500/20"
                        >
                          <X className="h-3 w-3" />
                        </button>
                      </div>
                    </td>
                  </>
                ) : (
                  <>
                    <td className="px-3 py-2 text-xs text-white">{rate.cargoType}</td>
                    <td className="px-3 py-2 text-xs text-white">{rate.trailerType}</td>
                    <td className="px-3 py-2 text-xs text-slate-400">
                      {rate.pricingMethod.replace('_', ' ')}
                    </td>
                    <td className="px-3 py-2 text-xs font-mono font-semibold text-cyan-400">
                      ${rate.baseRate.toFixed(2)}
                    </td>
                    <td className="px-3 py-2">
                      <span
                        className={`inline-flex rounded-full border px-2 py-0.5 text-[9px] font-medium uppercase capitalize ${
                          rate.status === 'active'
                            ? 'border-green-500/20 bg-green-500/10 text-green-300'
                            : rate.status === 'inactive'
                            ? 'border-red-500/20 bg-red-500/10 text-red-300'
                            : 'border-slate-500/20 bg-slate-500/10 text-slate-300'
                        }`}
                      >
                        {rate.status}
                      </span>
                    </td>
                    <td className="px-3 py-2 text-right">
                      <button
                        type="button"
                        onClick={() => handleEdit(rate)}
                        className="rounded bg-cyan-500/10 p-1.5 text-cyan-400 transition-colors hover:bg-cyan-500/20"
                      >
                        <Edit2 className="h-3 w-3" />
                      </button>
                    </td>
                  </>
                )}
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}

export default memo(BaseFreightRatesComponent);
