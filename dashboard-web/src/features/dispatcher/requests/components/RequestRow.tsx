import { ChevronRight } from 'lucide-react';
import type { ShipmentRequestListItem } from '../types/request.types';
import RequestStatusBadge from './RequestStatusBadge';
import {
  formatCargoSummary,
  formatPriceSummaryLabel,
  getRequestPrimaryAction,
} from '../utils/requestMapper';

interface RequestRowProps {
  request: ShipmentRequestListItem;
  onOpen: (request: ShipmentRequestListItem) => void;
}

export default function RequestRow({ request, onOpen }: RequestRowProps) {
  const nextAction = getRequestPrimaryAction(request.status);
  const cargo = formatCargoSummary(request);
  const price = formatPriceSummaryLabel(request.priceSummary);

  return (
    <tr
      className="border-b border-white/5 transition hover:bg-white/[0.03] cursor-pointer"
      onClick={() => onOpen(request)}
      data-testid={`request-row-${request.id}`}
    >
      <td className="px-4 py-3">
        <div className="flex flex-col gap-0.5">
          <span className="font-mono text-sm font-bold text-white">{request.requestNumber}</span>
          <span className="text-[11px] text-slate-500">{request.referenceNumber || 'No reference'}</span>
        </div>
      </td>
      <td className="px-4 py-3">
        <div className="flex flex-col gap-0.5">
          <span className="text-sm font-semibold text-slate-200">
            {request.customer?.companyName || 'Unavailable'}
          </span>
          <span className="text-[11px] text-slate-500">{request.customer?.companyCode || '—'}</span>
        </div>
      </td>
      <td className="px-4 py-3">
        <div className="flex flex-col gap-0.5">
          <span className="text-sm text-slate-200">{request.routeLabel}</span>
          <span className="text-[11px] text-slate-500">{cargo}</span>
        </div>
      </td>
      <td className="px-4 py-3">
        <RequestStatusBadge status={request.status} />
      </td>
      <td className="px-4 py-3 text-sm capitalize text-slate-300">{request.priority || '—'}</td>
      <td className="px-4 py-3 font-mono text-xs text-slate-300">{price}</td>
      <td className="px-4 py-3 font-mono text-xs text-slate-400">{request.requestedPickupDate || '—'}</td>
      <td className="px-4 py-3 text-right">
        <button
          type="button"
          aria-label={`${nextAction.label} for ${request.requestNumber}`}
          className="inline-flex items-center gap-1 rounded-xl border border-cyan-400/30 bg-cyan-500/10 px-2.5 py-1.5 text-[11px] font-bold uppercase tracking-wider text-cyan-100 hover:bg-cyan-500/20"
          onClick={(event) => {
            event.stopPropagation();
            onOpen(request);
          }}
        >
          {nextAction.label} <ChevronRight className="h-3.5 w-3.5" />
        </button>
      </td>
    </tr>
  );
}
