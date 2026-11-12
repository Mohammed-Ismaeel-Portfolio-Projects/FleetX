import { useState } from 'react';
import { Edit2, Save, X, Settings, Clock, Check } from 'lucide-react';

interface BaseFreightRate {
  id: string;
  cargoType: string;
  trailerType: string;
  pricingMethod: string;
  baseRate: number;
  status: 'active' | 'inactive';
}

interface DistanceTier {
  id: string;
  minDistance: number;
  maxDistance: number | null;
  pricePerKm: number;
  status: 'active' | 'inactive';
}

interface WaitingTimePolicy {
  loadingIncludedTime: number;
  unloadingIncludedTime: number;
  extra15MinPrice: number;
  extraHourPrice: number;
  maxWaitingCharge: number;
  enabled: boolean;
}

export default function FreightPricingConfiguration() {
  const [baseRates, setBaseRates] = useState<BaseFreightRate[]>([
    {
      id: '1',
      cargoType: 'General Cargo',
      trailerType: 'Dry Van',
      pricingMethod: '$1.45 / km',
      baseRate: 1.45,
      status: 'active',
    },
    {
      id: '2',
      cargoType: 'Refrigerated',
      trailerType: 'Reefer',
      pricingMethod: '$1.95 / km',
      baseRate: 1.95,
      status: 'active',
    },
    {
      id: '3',
      cargoType: 'Heavy Equipment',
      trailerType: 'Lowboy',
      pricingMethod: '$2.85 / km',
      baseRate: 2.85,
      status: 'active',
    },
  ]);

  const [distanceTiers, setDistanceTiers] = useState<DistanceTier[]>([
    {
      id: '1',
      minDistance: 0,
      maxDistance: 100,
      pricePerKm: 1.20,
      status: 'active',
    },
    {
      id: '2',
      minDistance: 101,
      maxDistance: 300,
      pricePerKm: 0.95,
      status: 'active',
    },
    {
      id: '3',
      minDistance: 301,
      maxDistance: 700,
      pricePerKm: 0.80,
      status: 'active',
    },
    {
      id: '4',
      minDistance: 701,
      maxDistance: null,
      pricePerKm: 0.65,
      status: 'active',
    },
  ]);

  const [waitingTimePolicy, setWaitingTimePolicy] = useState<WaitingTimePolicy>({
    loadingIncludedTime: 30,
    unloadingIncludedTime: 30,
    extra15MinPrice: 15,
    extraHourPrice: 45,
    maxWaitingCharge: 200,
    enabled: true,
  });

  const [editingBaseRate, setEditingBaseRate] = useState<string | null>(null);
  const [editingDistanceTier, setEditingDistanceTier] = useState<string | null>(null);
  const [editingWaitingTime, setEditingWaitingTime] = useState(false);
  const [hasChanges, setHasChanges] = useState(false);

  const handleEditBaseRate = (id: string) => {
    setEditingBaseRate(id);
  };

  const handleSaveBaseRate = (id: string, updates: Partial<BaseFreightRate>) => {
    setBaseRates(prev => prev.map(rate => rate.id === id ? { ...rate, ...updates } : rate));
    setEditingBaseRate(null);
    setHasChanges(true);
  };

  const handleCancelBaseRate = () => {
    setEditingBaseRate(null);
  };

  const handleEditDistanceTier = (id: string) => {
    setEditingDistanceTier(id);
  };

  const handleSaveDistanceTier = (id: string, updates: Partial<DistanceTier>) => {
    setDistanceTiers(prev => prev.map(tier => tier.id === id ? { ...tier, ...updates } : tier));
    setEditingDistanceTier(null);
    setHasChanges(true);
  };

  const handleCancelDistanceTier = () => {
    setEditingDistanceTier(null);
  };

  const handleSaveWaitingTime = (updates: Partial<WaitingTimePolicy>) => {
    setWaitingTimePolicy(prev => ({ ...prev, ...updates }));
    setEditingWaitingTime(false);
    setHasChanges(true);
  };

  const handleCancelWaitingTime = () => {
    setEditingWaitingTime(false);
  };

  const handleSaveAllChanges = () => {
    setHasChanges(false);
    // In production, this would save to backend
    console.log('Saving all pricing configuration changes...');
  };

  return (
    <div className="flex flex-col gap-6 w-full">
      {/* Section Header */}
      <div className="flex flex-col gap-1.5">
        <h2 className="text-lg font-semibold tracking-tight text-white font-sans">
          Freight Pricing Configuration
        </h2>
        <p className="text-slate-500 text-xs font-normal font-sans">
          Configure base rates, distance tiers, and waiting time policies for freight pricing.
        </p>
      </div>

      {/* Configuration Cards Grid */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* Base Freight Rates */}
        <div className="rounded-xl border border-white/5 bg-[#0E1320] p-5 shadow-sm">
          <div className="mb-4 flex items-center gap-2">
            <Settings className="h-4 w-4 text-cyan-400" />
            <h3 className="text-sm font-semibold text-white">Base Freight Rates</h3>
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
                {baseRates.map((rate) => (
                  <tr key={rate.id} className="border-b border-white/5 hover:bg-white/[0.02]">
                    {editingBaseRate === rate.id ? (
                      <>
                        <td className="px-3 py-2">
                          <input
                            type="text"
                            defaultValue={rate.cargoType}
                            className="w-full rounded border border-white/10 bg-[#0A0E17] px-2 py-1 text-xs text-white outline-none focus:border-cyan-500/50"
                          />
                        </td>
                        <td className="px-3 py-2">
                          <input
                            type="text"
                            defaultValue={rate.trailerType}
                            className="w-full rounded border border-white/10 bg-[#0A0E17] px-2 py-1 text-xs text-white outline-none focus:border-cyan-500/50"
                          />
                        </td>
                        <td className="px-3 py-2">
                          <input
                            type="text"
                            defaultValue={rate.pricingMethod}
                            className="w-full rounded border border-white/10 bg-[#0A0E17] px-2 py-1 text-xs text-white outline-none focus:border-cyan-500/50"
                          />
                        </td>
                        <td className="px-3 py-2">
                          <input
                            type="number"
                            step="0.01"
                            defaultValue={rate.baseRate}
                            className="w-full rounded border border-white/10 bg-[#0A0E17] px-2 py-1 text-xs text-white outline-none focus:border-cyan-500/50"
                          />
                        </td>
                        <td className="px-3 py-2">
                          <select
                            defaultValue={rate.status}
                            className="w-full rounded border border-white/10 bg-[#0A0E17] px-2 py-1 text-xs text-white outline-none focus:border-cyan-500/50"
                          >
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                          </select>
                        </td>
                        <td className="px-3 py-2 text-right">
                          <div className="flex justify-end gap-1">
                            <button
                              type="button"
                              onClick={() => handleSaveBaseRate(rate.id, {})}
                              className="rounded bg-green-500/10 p-1.5 text-green-400 transition-colors hover:bg-green-500/20"
                            >
                              <Save className="h-3 w-3" />
                            </button>
                            <button
                              type="button"
                              onClick={handleCancelBaseRate}
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
                        <td className="px-3 py-2 text-xs text-slate-400">{rate.pricingMethod}</td>
                        <td className="px-3 py-2 text-xs font-mono font-semibold text-cyan-400">
                          ${rate.baseRate.toFixed(2)}
                        </td>
                        <td className="px-3 py-2">
                          <span
                            className={`inline-flex rounded-full border px-2 py-0.5 text-[9px] font-medium uppercase capitalize ${
                              rate.status === 'active'
                                ? 'border-green-500/20 bg-green-500/10 text-green-300'
                                : 'border-red-500/20 bg-red-500/10 text-red-300'
                            }`}
                          >
                            {rate.status}
                          </span>
                        </td>
                        <td className="px-3 py-2 text-right">
                          <button
                            type="button"
                            onClick={() => handleEditBaseRate(rate.id)}
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

        {/* Distance Pricing Rules */}
        <div className="rounded-xl border border-white/5 bg-[#0E1320] p-5 shadow-sm">
          <div className="mb-4 flex items-center gap-2">
            <Settings className="h-4 w-4 text-cyan-400" />
            <h3 className="text-sm font-semibold text-white">Distance Pricing Rules</h3>
          </div>

          <div className="overflow-x-auto">
            <table className="w-full">
              <thead>
                <tr className="border-b border-white/5">
                  <th className="px-3 py-2 text-left text-[10px] font-semibold uppercase tracking-wider text-slate-500">
                    Distance Range
                  </th>
                  <th className="px-3 py-2 text-left text-[10px] font-semibold uppercase tracking-wider text-slate-500">
                    Min (km)
                  </th>
                  <th className="px-3 py-2 text-left text-[10px] font-semibold uppercase tracking-wider text-slate-500">
                    Max (km)
                  </th>
                  <th className="px-3 py-2 text-left text-[10px] font-semibold uppercase tracking-wider text-slate-500">
                    Price / KM
                  </th>
                  <th className="px-3 py-2 text-left text-[10px] font-semibold uppercase tracking-wider text-slate-500">
                    Status
                  </th>
                  <th className="px-3 py-2 text-right text-[10px] font-semibold uppercase tracking-wider text-slate-500">
                    Edit
                  </th>
                </tr>
              </thead>
              <tbody>
                {distanceTiers.map((tier) => {
                  const rangeText = tier.maxDistance === null 
                    ? `${tier.minDistance}+ km` 
                    : `${tier.minDistance}–${tier.maxDistance} km`;

                  return (
                    <tr key={tier.id} className="border-b border-white/5 hover:bg-white/[0.02]">
                      {editingDistanceTier === tier.id ? (
                        <>
                          <td className="px-3 py-2 text-xs text-slate-400">{rangeText}</td>
                          <td className="px-3 py-2">
                            <input
                              type="number"
                              defaultValue={tier.minDistance}
                              className="w-full rounded border border-white/10 bg-[#0A0E17] px-2 py-1 text-xs text-white outline-none focus:border-cyan-500/50"
                            />
                          </td>
                          <td className="px-3 py-2">
                            <input
                              type="number"
                              defaultValue={tier.maxDistance || ''}
                              placeholder="Unlimited"
                              className="w-full rounded border border-white/10 bg-[#0A0E17] px-2 py-1 text-xs text-white outline-none focus:border-cyan-500/50"
                            />
                          </td>
                          <td className="px-3 py-2">
                            <input
                              type="number"
                              step="0.01"
                              defaultValue={tier.pricePerKm}
                              className="w-full rounded border border-white/10 bg-[#0A0E17] px-2 py-1 text-xs text-white outline-none focus:border-cyan-500/50"
                            />
                          </td>
                          <td className="px-3 py-2">
                            <select
                              defaultValue={tier.status}
                              className="w-full rounded border border-white/10 bg-[#0A0E17] px-2 py-1 text-xs text-white outline-none focus:border-cyan-500/50"
                            >
                              <option value="active">Active</option>
                              <option value="inactive">Inactive</option>
                            </select>
                          </td>
                          <td className="px-3 py-2 text-right">
                            <div className="flex justify-end gap-1">
                              <button
                                type="button"
                                onClick={() => handleSaveDistanceTier(tier.id, {})}
                                className="rounded bg-green-500/10 p-1.5 text-green-400 transition-colors hover:bg-green-500/20"
                              >
                                <Save className="h-3 w-3" />
                              </button>
                              <button
                                type="button"
                                onClick={handleCancelDistanceTier}
                                className="rounded bg-red-500/10 p-1.5 text-red-400 transition-colors hover:bg-red-500/20"
                              >
                                <X className="h-3 w-3" />
                              </button>
                            </div>
                          </td>
                        </>
                      ) : (
                        <>
                          <td className="px-3 py-2 text-xs font-semibold text-white">{rangeText}</td>
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
                                  : 'border-red-500/20 bg-red-500/10 text-red-300'
                              }`}
                            >
                              {tier.status}
                            </span>
                          </td>
                          <td className="px-3 py-2 text-right">
                            <button
                              type="button"
                              onClick={() => handleEditDistanceTier(tier.id)}
                              className="rounded bg-cyan-500/10 p-1.5 text-cyan-400 transition-colors hover:bg-cyan-500/20"
                            >
                              <Edit2 className="h-3 w-3" />
                            </button>
                          </td>
                        </>
                      )}
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
        </div>
      </div>

      {/* Waiting Time Policy - Full Width */}
      <div className="rounded-xl border border-white/5 bg-[#0E1320] p-5 shadow-sm">
        <div className="mb-4 flex items-center justify-between">
          <div className="flex items-center gap-2">
            <Clock className="h-4 w-4 text-cyan-400" />
            <h3 className="text-sm font-semibold text-white">Waiting Time Policy</h3>
          </div>
          {!editingWaitingTime ? (
            <button
              type="button"
              onClick={() => setEditingWaitingTime(true)}
              className="inline-flex items-center gap-2 rounded-lg border border-cyan-500/30 bg-cyan-500/10 px-3 py-1.5 text-xs font-medium text-cyan-400 transition-colors hover:bg-cyan-500/20"
            >
              <Edit2 className="h-3 w-3" />
              Edit Policy
            </button>
          ) : (
            <div className="flex gap-2">
              <button
                type="button"
                onClick={() => handleSaveWaitingTime({})}
                className="inline-flex items-center gap-2 rounded-lg border border-green-500/30 bg-green-500/10 px-3 py-1.5 text-xs font-medium text-green-400 transition-colors hover:bg-green-500/20"
              >
                <Save className="h-3 w-3" />
                Save
              </button>
              <button
                type="button"
                onClick={handleCancelWaitingTime}
                className="inline-flex items-center gap-2 rounded-lg border border-red-500/30 bg-red-500/10 px-3 py-1.5 text-xs font-medium text-red-400 transition-colors hover:bg-red-500/20"
              >
                <X className="h-3 w-3" />
                Cancel
              </button>
            </div>
          )}
        </div>

        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
          <div className="rounded-lg border border-white/5 bg-[#0A0E17] p-4">
            <div className="mb-2 text-[10px] font-semibold uppercase tracking-wider text-slate-500">
              Loading Included Time
            </div>
            {editingWaitingTime ? (
              <div className="flex items-center gap-2">
                <input
                  type="number"
                  defaultValue={waitingTimePolicy.loadingIncludedTime}
                  className="w-full rounded border border-white/10 bg-[#0E1320] px-2 py-1.5 text-xs text-white outline-none focus:border-cyan-500/50"
                />
                <span className="text-xs text-slate-400">min</span>
              </div>
            ) : (
              <div className="text-lg font-mono font-semibold text-cyan-400">
                {waitingTimePolicy.loadingIncludedTime} <span className="text-xs text-slate-400">min</span>
              </div>
            )}
          </div>

          <div className="rounded-lg border border-white/5 bg-[#0A0E17] p-4">
            <div className="mb-2 text-[10px] font-semibold uppercase tracking-wider text-slate-500">
              Unloading Included Time
            </div>
            {editingWaitingTime ? (
              <div className="flex items-center gap-2">
                <input
                  type="number"
                  defaultValue={waitingTimePolicy.unloadingIncludedTime}
                  className="w-full rounded border border-white/10 bg-[#0E1320] px-2 py-1.5 text-xs text-white outline-none focus:border-cyan-500/50"
                />
                <span className="text-xs text-slate-400">min</span>
              </div>
            ) : (
              <div className="text-lg font-mono font-semibold text-cyan-400">
                {waitingTimePolicy.unloadingIncludedTime} <span className="text-xs text-slate-400">min</span>
              </div>
            )}
          </div>

          <div className="rounded-lg border border-white/5 bg-[#0A0E17] p-4">
            <div className="mb-2 text-[10px] font-semibold uppercase tracking-wider text-slate-500">
              Extra 15 Minutes
            </div>
            {editingWaitingTime ? (
              <div className="flex items-center gap-2">
                <span className="text-xs text-slate-400">$</span>
                <input
                  type="number"
                  step="0.01"
                  defaultValue={waitingTimePolicy.extra15MinPrice}
                  className="w-full rounded border border-white/10 bg-[#0E1320] px-2 py-1.5 text-xs text-white outline-none focus:border-cyan-500/50"
                />
              </div>
            ) : (
              <div className="text-lg font-mono font-semibold text-cyan-400">
                ${waitingTimePolicy.extra15MinPrice.toFixed(2)}
              </div>
            )}
          </div>

          <div className="rounded-lg border border-white/5 bg-[#0A0E17] p-4">
            <div className="mb-2 text-[10px] font-semibold uppercase tracking-wider text-slate-500">
              Extra Hour
            </div>
            {editingWaitingTime ? (
              <div className="flex items-center gap-2">
                <span className="text-xs text-slate-400">$</span>
                <input
                  type="number"
                  step="0.01"
                  defaultValue={waitingTimePolicy.extraHourPrice}
                  className="w-full rounded border border-white/10 bg-[#0E1320] px-2 py-1.5 text-xs text-white outline-none focus:border-cyan-500/50"
                />
              </div>
            ) : (
              <div className="text-lg font-mono font-semibold text-cyan-400">
                ${waitingTimePolicy.extraHourPrice.toFixed(2)}
              </div>
            )}
          </div>

          <div className="rounded-lg border border-white/5 bg-[#0A0E17] p-4">
            <div className="mb-2 text-[10px] font-semibold uppercase tracking-wider text-slate-500">
              Maximum Waiting Charge
            </div>
            {editingWaitingTime ? (
              <div className="flex items-center gap-2">
                <span className="text-xs text-slate-400">$</span>
                <input
                  type="number"
                  step="0.01"
                  defaultValue={waitingTimePolicy.maxWaitingCharge}
                  className="w-full rounded border border-white/10 bg-[#0E1320] px-2 py-1.5 text-xs text-white outline-none focus:border-cyan-500/50"
                />
              </div>
            ) : (
              <div className="text-lg font-mono font-semibold text-cyan-400">
                ${waitingTimePolicy.maxWaitingCharge.toFixed(2)}
              </div>
            )}
          </div>
        </div>

        <div className="mt-4 flex items-center gap-2">
          <div
            className={`h-2 w-2 rounded-full ${
              waitingTimePolicy.enabled ? 'bg-green-400' : 'bg-slate-500'
            }`}
          />
          <span className="text-xs text-slate-400">
            {waitingTimePolicy.enabled ? 'Policy Enabled' : 'Policy Disabled'}
          </span>
        </div>
      </div>

      {/* Save Changes Button */}
      {hasChanges && (
        <div className="flex justify-end">
          <button
            type="button"
            onClick={handleSaveAllChanges}
            className="inline-flex items-center gap-2 rounded-lg border border-cyan-500/30 bg-cyan-500/10 px-6 py-2.5 text-sm font-medium text-cyan-400 transition-colors hover:bg-cyan-500/20"
          >
            <Check className="h-4 w-4" />
            Save Changes
          </button>
        </div>
      )}
    </div>
  );
}
