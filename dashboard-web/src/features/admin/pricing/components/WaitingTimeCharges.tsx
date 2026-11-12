import { memo, useState } from 'react';
import { Save, Clock } from 'lucide-react';
import type { WaitingTimeConfig, PricingStatus } from '../types/pricing.types';

interface WaitingTimeChargesProps {
  config: WaitingTimeConfig;
  onUpdateConfig: (updates: Partial<WaitingTimeConfig>) => void;
}

function WaitingTimeChargesComponent({ config, onUpdateConfig }: WaitingTimeChargesProps) {
  const [isEditing, setIsEditing] = useState(false);
  const [editForm, setEditForm] = useState<Partial<WaitingTimeConfig>>(config);

  const handleEdit = () => {
    setEditForm(config);
    setIsEditing(true);
  };

  const handleCancel = () => {
    setEditForm(config);
    setIsEditing(false);
  };

  const handleSave = () => {
    onUpdateConfig(editForm);
    setIsEditing(false);
  };

  const handleChange = (field: keyof WaitingTimeConfig, value: any) => {
    setEditForm(prev => ({ ...prev, [field]: value }));
  };

  return (
    <div className="rounded-xl border border-white/5 bg-[#0E1320] p-5 shadow-sm">
      <div className="mb-4 flex items-center justify-between">
        <div className="flex items-center gap-2">
          <Clock className="h-4 w-4 text-cyan-400" />
          <h3 className="text-sm font-semibold text-white">Waiting Time Charges</h3>
        </div>
        {!isEditing ? (
          <button
            type="button"
            onClick={handleEdit}
            className="inline-flex items-center gap-2 rounded-lg border border-cyan-500/30 bg-cyan-500/10 px-3 py-1.5 text-xs font-medium text-cyan-400 transition-colors hover:bg-cyan-500/20"
          >
            Edit Configuration
          </button>
        ) : (
          <div className="flex gap-2">
            <button
              type="button"
              onClick={handleSave}
              className="inline-flex items-center gap-2 rounded-lg border border-green-500/30 bg-green-500/10 px-3 py-1.5 text-xs font-medium text-green-400 transition-colors hover:bg-green-500/20"
            >
              <Save className="h-3 w-3" />
              Save
            </button>
            <button
              type="button"
              onClick={handleCancel}
              className="inline-flex items-center gap-2 rounded-lg border border-red-500/30 bg-red-500/10 px-3 py-1.5 text-xs font-medium text-red-400 transition-colors hover:bg-red-500/20"
            >
              Cancel
            </button>
          </div>
        )}
      </div>

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <div className="rounded-lg border border-white/5 bg-[#0A0E17] p-4">
          <div className="mb-2 text-[10px] font-semibold uppercase tracking-wider text-slate-500">
            Included Loading Time
          </div>
          {isEditing ? (
            <div className="flex items-center gap-2">
              <input
                type="number"
                value={editForm.includedLoadingTime || 0}
                onChange={(e) => handleChange('includedLoadingTime', parseInt(e.target.value))}
                className="w-full rounded border border-white/10 bg-[#0E1320] px-2 py-1.5 text-xs text-white outline-none focus:border-cyan-500/50"
              />
              <span className="text-xs text-slate-400">min</span>
            </div>
          ) : (
            <div className="text-lg font-mono font-semibold text-cyan-400">
              {config.includedLoadingTime} <span className="text-xs text-slate-400">min</span>
            </div>
          )}
        </div>

        <div className="rounded-lg border border-white/5 bg-[#0A0E17] p-4">
          <div className="mb-2 text-[10px] font-semibold uppercase tracking-wider text-slate-500">
            Included Unloading Time
          </div>
          {isEditing ? (
            <div className="flex items-center gap-2">
              <input
                type="number"
                value={editForm.includedUnloadingTime || 0}
                onChange={(e) => handleChange('includedUnloadingTime', parseInt(e.target.value))}
                className="w-full rounded border border-white/10 bg-[#0E1320] px-2 py-1.5 text-xs text-white outline-none focus:border-cyan-500/50"
              />
              <span className="text-xs text-slate-400">min</span>
            </div>
          ) : (
            <div className="text-lg font-mono font-semibold text-cyan-400">
              {config.includedUnloadingTime} <span className="text-xs text-slate-400">min</span>
            </div>
          )}
        </div>

        <div className="rounded-lg border border-white/5 bg-[#0A0E17] p-4">
          <div className="mb-2 text-[10px] font-semibold uppercase tracking-wider text-slate-500">
            Extra 15 Minutes Charge
          </div>
          {isEditing ? (
            <div className="flex items-center gap-2">
              <span className="text-xs text-slate-400">$</span>
              <input
                type="number"
                step="0.01"
                value={editForm.extra15MinCharge || 0}
                onChange={(e) => handleChange('extra15MinCharge', parseFloat(e.target.value))}
                className="w-full rounded border border-white/10 bg-[#0E1320] px-2 py-1.5 text-xs text-white outline-none focus:border-cyan-500/50"
              />
            </div>
          ) : (
            <div className="text-lg font-mono font-semibold text-cyan-400">
              ${config.extra15MinCharge.toFixed(2)}
            </div>
          )}
        </div>

        <div className="rounded-lg border border-white/5 bg-[#0A0E17] p-4">
          <div className="mb-2 text-[10px] font-semibold uppercase tracking-wider text-slate-500">
            Extra Hour Charge
          </div>
          {isEditing ? (
            <div className="flex items-center gap-2">
              <span className="text-xs text-slate-400">$</span>
              <input
                type="number"
                step="0.01"
                value={editForm.extraHourCharge || 0}
                onChange={(e) => handleChange('extraHourCharge', parseFloat(e.target.value))}
                className="w-full rounded border border-white/10 bg-[#0E1320] px-2 py-1.5 text-xs text-white outline-none focus:border-cyan-500/50"
              />
            </div>
          ) : (
            <div className="text-lg font-mono font-semibold text-cyan-400">
              ${config.extraHourCharge.toFixed(2)}
            </div>
          )}
        </div>

        <div className="rounded-lg border border-white/5 bg-[#0A0E17] p-4">
          <div className="mb-2 text-[10px] font-semibold uppercase tracking-wider text-slate-500">
            Maximum Waiting Fee
          </div>
          {isEditing ? (
            <div className="flex items-center gap-2">
              <span className="text-xs text-slate-400">$</span>
              <input
                type="number"
                step="0.01"
                value={editForm.maxWaitingFee || 0}
                onChange={(e) => handleChange('maxWaitingFee', parseFloat(e.target.value))}
                className="w-full rounded border border-white/10 bg-[#0E1320] px-2 py-1.5 text-xs text-white outline-none focus:border-cyan-500/50"
              />
            </div>
          ) : (
            <div className="text-lg font-mono font-semibold text-cyan-400">
              ${config.maxWaitingFee.toFixed(2)}
            </div>
          )}
        </div>

        <div className="rounded-lg border border-white/5 bg-[#0A0E17] p-4">
          <div className="mb-2 text-[10px] font-semibold uppercase tracking-wider text-slate-500">
            Status
          </div>
          {isEditing ? (
            <select
              value={editForm.status || 'active'}
              onChange={(e) => handleChange('status', e.target.value as PricingStatus)}
              className="w-full rounded border border-white/10 bg-[#0E1320] px-2 py-1.5 text-xs text-white outline-none focus:border-cyan-500/50"
            >
              <option value="active">Active</option>
              <option value="inactive">Inactive</option>
              <option value="draft">Draft</option>
            </select>
          ) : (
            <span
              className={`inline-flex rounded-full border px-2 py-0.5 text-[9px] font-medium uppercase capitalize ${
                config.status === 'active'
                  ? 'border-green-500/20 bg-green-500/10 text-green-300'
                  : config.status === 'inactive'
                  ? 'border-red-500/20 bg-red-500/10 text-red-300'
                  : 'border-slate-500/20 bg-slate-500/10 text-slate-300'
              }`}
            >
              {config.status}
            </span>
          )}
        </div>
      </div>

      <div className="mt-4 rounded-lg border border-white/5 bg-[#0A0E17] p-3">
        <div className="text-[10px] text-slate-400">
          <span className="font-semibold text-slate-300">Note:</span> Waiting time charges apply when the total loading and unloading time exceeds the included free time. Charges are calculated in 15-minute increments and capped at the maximum fee.
        </div>
      </div>
    </div>
  );
}

export default memo(WaitingTimeChargesComponent);
