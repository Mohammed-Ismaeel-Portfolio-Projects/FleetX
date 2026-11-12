import type { ShipmentRequestDetail } from '../types/request.types';
import RequestStatusBadge from './RequestStatusBadge';
import {
  collectMissingReviewFields,
  formatPriceSummaryLabel,
  formatRequestStatusLabel,
  getRequestPrimaryAction,
  getRequestReviewPhase,
  getRequestReviewPhaseLabel,
} from '../utils/requestMapper';

interface RequestReviewSummaryProps {
  request: ShipmentRequestDetail;
}

function ReadinessItem({ label, ok }: { label: string; ok: boolean }) {
  return (
    <div className="flex items-center justify-between rounded-xl border border-white/5 bg-white/[0.02] px-3 py-2">
      <span className="text-xs text-slate-300">{label}</span>
      <span className={`text-[11px] font-bold uppercase tracking-wider ${ok ? 'text-emerald-300' : 'text-amber-300'}`}>
        {ok ? 'Complete' : 'Missing'}
      </span>
    </div>
  );
}

export default function RequestReviewSummary({ request }: RequestReviewSummaryProps) {
  const phase = getRequestReviewPhase(request.status);
  const missing = collectMissingReviewFields(request);
  const nextAction = getRequestPrimaryAction(request.status, request.convertedShipmentId);
  const estimate = formatPriceSummaryLabel(request.priceSummary || (request.priceSnapshot
    ? { currency: request.priceSnapshot.currency, total: request.priceSnapshot.total }
    : null));

  const routeOk = Boolean((request.pickupCity || request.pickupCountry) && (request.deliveryCity || request.deliveryCountry));
  const cargoOk = Boolean(request.cargoType);
  const scheduleOk = Boolean(request.requestedPickupDate && request.requestedDeliveryDate);
  const requestOk = Boolean(request.customer?.companyName && request.submittedBy?.name);
  const pricingOk = Boolean(request.priceSummary || request.priceSnapshot);

  const isHistorical = ['approved', 'converted', 'rejected', 'cancelled', 'completed'].includes(request.status.toLowerCase()) || Boolean(request.convertedShipmentId);

  return (
    <section className="rounded-2xl border border-white/5 bg-[#0E1320] p-5" data-testid="request-review-summary">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <p className="text-[10px] font-bold uppercase tracking-widest text-slate-500">Operations workspace</p>
          <h2 className="mt-1 font-mono text-xl font-bold text-white">{request.requestNumber}</h2>
          <p className="mt-1 text-sm text-slate-400">
            {request.customer?.companyName || 'Customer unavailable'} · {request.routeLabel}
          </p>
        </div>
        <div className="flex flex-wrap items-center gap-2">
          <RequestStatusBadge status={request.status} />
          <span className="rounded-full border border-white/10 bg-white/5 px-2.5 py-1 text-[10px] font-semibold uppercase tracking-widest text-slate-300">
            {request.priority || 'priority n/a'}
          </span>
          {isHistorical ? (
            <span className="rounded-full border border-cyan-400/20 bg-cyan-500/10 px-2.5 py-1 text-[10px] font-semibold uppercase tracking-widest text-cyan-100">
              Read-Only Audit Record
            </span>
          ) : (
            <span className="rounded-full border border-cyan-400/20 bg-cyan-500/10 px-2.5 py-1 text-[10px] font-semibold uppercase tracking-widest text-cyan-100">
              Next: {nextAction.label}
            </span>
          )}
        </div>
      </div>

      <p className="mt-4 text-sm text-slate-300">{getRequestReviewPhaseLabel(phase)}</p>
      <p className="mt-1 text-xs text-slate-500">
        Status: <span className="font-mono text-slate-300">{formatRequestStatusLabel(request.status)}</span>
        {' · '}
        Estimate: <span className="font-mono text-slate-300">{estimate}</span>
        <span className="text-slate-600"> (estimate, not final invoice)</span>
      </p>

      <div className="mt-4 grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-5">
        <ReadinessItem label="Request data" ok={requestOk} />
        <ReadinessItem label="Route" ok={routeOk} />
        <ReadinessItem label="Cargo" ok={cargoOk} />
        <ReadinessItem label="Schedule" ok={scheduleOk} />
        <ReadinessItem label="Pricing" ok={pricingOk} />
      </div>

      {missing.length > 0 ? (
        <div className="mt-4 rounded-xl border border-amber-400/20 bg-amber-500/10 px-3 py-2">
          <p className="text-[10px] font-bold uppercase tracking-wider text-amber-200">Missing for review</p>
          <p className="mt-1 text-xs text-amber-50">{missing.join(' · ')}</p>
        </div>
      ) : (
        <div className="mt-4 rounded-xl border border-emerald-400/20 bg-emerald-500/10 px-3 py-2 text-xs text-emerald-100">
          Core review fields from the API are present. Use the decision panel for actions allowed by the current status.
        </div>
      )}
    </section>
  );
}
