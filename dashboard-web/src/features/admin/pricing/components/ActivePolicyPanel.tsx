import { formatDateTime, formatPricingMoney, formatTaxRate } from '../utils/formatPricing';
import type { PricingPolicy } from '../types/pricing.types';

interface ActivePolicyPanelProps {
  policy: PricingPolicy | null;
  isLoading: boolean;
  error: string | null;
  status?: number;
  showConfiguration: boolean;
  onToggleConfiguration: () => void;
  onRetry: () => void;
}

function MetaItem({ label, value }: { label: string; value: string }) {
  return (
    <div className="rounded-xl border border-white/5 bg-[#090D16] px-3 py-2.5">
      <p className="text-[10px] uppercase tracking-wide text-slate-500">{label}</p>
      <p className="mt-1 text-sm font-medium text-white">{value}</p>
    </div>
  );
}

export default function ActivePolicyPanel({
  policy,
  isLoading,
  error,
  status,
  showConfiguration,
  onToggleConfiguration,
  onRetry,
}: ActivePolicyPanelProps) {
  if (isLoading) {
    return (
      <div className="rounded-2xl border border-white/5 bg-[#0E1320] p-6">
        <p className="text-sm text-slate-400">Loading active pricing policy…</p>
      </div>
    );
  }

  if (error) {
    const title = status === 403
      ? 'You do not have permission to view pricing policy configuration.'
      : status === 404
        ? 'No active pricing policy is currently in effect.'
        : 'Unable to load the active pricing policy.';

    return (
      <div className="rounded-2xl border border-white/5 bg-[#0E1320] p-6">
        <h2 className="text-lg font-semibold text-white">Active Pricing Policy</h2>
        <p className="mt-2 text-sm text-slate-400">{title}</p>
        {status !== 403 && status !== 404 && (
          <p className="mt-1 text-xs text-slate-500">{error}</p>
        )}
        {status !== 403 && (
          <button
            type="button"
            onClick={onRetry}
            className="mt-4 rounded-xl border border-white/10 px-3 py-2 text-xs text-cyan-400 hover:text-cyan-300"
          >
            Retry
          </button>
        )}
      </div>
    );
  }

  if (!policy) {
    return null;
  }

  return (
    <div className="rounded-2xl border border-white/5 bg-[#0E1320] p-6">
      <div className="mb-4 flex items-start justify-between gap-4">
        <div>
          <p className="text-[10px] uppercase tracking-wide text-slate-500">Resolved active policy</p>
          <h2 className="mt-1 text-lg font-semibold text-white">Active Pricing Policy</h2>
          <p className="mt-1 text-xs text-slate-500">
            Persisted configuration resolved by PricingPolicyResolver. Lifecycle changes are managed in Policy Governance above.
          </p>
        </div>
        <button
          type="button"
          onClick={onToggleConfiguration}
          className="shrink-0 rounded-xl border border-white/10 px-3 py-2 text-xs text-cyan-400 hover:text-cyan-300"
        >
          {showConfiguration ? 'Hide Configuration' : 'View Configuration'}
        </button>
      </div>

      <div className="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-4">
        <MetaItem label="Policy Name" value={policy.name} />
        <MetaItem label="Status" value={policy.status.toUpperCase()} />
        <MetaItem label="Currency" value={policy.currency} />
        <MetaItem label="Priority" value={String(policy.priority)} />
        <MetaItem label="Effective From" value={formatDateTime(policy.effective_from)} />
        <MetaItem label="Effective Until" value={formatDateTime(policy.effective_until)} />
        <MetaItem label="Tax Rate" value={formatTaxRate(policy.tax_rate)} />
        <MetaItem label="Tax Inclusive" value={policy.tax_inclusive ? 'Yes' : 'No'} />
        <MetaItem
          label="Minimum Fare"
          value={formatPricingMoney(policy.minimum_fare, policy.currency)}
        />
      </div>
    </div>
  );
}
