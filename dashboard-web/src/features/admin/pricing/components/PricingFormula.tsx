import { memo } from 'react';
import { Calculator, Plus, Minus, Equal } from 'lucide-react';
import type { ShipmentCalculation } from '../types/pricing.types';

interface PricingFormulaProps {
  calculation: ShipmentCalculation | null;
}

function PricingFormulaComponent({ calculation }: PricingFormulaProps) {
  if (!calculation) {
    return (
      <div className="rounded-xl border border-white/5 bg-[#0E1320] p-5 shadow-sm">
        <div className="mb-4 flex items-center gap-2">
          <Calculator className="h-4 w-4 text-cyan-400" />
          <h3 className="text-sm font-semibold text-white">Pricing Formula</h3>
        </div>
        <div className="flex h-32 items-center justify-center text-center">
          <div>
            <p className="text-sm font-semibold text-white">No calculation available</p>
            <p className="mt-1 text-xs text-slate-500">
              Use the Price Simulator to see the pricing formula breakdown
            </p>
          </div>
        </div>
      </div>
    );
  }

  const steps = [
    {
      step: '1',
      label: 'Base Rate',
      value: calculation.costBreakdown.baseRate,
      formula: `${calculation.distance} km × Base Rate`,
      color: 'text-cyan-400',
      bgColor: 'bg-cyan-500/10',
      borderColor: 'border-cyan-500/20',
    },
    {
      step: '2',
      label: 'Distance Charge',
      value: calculation.costBreakdown.distanceCharge,
      formula: 'Distance Tiers × Price per km',
      color: 'text-blue-400',
      bgColor: 'bg-blue-500/10',
      borderColor: 'border-blue-500/20',
    },
    {
      step: '3',
      label: 'Waiting Time Charge',
      value: calculation.costBreakdown.waitingTimeCharge,
      formula: 'Extra Time × Rate (capped)',
      color: 'text-yellow-400',
      bgColor: 'bg-yellow-500/10',
      borderColor: 'border-yellow-500/20',
    },
    {
      step: '4',
      label: 'Services Charge',
      value: calculation.costBreakdown.servicesCharge,
      formula: 'Sum of selected services',
      color: 'text-purple-400',
      bgColor: 'bg-purple-500/10',
      borderColor: 'border-purple-500/20',
    },
    {
      step: '5',
      label: 'Extra Charges',
      value: calculation.costBreakdown.extraCharges,
      formula: 'Fixed + Percentage charges',
      color: 'text-orange-400',
      bgColor: 'bg-orange-500/10',
      borderColor: 'border-orange-500/20',
    },
    {
      step: '6',
      label: 'Subtotal',
      value: calculation.costBreakdown.subtotal,
      formula: 'Sum of all charges',
      color: 'text-white',
      bgColor: 'bg-white/10',
      borderColor: 'border-white/20',
      isSubtotal: true,
    },
    {
      step: '7',
      label: 'Discount',
      value: -calculation.costBreakdown.discount,
      formula: 'Discount Policy Applied',
      color: 'text-green-400',
      bgColor: 'bg-green-500/10',
      borderColor: 'border-green-500/20',
      isDiscount: true,
    },
    {
      step: '8',
      label: 'Total',
      value: calculation.costBreakdown.total,
      formula: 'Subtotal - Discount',
      color: 'text-cyan-400',
      bgColor: 'bg-cyan-500/10',
      borderColor: 'border-cyan-500/20',
      isTotal: true,
    },
  ];

  return (
    <div className="rounded-xl border border-white/5 bg-[#0E1320] p-5 shadow-sm">
      <div className="mb-4 flex items-center gap-2">
        <Calculator className="h-4 w-4 text-cyan-400" />
        <h3 className="text-sm font-semibold text-white">Pricing Formula Breakdown</h3>
      </div>

      <div className="space-y-2">
        {steps.map((step, index) => (
          <div key={step.step} className="flex items-center gap-3">
            <div
              className={`flex h-8 w-8 items-center justify-center rounded-lg border ${step.borderColor} ${step.bgColor} ${step.color} font-mono text-sm font-semibold`}
            >
              {step.step}
            </div>

            <div className="flex-1">
              <div className="flex items-center justify-between">
                <span className="text-xs font-semibold text-white">{step.label}</span>
                <span className={`font-mono text-sm font-semibold ${step.color}`}>
                  {step.isDiscount && step.value > 0 ? '-' : ''}
                  ${Math.abs(step.value).toFixed(2)}
                </span>
              </div>
              <div className="text-[10px] text-slate-400">{step.formula}</div>
            </div>

            {index < steps.length - 1 && (
              <div className="flex items-center gap-1">
                {step.isSubtotal ? (
                  <Plus className="h-4 w-4 text-slate-500" />
                ) : step.isDiscount ? (
                  <Minus className="h-4 w-4 text-slate-500" />
                ) : (
                  <Plus className="h-4 w-4 text-slate-500" />
                )}
              </div>
            )}

            {index === steps.length - 1 && (
              <Equal className="h-4 w-4 text-cyan-400" />
            )}
          </div>
        ))}
      </div>

      <div className="mt-4 rounded-lg border border-cyan-500/30 bg-cyan-500/5 p-4">
        <div className="flex items-center justify-between">
          <div>
            <div className="text-[10px] font-semibold uppercase tracking-wider text-slate-400">
              Final Shipment Cost
            </div>
            <div className="text-[10px] text-slate-500">
              {calculation.distance} km • {calculation.weight} tons • {calculation.trailerType}
            </div>
          </div>
          <div className="text-right">
            <div className="text-2xl font-mono font-bold text-cyan-400">
              ${calculation.estimatedCost.toFixed(2)}
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}

export default memo(PricingFormulaComponent);
