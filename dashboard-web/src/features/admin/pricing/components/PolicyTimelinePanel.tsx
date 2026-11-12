import { formatDateTime } from '../utils/formatPricing';
import type { PricingPolicySummary } from '../types/pricing.types';

interface PolicyTimelinePanelProps {
  timeline: PricingPolicyTimeline | null;
  isLoading: boolean;
  error: string | null;
}

export interface PricingPolicyTimeline {
  current: (PricingPolicySummary & { temporal_state: string }) | null;
  upcoming: Array<PricingPolicySummary & { temporal_state: string }>;
  history: Array<PricingPolicySummary & { temporal_state: string }>;
  drafts: Array<PricingPolicySummary & { temporal_state: string }>;
  other_active: Array<PricingPolicySummary & { temporal_state: string }>;
}

function TimelineCard({
  title,
  policy,
  emptyLabel,
}: {
  title: string;
  policy: (PricingPolicySummary & { temporal_state?: string }) | null;
  emptyLabel: string;
}) {
  return (
    <div className="rounded-xl border border-white/5 bg-[#090D16] p-4">
      <p className="text-[10px] uppercase tracking-wide text-slate-500">{title}</p>
      {!policy ? (
        <p className="mt-2 text-sm text-slate-500">{emptyLabel}</p>
      ) : (
        <div className="mt-2 space-y-1">
          <p className="text-sm font-medium text-white">{policy.name}</p>
          <p className="text-xs text-cyan-400 uppercase">{policy.temporal_state ?? policy.status}</p>
          <p className="text-xs text-slate-500">
            Effective: {policy.effective_from ? formatDateTime(policy.effective_from) : 'Now'}
            {policy.effective_until ? ` → ${formatDateTime(policy.effective_until)}` : ''}
          </p>
          <p className="text-xs text-slate-500">Priority: {policy.priority}</p>
        </div>
      )}
    </div>
  );
}

function TimelineList({
  title,
  policies,
  emptyLabel,
}: {
  title: string;
  policies: Array<PricingPolicySummary & { temporal_state?: string }>;
  emptyLabel: string;
}) {
  return (
    <div className="rounded-xl border border-white/5 bg-[#090D16] p-4">
      <p className="text-[10px] uppercase tracking-wide text-slate-500">{title}</p>
      {policies.length === 0 ? (
        <p className="mt-2 text-sm text-slate-500">{emptyLabel}</p>
      ) : (
        <ul className="mt-2 space-y-3">
          {policies.map((policy) => (
            <li key={policy.id} className="border-t border-white/5 pt-2 first:border-t-0 first:pt-0">
              <p className="text-sm font-medium text-white">{policy.name}</p>
              <p className="text-xs text-cyan-400 uppercase">{policy.temporal_state ?? policy.status}</p>
              <p className="text-xs text-slate-500">
                {policy.effective_from ? formatDateTime(policy.effective_from) : 'Immediate'}
                {policy.effective_until ? ` → ${formatDateTime(policy.effective_until)}` : ''}
              </p>
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}

export default function PolicyTimelinePanel({ timeline, isLoading, error }: PolicyTimelinePanelProps) {
  if (isLoading) {
    return (
      <div className="rounded-2xl border border-white/5 bg-[#0E1320] p-6">
        <p className="text-sm text-slate-400">Loading policy timeline…</p>
      </div>
    );
  }

  if (error) {
    return (
      <div className="rounded-2xl border border-white/5 bg-[#0E1320] p-6">
        <p className="text-sm text-rose-400">{error}</p>
      </div>
    );
  }

  if (!timeline) {
    return null;
  }

  return (
    <div className="rounded-2xl border border-white/5 bg-[#0E1320] p-6">
      <div className="mb-4">
        <p className="text-[10px] uppercase tracking-wide text-slate-500">Temporal governance</p>
        <h2 className="mt-1 text-lg font-semibold text-white">Policy Timeline</h2>
        <p className="mt-1 text-xs text-slate-500">
          Current, upcoming, and historical policies. Effectiveness is determined by the backend resolver.
        </p>
      </div>
      <div className="grid grid-cols-1 gap-4 lg:grid-cols-3">
        <TimelineCard title="Current" policy={timeline.current} emptyLabel="No policy currently in effect." />
        <TimelineList title="Upcoming" policies={timeline.upcoming} emptyLabel="No future-effective policies." />
        <TimelineList title="History" policies={timeline.history} emptyLabel="No archived or expired policies." />
      </div>
      {timeline.other_active.length > 0 && (
        <div className="mt-4">
          <TimelineList
            title="Other active (not selected)"
            policies={timeline.other_active}
            emptyLabel=""
          />
        </div>
      )}
    </div>
  );
}
