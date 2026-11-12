import { memo, useState, useEffect } from 'react';
import { Calculator, RefreshCw } from 'lucide-react';
import type { ShipmentCalculation } from '../types/pricing.types';

interface PriceSimulatorProps {
  onCalculate: (params: {
    distance: number;
    weight: number;
    trailerType: string;
    services: string[];
    waitingTime: number;
    extraCharges: string[];
    discount: string;
  }) => ShipmentCalculation;
  baseFreightRates: any[];
  loadingUnloadingServices: any[];
  additionalCharges: any[];
  discountPolicies: any[];
}

function PriceSimulatorComponent({
  onCalculate,
  baseFreightRates,
  loadingUnloadingServices,
  additionalCharges,
  discountPolicies,
}: PriceSimulatorProps) {
  const [params, setParams] = useState({
    distance: 250,
    weight: 10,
    trailerType: 'Dry Van',
    services: [] as string[],
    waitingTime: 30,
    extraCharges: [] as string[],
    discount: '',
  });

  const [calculation, setCalculation] = useState<ShipmentCalculation | null>(null);

  useEffect(() => {
    const result = onCalculate(params);
    setCalculation(result);
  }, [params, onCalculate]);

  const handleReset = () => {
    setParams({
      distance: 250,
      weight: 10,
      trailerType: 'Dry Van',
      services: [],
      waitingTime: 30,
      extraCharges: [],
      discount: '',
    });
  };

  const toggleService = (serviceId: string) => {
    setParams(prev => ({
      ...prev,
      services: prev.services.includes(serviceId)
        ? prev.services.filter(id => id !== serviceId)
        : [...prev.services, serviceId],
    }));
  };

  const toggleExtraCharge = (chargeId: string) => {
    setParams(prev => ({
      ...prev,
      extraCharges: prev.extraCharges.includes(chargeId)
        ? prev.extraCharges.filter(id => id !== chargeId)
        : [...prev.extraCharges, chargeId],
    }));
  };

  const enabledServices = loadingUnloadingServices.filter(s => s.enabled);
  const enabledCharges = additionalCharges.filter(c => c.enabled);
  const enabledDiscounts = discountPolicies.filter(d => d.enabled);

  return (
    <div className="rounded-xl border border-white/5 bg-[#0E1320] p-5 shadow-sm">
      <div className="mb-4 flex items-center justify-between">
        <div className="flex items-center gap-2">
          <Calculator className="h-4 w-4 text-cyan-400" />
          <h3 className="text-sm font-semibold text-white">Live Shipment Price Simulator</h3>
        </div>
        <button
          type="button"
          onClick={handleReset}
          className="inline-flex items-center gap-2 rounded-lg border border-white/10 bg-white/5 px-3 py-1.5 text-xs font-medium text-slate-300 transition-colors hover:bg-white/10"
        >
          <RefreshCw className="h-3 w-3" />
          Reset
        </button>
      </div>

      <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
        {/* Input Parameters */}
        <div className="space-y-4">
          <div className="grid grid-cols-2 gap-3">
            <div>
              <label className="mb-1 block text-[9px] font-semibold uppercase tracking-wider text-slate-500">
                Distance (km)
              </label>
              <input
                type="number"
                value={params.distance}
                onChange={(e) => setParams(prev => ({ ...prev, distance: parseFloat(e.target.value) || 0 }))}
                className="w-full rounded border border-white/10 bg-[#0A0E17] px-3 py-2 text-xs text-white outline-none focus:border-cyan-500/50"
              />
            </div>
            <div>
              <label className="mb-1 block text-[9px] font-semibold uppercase tracking-wider text-slate-500">
                Weight (tons)
              </label>
              <input
                type="number"
                value={params.weight}
                onChange={(e) => setParams(prev => ({ ...prev, weight: parseFloat(e.target.value) || 0 }))}
                className="w-full rounded border border-white/10 bg-[#0A0E17] px-3 py-2 text-xs text-white outline-none focus:border-cyan-500/50"
              />
            </div>
          </div>

          <div>
            <label className="mb-1 block text-[9px] font-semibold uppercase tracking-wider text-slate-500">
              Trailer Type
            </label>
            <select
              value={params.trailerType}
              onChange={(e) => setParams(prev => ({ ...prev, trailerType: e.target.value }))}
              className="w-full rounded border border-white/10 bg-[#0A0E17] px-3 py-2 text-xs text-white outline-none focus:border-cyan-500/50"
            >
              {baseFreightRates.map((rate) => (
                <option key={rate.id} value={rate.trailerType}>
                  {rate.trailerType}
                </option>
              ))}
            </select>
          </div>

          <div>
            <label className="mb-1 block text-[9px] font-semibold uppercase tracking-wider text-slate-500">
              Waiting Time (minutes)
            </label>
            <input
              type="number"
              value={params.waitingTime}
              onChange={(e) => setParams(prev => ({ ...prev, waitingTime: parseFloat(e.target.value) || 0 }))}
              className="w-full rounded border border-white/10 bg-[#0A0E17] px-3 py-2 text-xs text-white outline-none focus:border-cyan-500/50"
            />
          </div>

          <div>
            <label className="mb-2 block text-[9px] font-semibold uppercase tracking-wider text-slate-500">
              Loading/Unloading Services
            </label>
            <div className="grid grid-cols-2 gap-2">
              {enabledServices.map((service) => (
                <label
                  key={service.id}
                  className={`flex cursor-pointer items-center gap-2 rounded-lg border p-2 transition-all ${
                    params.services.includes(service.id)
                      ? 'border-cyan-500/30 bg-cyan-500/10'
                      : 'border-white/5 bg-[#0A0E17] hover:border-white/10'
                  }`}
                >
                  <input
                    type="checkbox"
                    checked={params.services.includes(service.id)}
                    onChange={() => toggleService(service.id)}
                    className="rounded border-white/10 bg-[#0E1320] text-cyan-500 focus:ring-cyan-500/50"
                  />
                  <div className="flex-1">
                    <div className="text-xs text-white">{service.name}</div>
                    <div className="text-[10px] text-slate-400">${service.price.toFixed(2)}</div>
                  </div>
                </label>
              ))}
            </div>
          </div>

          <div>
            <label className="mb-2 block text-[9px] font-semibold uppercase tracking-wider text-slate-500">
              Extra Charges
            </label>
            <div className="grid grid-cols-2 gap-2">
              {enabledCharges.map((charge) => (
                <label
                  key={charge.id}
                  className={`flex cursor-pointer items-center gap-2 rounded-lg border p-2 transition-all ${
                    params.extraCharges.includes(charge.id)
                      ? 'border-cyan-500/30 bg-cyan-500/10'
                      : 'border-white/5 bg-[#0A0E17] hover:border-white/10'
                  }`}
                >
                  <input
                    type="checkbox"
                    checked={params.extraCharges.includes(charge.id)}
                    onChange={() => toggleExtraCharge(charge.id)}
                    className="rounded border-white/10 bg-[#0E1320] text-cyan-500 focus:ring-cyan-500/50"
                  />
                  <div className="flex-1">
                    <div className="text-xs text-white">{charge.name}</div>
                    <div className="text-[10px] text-slate-400">
                      {charge.chargeType === 'fixed' ? `$${charge.amount.toFixed(2)}` : `${charge.amount}%`}
                    </div>
                  </div>
                </label>
              ))}
            </div>
          </div>

          <div>
            <label className="mb-1 block text-[9px] font-semibold uppercase tracking-wider text-slate-500">
              Discount Policy
            </label>
            <select
              value={params.discount}
              onChange={(e) => setParams(prev => ({ ...prev, discount: e.target.value }))}
              className="w-full rounded border border-white/10 bg-[#0A0E17] px-3 py-2 text-xs text-white outline-none focus:border-cyan-500/50"
            >
              <option value="">No Discount</option>
              {enabledDiscounts.map((discount) => (
                <option key={discount.id} value={discount.id}>
                  {discount.name} ({discount.discountType === 'fixed' ? `$${discount.amount}` : `${discount.amount}%`})
                </option>
              ))}
            </select>
          </div>
        </div>

        {/* Result Display */}
        <div className="rounded-lg border border-cyan-500/30 bg-cyan-500/5 p-4">
          <div className="mb-4">
            <div className="text-[10px] font-semibold uppercase tracking-wider text-slate-400">
              Estimated Shipment Cost
            </div>
            <div className="mt-2 text-3xl font-mono font-bold text-cyan-400">
              ${calculation?.estimatedCost.toFixed(2) || '0.00'}
            </div>
          </div>

          {calculation && (
            <div className="space-y-2">
              <div className="flex justify-between border-b border-white/5 pb-2">
                <span className="text-xs text-slate-400">Base Rate</span>
                <span className="text-xs font-mono text-white">
                  ${calculation.costBreakdown.baseRate.toFixed(2)}
                </span>
              </div>
              <div className="flex justify-between border-b border-white/5 pb-2">
                <span className="text-xs text-slate-400">Distance Charge</span>
                <span className="text-xs font-mono text-white">
                  ${calculation.costBreakdown.distanceCharge.toFixed(2)}
                </span>
              </div>
              <div className="flex justify-between border-b border-white/5 pb-2">
                <span className="text-xs text-slate-400">Waiting Time Charge</span>
                <span className="text-xs font-mono text-white">
                  ${calculation.costBreakdown.waitingTimeCharge.toFixed(2)}
                </span>
              </div>
              <div className="flex justify-between border-b border-white/5 pb-2">
                <span className="text-xs text-slate-400">Services Charge</span>
                <span className="text-xs font-mono text-white">
                  ${calculation.costBreakdown.servicesCharge.toFixed(2)}
                </span>
              </div>
              <div className="flex justify-between border-b border-white/5 pb-2">
                <span className="text-xs text-slate-400">Extra Charges</span>
                <span className="text-xs font-mono text-white">
                  ${calculation.costBreakdown.extraCharges.toFixed(2)}
                </span>
              </div>
              <div className="flex justify-between border-b border-white/5 pb-2">
                <span className="text-xs text-slate-400">Subtotal</span>
                <span className="text-xs font-mono font-semibold text-white">
                  ${calculation.costBreakdown.subtotal.toFixed(2)}
                </span>
              </div>
              <div className="flex justify-between border-b border-white/5 pb-2">
                <span className="text-xs text-slate-400">Discount</span>
                <span className="text-xs font-mono text-green-400">
                  -${calculation.costBreakdown.discount.toFixed(2)}
                </span>
              </div>
              <div className="flex justify-between pt-2">
                <span className="text-xs font-semibold text-white">Total</span>
                <span className="text-sm font-mono font-bold text-cyan-400">
                  ${calculation.costBreakdown.total.toFixed(2)}
                </span>
              </div>
            </div>
          )}
        </div>
      </div>
    </div>
  );
}

export default memo(PriceSimulatorComponent);
