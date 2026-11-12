import { useState } from 'react';
import { ChevronLeft, ChevronRight, ClipboardList } from 'lucide-react';
import type { Shipment, ShipmentStatus } from '../types/shipment.types';
import ShipmentRow from './ShipmentRow';

interface ShipmentsTableProps {
  shipments: Shipment[];
  onAssignClick: (shipmentId: string) => void;
  onStatusChange: (shipmentId: string, status: ShipmentStatus) => void;
}

export default function ShipmentsTable({
  shipments,
  onAssignClick,
  onStatusChange,
}: ShipmentsTableProps) {
  const [currentPage, setCurrentPage] = useState(1);
  const itemsPerPage = 5;

  const totalPages = Math.ceil(shipments.length / itemsPerPage);
  const startIndex = (currentPage - 1) * itemsPerPage;
  const currentItems = shipments.slice(startIndex, startIndex + itemsPerPage);

  const handlePageChange = (page: number) => {
    if (page >= 1 && page <= totalPages) {
      setCurrentPage(page);
    }
  };

  return (
    <div className="bg-[#0E1320] border border-white/5 rounded-2xl p-5 flex flex-col shadow-md w-full text-left select-none">
      
      {/* Header Info */}
      <div className="flex items-center gap-2 mb-6 flex-shrink-0">
        <ClipboardList className="w-4.5 h-4.5 text-[#0250C5]" />
        <h3 className="text-sm font-bold text-white font-sans tracking-tight">
          Operational Cargo Manifests
        </h3>
      </div>

      {/* Table Frame container */}
      <div className="overflow-x-auto w-full scrollbar-none">
        <table className="w-full min-w-[900px] border-collapse">
          <thead>
            <tr className="border-b border-white/5 pb-3">
              <th className="text-[10px] font-bold text-slate-500 uppercase tracking-widest pb-3 font-sans w-[12%] text-left">
                Shipment ID
              </th>
              <th className="text-[10px] font-bold text-slate-500 uppercase tracking-widest pb-3 font-sans w-[16%] text-left">
                Customer
              </th>
              <th className="text-[10px] font-bold text-slate-500 uppercase tracking-widest pb-3 font-sans w-[22%] text-left">
                Pickup → Destination
              </th>
              <th className="text-[10px] font-bold text-slate-500 uppercase tracking-widest pb-3 font-sans w-[12%] text-left">
                Status
              </th>
              <th className="text-[10px] font-bold text-slate-500 uppercase tracking-widest pb-3 font-sans w-[10%] text-left">
                Priority
              </th>
              <th className="text-[10px] font-bold text-slate-500 uppercase tracking-widest pb-3 font-sans w-[14%] text-left">
                Driver / Vehicle
              </th>
              <th className="text-[10px] font-bold text-slate-500 uppercase tracking-widest pb-3 font-sans w-[8%] text-left">
                ETA
              </th>
              <th className="text-[10px] font-bold text-slate-500 uppercase tracking-widest pb-3 font-sans w-[6%] text-right">
                Actions
              </th>
            </tr>
          </thead>
          <tbody className="divide-y divide-white/2">
            {currentItems.map((shipment) => (
              <ShipmentRow
                key={shipment.id}
                shipment={shipment}
                onAssignClick={onAssignClick}
                onStatusChange={onStatusChange}
              />
            ))}

            {shipments.length === 0 && (
              <tr>
                <td colSpan={8} className="py-8 text-center text-slate-500 text-xs font-sans">
                  No shipments matched your search or filters.
                </td>
              </tr>
            )}
          </tbody>
        </table>
      </div>

      {/* Pagination row */}
      {totalPages > 1 && (
        <div className="flex items-center justify-between mt-5 pt-4 border-t border-white/5 flex-shrink-0">
          <span className="text-[10px] text-slate-500 font-sans">
            Showing <span className="text-slate-300 font-mono font-bold">{startIndex + 1}</span> to{' '}
            <span className="text-slate-300 font-mono font-bold">
              {Math.min(startIndex + itemsPerPage, shipments.length)}
            </span>{' '}
            of <span className="text-[#00E5FF] font-mono font-bold">{shipments.length}</span> manifests
          </span>

          <div className="flex items-center gap-1.5">
            <button
              onClick={() => handlePageChange(currentPage - 1)}
              disabled={currentPage === 1}
              className="w-7 h-7 rounded-lg border border-white/5 bg-slate-950/20 hover:bg-slate-800 disabled:opacity-30 disabled:hover:bg-transparent text-slate-400 hover:text-white flex items-center justify-center cursor-pointer transition-colors"
            >
              <ChevronLeft className="w-3.5 h-3.5" />
            </button>
            
            <span className="text-xs font-bold text-white font-mono px-2">
              {currentPage} / {totalPages}
            </span>

            <button
              onClick={() => handlePageChange(currentPage + 1)}
              disabled={currentPage === totalPages}
              className="w-7 h-7 rounded-lg border border-white/5 bg-slate-950/20 hover:bg-slate-800 disabled:opacity-30 disabled:hover:bg-transparent text-slate-400 hover:text-white flex items-center justify-center cursor-pointer transition-colors"
            >
              <ChevronRight className="w-3.5 h-3.5" />
            </button>
          </div>
        </div>
      )}

    </div>
  );
}
export type { ShipmentsTableProps };
