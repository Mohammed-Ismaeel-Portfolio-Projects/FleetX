import { formatPricingMoney, formatPricingNumber, formatTaxRate } from '../utils/formatPricing';
import type { PricingEstimateResponse } from '../types/pricing.types';

function Row({ label, value }: { label: string; value: string }) {
  return (
    <div className="flex items-center justify-between gap-4 border-t border-white/5 py-2 text-sm">
      <span className="text-slate-400">{label}</span>
      <span className="font-medium text-white">{value}</span>
    </div>
  );
}

export default function PricingEstimateResult({ estimate }: { estimate: PricingEstimateResponse }) {
  const currency = estimate.currency;

  return (
    <div className="rounded-xl border border-white/5 bg-[#090D16] p-4">
      <h3 className="text-sm font-semibold text-white">Estimate Result</h3>
      <p className="mt-1 text-xs text-slate-500">
        Values are returned by the backend PricingEngine. This screen does not recalculate them.
      </p>

      <div className="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
        <div>
          <p className="text-[10px] uppercase tracking-wide text-slate-500">Context</p>
          <Row label="Policy" value={`${estimate.pricing_policy.name} (${estimate.pricing_policy.source})`} />
          <Row label="Customer type" value={`${estimate.customer_type.value} (${estimate.customer_type.source})`} />
          <Row
            label="Distance"
            value={`${formatPricingNumber(estimate.distance.distance_km, 2)} km (${estimate.distance.distance_source})`}
          />
          <Row label="Currency" value={estimate.currency} />
        </div>
        <div>
          <p className="text-[10px] uppercase tracking-wide text-slate-500">Resolution</p>
          <Row label="Base rate" value={formatPricingMoney(estimate.pricing_rule_resolution.base_rate, currency)} />
          <Row
            label="Distance tier rate"
            value={formatPricingMoney(estimate.pricing_rule_resolution.distance_tier_rate, currency)}
          />
          <Row label="Base rate match" value={estimate.pricing_rule_resolution.base_rate_resolution_type} />
          <Row label="Tax rate" value={formatTaxRate(estimate.tax.rate)} />
        </div>
      </div>

      <div className="mt-4">
        <p className="text-[10px] uppercase tracking-wide text-slate-500">Charges</p>
        <Row label="Freight" value={formatPricingMoney(estimate.charges.freight, currency)} />
        <Row label="Weight surcharge" value={formatPricingMoney(estimate.charges.weight_surcharge, currency)} />
        <Row label="Waiting charge" value={formatPricingMoney(estimate.charges.waiting_charge, currency)} />
        <Row label="Services" value={formatPricingMoney(estimate.charges.services, currency)} />
        <Row label="Border" value={formatPricingMoney(estimate.charges.border, currency)} />
        <Row label="Insurance" value={formatPricingMoney(estimate.charges.insurance, currency)} />
        <Row label="Hazmat" value={formatPricingMoney(estimate.charges.hazmat, currency)} />
        <Row label="Heavy reefer" value={formatPricingMoney(estimate.charges.heavy_reefer, currency)} />
        <Row label="Priority" value={formatPricingMoney(estimate.charges.priority, currency)} />
        <Row label="Subtotal" value={formatPricingMoney(estimate.subtotal, currency)} />
        <Row
          label="Discount"
          value={`${formatPricingMoney(estimate.discount.amount, currency)}${estimate.discount.type ? ` (${estimate.discount.type})` : ''}`}
        />
        <Row label="After discount" value={formatPricingMoney(estimate.after_discount, currency)} />
        <Row label="Minimum fare adjustment" value={formatPricingMoney(estimate.minimum_fare_adjustment, currency)} />
        <Row label="After minimum fare" value={formatPricingMoney(estimate.after_minimum_fare, currency)} />
        <Row label="Tax" value={formatPricingMoney(estimate.tax.amount, currency)} />
        <Row label="Total" value={formatPricingMoney(estimate.total, currency)} />
      </div>

      {estimate.service_items.length > 0 && (
        <div className="mt-4">
          <p className="text-[10px] uppercase tracking-wide text-slate-500">Service items</p>
          {estimate.service_items.map((item) => (
            <Row key={item.name} label={item.name} value={formatPricingMoney(item.price, currency)} />
          ))}
        </div>
      )}

      {estimate.applied_rules.length > 0 && (
        <div className="mt-4">
          <p className="text-[10px] uppercase tracking-wide text-slate-500">Applied rules</p>
          <p className="mt-2 text-xs text-slate-300">{estimate.applied_rules.join(', ')}</p>
        </div>
      )}

      {estimate.warnings.length > 0 && (
        <div className="mt-4">
          <p className="text-[10px] uppercase tracking-wide text-slate-500">Warnings</p>
          <p className="mt-2 text-xs text-amber-300">{estimate.warnings.join(' ')}</p>
        </div>
      )}
    </div>
  );
}
