import { memo, useState } from 'react';
import { Edit2, Save, X, Plus, Clock, AlertTriangle, Shield, Zap } from 'lucide-react';
import type { AdditionalCharge } from '../types/pricing.types';

interface AdditionalChargesProps {
  charges: AdditionalCharge[];
  onUpdateCharge: (id: string, updates: Partial<AdditionalCharge>) => void;
}

function AdditionalChargesComponent({ charges, onUpdateCharge }: AdditionalChargesProps) {
  const [editingId, setEditingId] = useState<string | null>(null);
  const [editForm, setEditForm] = useState<Partial<AdditionalCharge>>({});

  const handleEdit = (charge: AdditionalCharge) => {
    setEditingId(charge.id);
    setEditForm(charge);
  };

  const handleCancel = () => {
    setEditingId(null);
    setEditForm({});
  };

  const handleSave = () => {
    if (editingId && editForm) {
      onUpdateCharge(editingId, editForm);
      setEditingId(null);
      setEditForm({});
    }
  };

  const handleChange = (field: keyof AdditionalCharge, value: any) => {
    setEditForm(prev => ({ ...prev, [field]: value }));
  };

  const getCategoryIcon = (category: AdditionalCharge['category']) => {
    switch (category) {
      case 'time':
        return Clock;
      case 'special':
        return Zap;
      case 'penalty':
        return AlertTriangle;
      case 'insurance':
        return Shield;
      default:
        return AlertTriangle;
    }
  };

  const getCategoryColor = (category: AdditionalCharge['category']) => {
    switch (category) {
      case 'time':
        return 'text-blue-400 bg-blue-500/10 border-blue-500/20';
      case 'special':
        return 'text-purple-400 bg-purple-500/10 border-purple-500/20';
      case 'penalty':
        return 'text-red-400 bg-red-500/10 border-red-500/20';
      case 'insurance':
        return 'text-green-400 bg-green-500/10 border-green-500/20';
      default:
        return 'text-slate-400 bg-slate-500/10 border-slate-500/20';
    }
  };

  const groupedCharges = charges.reduce((acc, charge) => {
    if (!acc[charge.category]) {
      acc[charge.category] = [];
    }
    acc[charge.category].push(charge);
    return acc;
  }, {} as Record<AdditionalCharge['category'], AdditionalCharge[]>);

  return (
    <div className="rounded-xl border border-white/5 bg-[#0E1320] p-5 shadow-sm">
      <div className="mb-4 flex items-center justify-between">
        <h3 className="text-sm font-semibold text-white">Additional Charges</h3>
        <button
          type="button"
          className="inline-flex items-center gap-2 rounded-lg border border-cyan-500/30 bg-cyan-500/10 px-3 py-1.5 text-xs font-medium text-cyan-400 transition-colors hover:bg-cyan-500/20"
        >
          <Plus className="h-3 w-3" />
          Add Charge
        </button>
      </div>

      <div className="space-y-4">
        {Object.entries(groupedCharges).map(([category, categoryCharges]) => {
          const Icon = getCategoryIcon(category as AdditionalCharge['category']);
          const categoryColor = getCategoryColor(category as AdditionalCharge['category']);

          return (
            <div key={category}>
              <div className="mb-2 flex items-center gap-2">
                <div className={`rounded-lg border p-1.5 ${categoryColor}`}>
                  <Icon className="h-3 w-3" />
                </div>
                <span className="text-[10px] font-semibold uppercase tracking-wider text-slate-400">
                  {category}
                </span>
              </div>

              <div className="space-y-2">
                {categoryCharges.map((charge) => (
                  <div
                    key={charge.id}
                    className={`rounded-lg border border-white/5 bg-[#0A0E17] p-3 transition-all ${
                      !charge.enabled ? 'opacity-50' : ''
                    }`}
                  >
                    {editingId === charge.id ? (
                      <div className="space-y-3">
                        <div>
                          <label className="mb-1 block text-[9px] font-semibold uppercase tracking-wider text-slate-500">
                            Charge Name
                          </label>
                          <input
                            type="text"
                            value={editForm.name || ''}
                            onChange={(e) => handleChange('name', e.target.value)}
                            className="w-full rounded border border-white/10 bg-[#0E1320] px-2 py-1.5 text-xs text-white outline-none focus:border-cyan-500/50"
                          />
                        </div>
                        <div className="grid grid-cols-2 gap-3">
                          <div>
                            <label className="mb-1 block text-[9px] font-semibold uppercase tracking-wider text-slate-500">
                              Charge Type
                            </label>
                            <select
                              value={editForm.chargeType || 'fixed'}
                              onChange={(e) => handleChange('chargeType', e.target.value)}
                              className="w-full rounded border border-white/10 bg-[#0E1320] px-2 py-1.5 text-xs text-white outline-none focus:border-cyan-500/50"
                            >
                              <option value="fixed">Fixed</option>
                              <option value="percentage">Percentage</option>
                            </select>
                          </div>
                          <div>
                            <label className="mb-1 block text-[9px] font-semibold uppercase tracking-wider text-slate-500">
                              Amount
                            </label>
                            <input
                              type="number"
                              step="0.01"
                              value={editForm.amount || 0}
                              onChange={(e) => handleChange('amount', parseFloat(e.target.value))}
                              className="w-full rounded border border-white/10 bg-[#0E1320] px-2 py-1.5 text-xs text-white outline-none focus:border-cyan-500/50"
                            />
                          </div>
                        </div>
                        <div>
                          <label className="mb-1 block text-[9px] font-semibold uppercase tracking-wider text-slate-500">
                            Description
                          </label>
                          <textarea
                            value={editForm.description || ''}
                            onChange={(e) => handleChange('description', e.target.value)}
                            rows={2}
                            className="w-full rounded border border-white/10 bg-[#0E1320] px-2 py-1.5 text-xs text-white outline-none focus:border-cyan-500/50 resize-none"
                          />
                        </div>
                        <div className="flex items-center gap-2">
                          <input
                            type="checkbox"
                            id={`enabled-${charge.id}`}
                            checked={editForm.enabled || false}
                            onChange={(e) => handleChange('enabled', e.target.checked)}
                            className="rounded border-white/10 bg-[#0E1320] text-cyan-500 focus:ring-cyan-500/50"
                          />
                          <label htmlFor={`enabled-${charge.id}`} className="text-xs text-slate-400">
                            Enabled
                          </label>
                        </div>
                        <div className="flex gap-2">
                          <button
                            type="button"
                            onClick={handleSave}
                            className="flex-1 rounded bg-green-500/10 px-3 py-1.5 text-xs font-medium text-green-400 transition-colors hover:bg-green-500/20"
                          >
                            <Save className="mr-1 inline h-3 w-3" />
                            Save
                          </button>
                          <button
                            type="button"
                            onClick={handleCancel}
                            className="flex-1 rounded bg-red-500/10 px-3 py-1.5 text-xs font-medium text-red-400 transition-colors hover:bg-red-500/20"
                          >
                            <X className="mr-1 inline h-3 w-3" />
                            Cancel
                          </button>
                        </div>
                      </div>
                    ) : (
                      <div className="flex items-center justify-between">
                        <div className="flex-1">
                          <div className="flex items-center gap-2">
                            <span className="text-xs font-semibold text-white">{charge.name}</span>
                            <span
                              className={`inline-flex rounded-full border px-1.5 py-0.5 text-[8px] font-medium uppercase ${
                                charge.chargeType === 'fixed'
                                  ? 'border-cyan-500/20 bg-cyan-500/10 text-cyan-300'
                                  : 'border-purple-500/20 bg-purple-500/10 text-purple-300'
                              }`}
                            >
                              {charge.chargeType === 'fixed' ? 'Fixed' : '%'}
                            </span>
                          </div>
                          <div className="mt-1 text-[10px] text-slate-400">{charge.description}</div>
                        </div>
                        <div className="flex items-center gap-3">
                          <div className="text-right">
                            <div className="text-sm font-mono font-semibold text-cyan-400">
                              {charge.chargeType === 'fixed' ? `$${charge.amount.toFixed(2)}` : `${charge.amount}%`}
                            </div>
                            <div className="flex items-center justify-end gap-1">
                              <div
                                className={`h-1.5 w-1.5 rounded-full ${
                                  charge.enabled ? 'bg-green-400' : 'bg-slate-500'
                                }`}
                              />
                              <span className="text-[9px] text-slate-400">
                                {charge.enabled ? 'Active' : 'Disabled'}
                              </span>
                            </div>
                          </div>
                          <button
                            type="button"
                            onClick={() => handleEdit(charge)}
                            className="rounded bg-cyan-500/10 p-1.5 text-cyan-400 transition-colors hover:bg-cyan-500/20"
                          >
                            <Edit2 className="h-3 w-3" />
                          </button>
                        </div>
                      </div>
                    )}
                  </div>
                ))}
              </div>
            </div>
          );
        })}
      </div>
    </div>
  );
}

export default memo(AdditionalChargesComponent);
