import { memo, useState } from 'react';
import { Edit2, Save, X, Plus, Building, Crown, Calendar, Tag } from 'lucide-react';
import type { DiscountPolicy } from '../types/pricing.types';

interface DiscountPoliciesProps {
  policies: DiscountPolicy[];
  onUpdatePolicy: (id: string, updates: Partial<DiscountPolicy>) => void;
}

function DiscountPoliciesComponent({ policies, onUpdatePolicy }: DiscountPoliciesProps) {
  const [editingId, setEditingId] = useState<string | null>(null);
  const [editForm, setEditForm] = useState<Partial<DiscountPolicy>>({});

  const handleEdit = (policy: DiscountPolicy) => {
    setEditingId(policy.id);
    setEditForm(policy);
  };

  const handleCancel = () => {
    setEditingId(null);
    setEditForm({});
  };

  const handleSave = () => {
    if (editingId && editForm) {
      onUpdatePolicy(editingId, editForm);
      setEditingId(null);
      setEditForm({});
    }
  };

  const handleChange = (field: keyof DiscountPolicy, value: any) => {
    setEditForm(prev => ({ ...prev, [field]: value }));
  };

  const getCustomerTypeIcon = (type: DiscountPolicy['customerType']) => {
    switch (type) {
      case 'corporate':
        return Building;
      case 'vip':
        return Crown;
      case 'long_term':
        return Calendar;
      case 'seasonal':
        return Calendar;
      case 'promotional':
        return Tag;
      default:
        return Building;
    }
  };

  const getCustomerTypeColor = (type: DiscountPolicy['customerType']) => {
    switch (type) {
      case 'corporate':
        return 'text-blue-400 bg-blue-500/10 border-blue-500/20';
      case 'vip':
        return 'text-yellow-400 bg-yellow-500/10 border-yellow-500/20';
      case 'long_term':
        return 'text-green-400 bg-green-500/10 border-green-500/20';
      case 'seasonal':
        return 'text-purple-400 bg-purple-500/10 border-purple-500/20';
      case 'promotional':
        return 'text-pink-400 bg-pink-500/10 border-pink-500/20';
      default:
        return 'text-slate-400 bg-slate-500/10 border-slate-500/20';
    }
  };

  const groupedPolicies = policies.reduce((acc, policy) => {
    if (!acc[policy.customerType]) {
      acc[policy.customerType] = [];
    }
    acc[policy.customerType].push(policy);
    return acc;
  }, {} as Record<DiscountPolicy['customerType'], DiscountPolicy[]>);

  return (
    <div className="rounded-xl border border-white/5 bg-[#0E1320] p-5 shadow-sm">
      <div className="mb-4 flex items-center justify-between">
        <h3 className="text-sm font-semibold text-white">Discount Policies</h3>
        <button
          type="button"
          className="inline-flex items-center gap-2 rounded-lg border border-cyan-500/30 bg-cyan-500/10 px-3 py-1.5 text-xs font-medium text-cyan-400 transition-colors hover:bg-cyan-500/20"
        >
          <Plus className="h-3 w-3" />
          Add Policy
        </button>
      </div>

      <div className="space-y-4">
        {Object.entries(groupedPolicies).map(([customerType, typePolicies]) => {
          const Icon = getCustomerTypeIcon(customerType as DiscountPolicy['customerType']);
          const typeColor = getCustomerTypeColor(customerType as DiscountPolicy['customerType']);

          return (
            <div key={customerType}>
              <div className="mb-2 flex items-center gap-2">
                <div className={`rounded-lg border p-1.5 ${typeColor}`}>
                  <Icon className="h-3 w-3" />
                </div>
                <span className="text-[10px] font-semibold uppercase tracking-wider text-slate-400">
                  {customerType.replace('_', ' ')}
                </span>
              </div>

              <div className="space-y-2">
                {typePolicies.map((policy) => (
                  <div
                    key={policy.id}
                    className={`rounded-lg border border-white/5 bg-[#0A0E17] p-3 transition-all ${
                      !policy.enabled ? 'opacity-50' : ''
                    }`}
                  >
                    {editingId === policy.id ? (
                      <div className="space-y-3">
                        <div>
                          <label className="mb-1 block text-[9px] font-semibold uppercase tracking-wider text-slate-500">
                            Policy Name
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
                              Discount Type
                            </label>
                            <select
                              value={editForm.discountType || 'percentage'}
                              onChange={(e) => handleChange('discountType', e.target.value)}
                              className="w-full rounded border border-white/10 bg-[#0E1320] px-2 py-1.5 text-xs text-white outline-none focus:border-cyan-500/50"
                            >
                              <option value="percentage">Percentage</option>
                              <option value="fixed">Fixed</option>
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
                            Conditions (comma-separated)
                          </label>
                          <textarea
                            value={editForm.conditions?.join(', ') || ''}
                            onChange={(e) => handleChange('conditions', e.target.value.split(',').map(c => c.trim()))}
                            rows={2}
                            className="w-full rounded border border-white/10 bg-[#0E1320] px-2 py-1.5 text-xs text-white outline-none focus:border-cyan-500/50 resize-none"
                          />
                        </div>
                        <div className="flex items-center gap-2">
                          <input
                            type="checkbox"
                            id={`enabled-${policy.id}`}
                            checked={editForm.enabled || false}
                            onChange={(e) => handleChange('enabled', e.target.checked)}
                            className="rounded border-white/10 bg-[#0E1320] text-cyan-500 focus:ring-cyan-500/50"
                          />
                          <label htmlFor={`enabled-${policy.id}`} className="text-xs text-slate-400">
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
                            <span className="text-xs font-semibold text-white">{policy.name}</span>
                            <span
                              className={`inline-flex rounded-full border px-1.5 py-0.5 text-[8px] font-medium uppercase ${
                                policy.discountType === 'fixed'
                                  ? 'border-cyan-500/20 bg-cyan-500/10 text-cyan-300'
                                  : 'border-purple-500/20 bg-purple-500/10 text-purple-300'
                              }`}
                            >
                              {policy.discountType === 'fixed' ? 'Fixed' : '%'}
                            </span>
                          </div>
                          <div className="mt-1 flex flex-wrap gap-1">
                            {policy.conditions.map((condition, idx) => (
                              <span
                                key={idx}
                                className="inline-flex rounded bg-white/5 px-1.5 py-0.5 text-[9px] text-slate-400"
                              >
                                {condition}
                              </span>
                            ))}
                          </div>
                        </div>
                        <div className="flex items-center gap-3">
                          <div className="text-right">
                            <div className="text-sm font-mono font-semibold text-green-400">
                              {policy.discountType === 'fixed' ? `$${policy.amount.toFixed(2)}` : `${policy.amount}%`}
                            </div>
                            <div className="flex items-center justify-end gap-1">
                              <div
                                className={`h-1.5 w-1.5 rounded-full ${
                                  policy.enabled ? 'bg-green-400' : 'bg-slate-500'
                                }`}
                              />
                              <span className="text-[9px] text-slate-400">
                                {policy.enabled ? 'Active' : 'Disabled'}
                              </span>
                            </div>
                          </div>
                          <button
                            type="button"
                            onClick={() => handleEdit(policy)}
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

export default memo(DiscountPoliciesComponent);
