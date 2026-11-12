import type { ShipmentRequestListItem, ShipmentRequestListMeta } from '../types/request.types';
import RequestRow from './RequestRow';

interface RequestsTableProps {
  requests: ShipmentRequestListItem[];
  pagination: ShipmentRequestListMeta;
  onOpen: (request: ShipmentRequestListItem) => void;
  onPageChange: (page: number) => void;
}

export default function RequestsTable({
  requests,
  pagination,
  onOpen,
  onPageChange,
}: RequestsTableProps) {
  return (
    <div className="overflow-hidden rounded-2xl border border-white/5 bg-[#0E1320]">
      <div className="overflow-x-auto">
        <table className="min-w-full text-left">
          <thead className="border-b border-white/5 bg-white/[0.02] text-[10px] font-bold uppercase tracking-[0.2em] text-slate-500">
            <tr>
              <th className="px-4 py-3">Request</th>
              <th className="px-4 py-3">Customer</th>
              <th className="px-4 py-3">Route / Cargo</th>
              <th className="px-4 py-3">Status</th>
              <th className="px-4 py-3">Priority</th>
              <th className="px-4 py-3">Estimate</th>
              <th className="px-4 py-3">Pickup</th>
              <th className="px-4 py-3 text-right">Next action</th>
            </tr>
          </thead>
          <tbody>
            {requests.map((request) => (
              <RequestRow key={request.id} request={request} onOpen={onOpen} />
            ))}
          </tbody>
        </table>
      </div>

      <div className="flex flex-col gap-3 border-t border-white/5 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
        <p className="text-xs text-slate-500">
          Showing page {pagination.current_page} of {pagination.last_page} · {pagination.total} requests
        </p>
        <div className="flex items-center gap-2">
          <button
            type="button"
            disabled={pagination.current_page <= 1}
            onClick={() => onPageChange(pagination.current_page - 1)}
            className="rounded-xl border border-white/10 bg-white/5 px-3 py-1.5 text-xs font-semibold text-slate-300 disabled:cursor-not-allowed disabled:opacity-40"
          >
            Previous
          </button>
          <button
            type="button"
            disabled={pagination.current_page >= pagination.last_page}
            onClick={() => onPageChange(pagination.current_page + 1)}
            className="rounded-xl border border-white/10 bg-white/5 px-3 py-1.5 text-xs font-semibold text-slate-300 disabled:cursor-not-allowed disabled:opacity-40"
          >
            Next
          </button>
        </div>
      </div>
    </div>
  );
}
