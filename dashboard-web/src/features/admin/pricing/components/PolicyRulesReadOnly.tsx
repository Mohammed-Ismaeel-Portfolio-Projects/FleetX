import type { ReactNode } from 'react';
import { formatPricingMoney, formatPricingNumber } from '../utils/formatPricing';
import type { PricingPolicy } from '../types/pricing.types';

function EmptyState({ message }: { message: string }) {
  return <p className="text-xs text-slate-500">{message}</p>;
}

function Section({ title, children }: { title: string; children: ReactNode }) {
  return (
    <section className="rounded-xl border border-white/5 bg-[#090D16] p-4">
      <h3 className="mb-3 text-sm font-semibold text-white">{title}</h3>
      {children}
    </section>
  );
}

function SimpleTable({
  headers,
  rows,
}: {
  headers: string[];
  rows: Array<Array<string>>;
}) {
  return (
    <div className="overflow-x-auto">
      <table className="min-w-full text-left text-xs">
        <thead className="text-slate-500">
          <tr>
            {headers.map((header) => (
              <th key={header} className="pb-2 pr-4 font-medium">{header}</th>
            ))}
          </tr>
        </thead>
        <tbody className="text-slate-200">
          {rows.map((row) => (
            <tr key={row.join('|')} className="border-t border-white/5">
              {row.map((cell) => (
                <td key={`${row[0]}-${cell}`} className="py-2 pr-4">{cell}</td>
              ))}
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}

export default function PolicyRulesReadOnly({ policy }: { policy: PricingPolicy }) {
  const currency = policy.currency;

  return (
    <div className="grid grid-cols-1 gap-4 xl:grid-cols-2">
      <Section title="Base Rates">
        {policy.base_rates.length === 0 ? (
          <EmptyState message="No base rates configured." />
        ) : (
          <SimpleTable
            headers={['Cargo', 'Trailer', 'Rate / km']}
            rows={policy.base_rates.map((rate) => [
              rate.cargo_type ?? '—',
              rate.trailer_type ?? 'cargo fallback',
              formatPricingMoney(rate.rate_per_km, currency),
            ])}
          />
        )}
      </Section>

      <Section title="Distance Tiers">
        {policy.distance_tiers.length === 0 ? (
          <EmptyState message="No distance tiers configured." />
        ) : (
          <SimpleTable
            headers={['Min km', 'Max km', 'Rate / km']}
            rows={policy.distance_tiers.map((tier) => [
              formatPricingNumber(tier.min_distance_km, 0),
              tier.max_distance_km === null ? 'Unlimited' : formatPricingNumber(tier.max_distance_km, 0),
              formatPricingMoney(tier.rate_per_km, currency),
            ])}
          />
        )}
      </Section>

      <Section title="Weight Rules">
        {policy.weight_config === null ? (
          <EmptyState message="No weight configuration configured." />
        ) : (
          <SimpleTable
            headers={['Threshold (tons)', 'Charge / ton']}
            rows={[[
              formatPricingNumber(policy.weight_config.threshold_tons, 3),
              formatPricingMoney(policy.weight_config.charge_per_ton, currency),
            ]]}
          />
        )}
      </Section>

      <Section title="Waiting Charges">
        {policy.waiting_config === null ? (
          <EmptyState message="No waiting-charge configuration configured." />
        ) : (
          <SimpleTable
            headers={['Loading free min', 'Unloading free min', 'Rate / 15 min', 'Max', 'Enabled']}
            rows={[[
              String(policy.waiting_config.loading_free_minutes),
              String(policy.waiting_config.unloading_free_minutes),
              formatPricingMoney(policy.waiting_config.rate_per_15min, currency),
              formatPricingMoney(policy.waiting_config.max_charge, currency),
              policy.waiting_config.enabled ? 'Yes' : 'No',
            ]]}
          />
        )}
      </Section>

      <Section title="Fees">
        {policy.fees.length === 0 ? (
          <EmptyState message="No fees configured." />
        ) : (
          <SimpleTable
            headers={['Code', 'Name', 'Type', 'Amount', 'Enabled']}
            rows={policy.fees.map((fee) => [
              fee.code,
              fee.name,
              fee.fee_type,
              fee.fee_type === 'percentage'
                ? `${formatPricingNumber(fee.amount, 2)}%`
                : formatPricingMoney(fee.amount, currency),
              fee.enabled ? 'Yes' : 'No',
            ])}
          />
        )}
      </Section>

      <Section title="Priority Multipliers">
        {policy.priority_multipliers.length === 0 ? (
          <EmptyState message="No priority multipliers configured." />
        ) : (
          <SimpleTable
            headers={['Priority', 'Multiplier']}
            rows={policy.priority_multipliers.map((item) => [
              item.priority_level,
              formatPricingNumber(item.multiplier, 4),
            ])}
          />
        )}
      </Section>

      <Section title="Discounts">
        {policy.discounts.length === 0 ? (
          <EmptyState message="No discounts configured." />
        ) : (
          <SimpleTable
            headers={['Customer type', 'Type', 'Amount', 'Enabled']}
            rows={policy.discounts.map((discount) => [
              discount.customer_type,
              discount.discount_type,
              discount.discount_type === 'percentage'
                ? `${formatPricingNumber(discount.amount, 2)}%`
                : formatPricingMoney(discount.amount, currency),
              discount.enabled ? 'Yes' : 'No',
            ])}
          />
        )}
      </Section>

      <Section title="Service Catalog">
        {policy.service_catalog.length === 0 ? (
          <EmptyState message="No service catalog items configured." />
        ) : (
          <SimpleTable
            headers={['Name', 'Price', 'Enabled']}
            rows={policy.service_catalog.map((service) => [
              service.name,
              formatPricingMoney(service.price, currency),
              service.enabled ? 'Yes' : 'No',
            ])}
          />
        )}
      </Section>

      <Section title="Variance Rules">
        {policy.variance_rules.length === 0 ? (
          <EmptyState message="No variance rules configured." />
        ) : (
          <SimpleTable
            headers={['Factor', 'Threshold type', 'Threshold', 'Enabled']}
            rows={policy.variance_rules.map((rule) => [
              rule.factor_code ?? '—',
              rule.threshold_type ?? '—',
              formatPricingNumber(rule.threshold_value, 2),
              rule.enabled ? 'Yes' : 'No',
            ])}
          />
        )}
      </Section>
    </div>
  );
}
