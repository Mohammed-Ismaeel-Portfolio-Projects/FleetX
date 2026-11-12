import { memo, useState } from 'react';
import { Edit2, Save, X, Plus } from 'lucide-react';
import type { DistanceTier, PricingStatus } from '../types/pricing.types';

interface DistancePricingRulesProps {
  tiers: DistanceTier[];
  onUpdateTier: (id: string, updates: Partial<DistanceTier>) => void;
}

function DistancePricingRulesComponent({ tiers, onUpdateTier }: DistancePricingRulesProps) {
  const [editingId, setEditingId] = useState<string | null>(null);
  const [editForm, setEditForm] = useState<Partial<DistanceTier>>({});

  const handleEdit = (tier: DistanceTier) => {
    setEditingId(tier.id);
    setEditForm(tier);
  };

  const handleCancel = () => {
    setEditingId(null);
    setEditForm({});
  };

  const handleSave = () => {
    if (editingId && editForm) {
      onUpdateTier(editingId, editForm);
      setEditingId(null);
      setEditForm({});
    }
  };

  const handleChange = (field: keyof DistanceTier, value: any) => {
    setEditForm(prev => ({ ...prev, [field]: value }));
  };

  const formatDistanceRange = (min: number, max: number | null) => {
    if (max === null) return `${min}+ km`;
    return `${min}–${max} km`;
  };

  return (
    <div className="rounded-xl border border-white/5 bg-[#0E1320] p-5 shadow-sm">
      <div className="mb-4 flex items-center justify-between">
        <h3 className="text-sm font-semibold text-white">Distance Pricing Tiers</h3>
        <button
          type="button"
          className="inline-flex items-center gap-2 rounded-lg border border-cyan-500/30 bg-cyan-500/10 px-3 py-1.5 text-xs font-medium text-cyan-400 transition-colors hover:bg-cyan-500/20"
        >
          <Plus className="h-3 w-3" />
          Add Tier
        </button>
      </div>

      <div className="overflow-x-auto">
        <table className="w-full">
          <thead>
            <tr className="border-b border-white/5">
              <th className="px-3 py-2 text-left text-[10px] font-semibold uppercase tracking-wider text-slate-500">
                Distance Range
              </th>
              <th className="px-3 py-2 text-left text-[10px] font-semibold uppercase tracking-wider text-slate-500">
                Min Distance (km)
              </th>
              <th className="px-3 py-2 text-left text-[10px] font-semibold uppercase tracking-wider text-slate-500">
                Max Distance (km)
              </th>
              <th className="px-3 py-2 text-left text-[10px] font-semibold uppercase tracking-wider text-slate-500">
                Price per km
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
            {tiers.map((tier) => (
              <tr key={tier.id} className="border-b border-white/5 hover:bg-white/[0.02]">
                {editingId === tier.id ? (
                  <>
                    <td className="px-3 py-2 text-xs text-slate-400">
                      {formatDistanceRange(editForm.minDistance || 0, editForm.maxDistance || null)}
                    </td>
                    <td className="px-3 py-2">
                      <input
                        type="number"
                        value={editForm.minDistance || 0}
                        onChange={(e) => handleChange('minDistance', parseInt(e.target.value))}
                        className="w-full rounded border border-white/10 bg-[#0A0E17] px-2 py-1 text-xs text-white outline-none focus:border-cyan-500/50"
                      />
                    </td>
                    <td className="px-3 py-2">
                      <input
                        type="number"
                        value={editForm.maxDistance || ''}
                        onChange={(e) => handleChange('maxDistance', e.target.value ? parseInt(e.target.value) : null)}
                        placeholder="Unlimited"
                        className="w-full rounded border border-white/10 bg-[#0A0E17] px-2 py-1 text-xs text-white outline-none focus:border-cyan-500/50"
                      />
                    </td>
                    <td className="px-3 py-2">
                      <input
                        type="number"
                        step="0.01"
                        value={editForm.pricePerKm || 0}
                        onChange={(e) => handleChange('pricePerKm', parseFloat(e.target.value))}
                        className="w-full rounded border border-white/10 bg-[#0A0E17] px-2 py-1 text-xs text-white outline-none focus:border-cyan-500/50"
                      />
                    </td>
                    <td className="px-3 py-2">
                      <select
                        value={editForm.status || 'active'}
                        onChange={(e) => handleChange('status', e.target.value as PricingStatus)}
                        className="w-full rounded border border-white/10 bg-[#0A0E17] px-2 py-1 text-xs text-white outline-none focus:border-cyan-500/50"
                      >
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                        <option value="draft">Draft</option>
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
                    <td className="px-3 py-2 text-xs font-semibold text-white">
                      {formatDistanceRange(tier.minDistance, tier.maxDistance)}
                    </td>
                    <td className="px-3 py-2 text-xs text-slate-400">{tier.minDistance}</td>
                    <td className="px-3 py-2 text-xs text-slate-400">{tier.maxDistance || 'Unlimited'}</td>
                    <td className="px-3 py-2 text-xs font-mono font-semibold text-cyan-400">
                      ${tier.pricePerKm.toFixed(2)}
                    </td>
                    <td className="px-3 py-2">
                      <span
                        className={`inline-flex rounded-full border px-2 py-0.5 text-[9px] font-medium uppercase capitalize ${
                          tier.status === 'active'
                            ? 'border-green-500/20 bg-green-500/10 text-green-300'
                            : tier.status === 'inactive'
                            ? 'border-red-500/20 bg-red-500/10 text-red-300'
                            : 'border-slate-500/20 bg-slate-500/10 text-slate-300'
                        }`}
                      >
                        {tier.status}
                      </span>
                    </td>
                    <td className="px-3 py-2 text-right">
                      <button
                        type="button"
                        onClick={() => handleEdit(tier)}
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

export default memo(DistancePricingRulesComponent);
