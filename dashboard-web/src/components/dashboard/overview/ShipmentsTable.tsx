import { useEffect, useState } from 'react';
import { ClipboardList } from 'lucide-react';
import { Link } from 'react-router-dom';
import {
  shipmentsService,
  ShipmentApiError,
} from '../../../features/admin/shipments/services/shipments.service';
import type { Shipment } from '../../../features/admin/shipments/types/shipment.types';

function formatPlace(city: string | null, country: string | null): string {
  const value = [city, country].filter(Boolean).join(', ');
  return value || 'Unavailable';
}

export default function ShipmentsTable() {
  const [shipments, setShipments] = useState<Shipment[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    let cancelled = false;

    async function load() {
      setIsLoading(true);
      setError(null);
      try {
        const result = await shipmentsService.getShipments({
          page: 1,
          per_page: 8,
          sort_by: 'created_at',
          sort_direction: 'desc',
        });
        if (cancelled) return;
        setShipments(Array.isArray(result.data) ? result.data : []);
      } catch (err) {
        if (cancelled) return;
        setShipments([]);
        setError(err instanceof ShipmentApiError ? err.message : 'Failed to load shipments');
      } finally {
        if (!cancelled) {
          setIsLoading(false);
        }
      }
    }

    void load();
    return () => {
      cancelled = true;
    };
  }, []);

  return (
    <div className="bg-[#0E1320] border border-white/5 rounded-2xl p-5 flex flex-col shadow-md select-none w-full text-left">
      <div className="flex items-center justify-between gap-2 mb-6 flex-shrink-0">
        <div className="flex items-center gap-2">
          <ClipboardList className="w-4.5 h-4.5 text-blue-500" />
          <h3 className="text-sm font-bold text-white font-sans tracking-tight">
            Recent Shipments
          </h3>
        </div>
        <Link
          to="/admin/shipments"
          className="text-[11px] font-semibold text-[#0250C5] hover:text-[#3b7ae8]"
        >
          Open shipments
        </Link>
      </div>

      {isLoading && (
        <p className="text-sm text-slate-400">Loading shipments…</p>
      )}

      {!isLoading && error && (
        <p className="text-sm text-amber-200">
          Shipment list unavailable. {error}
        </p>
      )}

      {!isLoading && !error && shipments.length === 0 && (
        <p className="text-sm text-slate-400">No shipments returned from GET /shipments.</p>
      )}

      {!isLoading && !error && shipments.length > 0 && (
        <div className="overflow-x-auto w-full scrollbar-none">
          <table className="w-full min-w-[720px] border-collapse">
            <thead>
              <tr className="border-b border-white/5 pb-3">
                <th className="text-[10px] font-bold text-slate-500 uppercase tracking-widest pb-3 font-sans text-left">
                  Shipment
                </th>
                <th className="text-[10px] font-bold text-slate-500 uppercase tracking-widest pb-3 font-sans text-left">
                  Origin
                </th>
                <th className="text-[10px] font-bold text-slate-500 uppercase tracking-widest pb-3 font-sans text-left">
                  Destination
                </th>
                <th className="text-[10px] font-bold text-slate-500 uppercase tracking-widest pb-3 font-sans text-left">
                  Status
                </th>
                <th className="text-[10px] font-bold text-slate-500 uppercase tracking-widest pb-3 font-sans text-left">
                  Planned delivery
                </th>
              </tr>
            </thead>
            <tbody className="divide-y divide-white/5">
              {shipments.map((shipment) => (
                <tr key={shipment.id} className="hover:bg-white/[0.02] transition-colors duration-150">
                  <td className="py-3.5 text-xs font-bold text-slate-200 font-mono">
                    {shipment.shipment_number || 'Unavailable'}
                  </td>
                  <td className="py-3.5 text-xs font-medium text-slate-300 font-sans">
                    {formatPlace(shipment.pickup_city, shipment.pickup_country)}
                  </td>
                  <td className="py-3.5 text-xs font-medium text-slate-300 font-sans">
                    {formatPlace(shipment.delivery_city, shipment.delivery_country)}
                  </td>
                  <td className="py-3.5 text-xs font-medium text-slate-300 font-sans">
                    {shipment.status?.name || 'Unavailable'}
                  </td>
                  <td className="py-3.5 text-xs font-medium text-slate-300 font-mono">
                    {shipment.planned_delivery_date || 'Unavailable'}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </div>
  );
}
