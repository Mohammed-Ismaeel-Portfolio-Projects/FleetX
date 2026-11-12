import { useState, useEffect, useRef } from 'react';
import { MapPin, User, Truck } from 'lucide-react';
import { Link } from 'react-router-dom';
import WidgetState from './WidgetState';
import { cn } from '../../../lib/utils';
import { shipmentsService } from '../../../features/admin/shipments/services/shipments.service';

const MAX_ROWS = 8;

interface ShipmentQueueItem {
  id: string;
  priority: string | null;
  customer: string;
  origin: string;
  destination: string;
  status: string;
  driver: string | null;
  plannedDelivery: string;
}

const PRIORITY_RANK: Record<string, number> = {
  Critical: 0,
  High: 1,
  Medium: 2,
  Low: 3,
};

function mapShipmentFromApi(shipment: any): ShipmentQueueItem {
  const priority = shipment.priority ? String(shipment.priority) : null;

  return {
    id: shipment.shipment_number || String(shipment.id),
    priority,
    customer: shipment.customerCompany?.company_name || shipment.pickup_company || 'Unknown customer',
    origin: shipment.pickup_city || shipment.pickup_address || '—',
    destination: shipment.delivery_city || shipment.delivery_address || '—',
    status: shipment.status?.name || 'Unknown',
    driver: shipment.activeAssignment?.driver?.full_name || null,
    plannedDelivery: shipment.planned_delivery_date
      ? new Date(shipment.planned_delivery_date).toLocaleString([], {
          month: 'short',
          day: 'numeric',
          hour: '2-digit',
          minute: '2-digit',
        })
      : '—',
  };
}

function getPriorityBadgeClasses(priority: string) {
  switch (priority) {
    case 'Critical':
      return 'text-red-300 bg-red-500/15 border-red-500/25';
    case 'High':
      return 'text-orange-300 bg-orange-500/15 border-orange-500/25';
    case 'Medium':
      return 'text-amber-300 bg-amber-500/15 border-amber-500/25';
    case 'Low':
      return 'text-slate-400 bg-slate-500/10 border-slate-500/20';
    default:
      return 'text-slate-500 bg-white/5 border-white/10';
  }
}

function getPriorityRowClasses(priority: string | null) {
  switch (priority) {
    case 'Critical':
      return 'bg-red-500/[0.07] border-l-2 border-l-red-500/50';
    case 'High':
      return 'bg-orange-500/[0.07] border-l-2 border-l-orange-500/45';
    case 'Medium':
      return 'bg-amber-500/[0.06] border-l-2 border-l-amber-500/40';
    case 'Low':
      return 'bg-transparent border-l-2 border-l-transparent';
    default:
      return 'bg-transparent border-l-2 border-l-transparent';
  }
}

function ShipmentRow({ shipment }: { shipment: ShipmentQueueItem }) {
  return (
    <div
      className={cn(
        'flex flex-col gap-2 sm:flex-row sm:items-center sm:gap-4 px-3 py-3 border-b border-white/5 last:border-0',
        getPriorityRowClasses(shipment.priority)
      )}
    >
      <div className="flex items-center gap-3 min-w-0 sm:flex-1">
        {shipment.priority ? (
          <div className={`px-2 py-1 rounded text-[10px] font-bold uppercase tracking-wider border flex-shrink-0 ${getPriorityBadgeClasses(shipment.priority)}`}>
            {shipment.priority}
          </div>
        ) : (
          <div className="px-2 py-1 rounded text-[10px] font-bold uppercase tracking-wider border border-white/10 text-slate-500 flex-shrink-0">
            —
          </div>
        )}
        <div className="min-w-0 flex-1">
          <p className="text-xs font-semibold text-white truncate">{shipment.customer}</p>
          <p className="text-[10px] text-slate-500 truncate">{shipment.id}</p>
        </div>
      </div>

      <div className="flex items-center gap-2 min-w-0 sm:min-w-[160px] md:min-w-[180px]">
        <MapPin className="w-3 h-3 text-slate-500 flex-shrink-0" />
        <div className="min-w-0">
          <p className="text-[10px] text-slate-400 truncate">{shipment.origin}</p>
          <p className="text-[10px] text-slate-500 truncate">→ {shipment.destination}</p>
        </div>
      </div>

      <div className="flex items-center gap-2 sm:min-w-[110px]">
        <Truck className="w-3.5 h-3.5 text-slate-500 flex-shrink-0" />
        <span className="text-[10px] text-slate-300 truncate">{shipment.status}</span>
      </div>

      <div className="flex items-center gap-2 sm:min-w-[100px]">
        <User className="w-3 h-3 text-slate-500 flex-shrink-0" />
        <span className="text-[10px] text-slate-400 truncate">
          {shipment.driver || 'Unassigned'}
        </span>
      </div>

      <div className="sm:text-right sm:min-w-[110px]">
        <p className="text-[9px] text-slate-500 uppercase tracking-wider">Planned</p>
        <p className="text-[10px] font-mono text-slate-300">{shipment.plannedDelivery}</p>
      </div>
    </div>
  );
}

export default function ActiveOperationsPanel({ refreshKey = 0 }: { refreshKey?: number }) {
  const [shipments, setShipments] = useState<ShipmentQueueItem[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const hasLoadedRef = useRef(false);
  const inFlightRef = useRef(false);

  const load = async (silent = false) => {
    if (inFlightRef.current) return;
    inFlightRef.current = true;
    try {
      if (!silent) setIsLoading(true);
      setError(null);
      const data = await shipmentsService.getShipmentQueue();
      const mapped = data
        .map(mapShipmentFromApi)
        .sort((a, b) => {
          const aRank = a.priority ? (PRIORITY_RANK[a.priority] ?? 99) : 99;
          const bRank = b.priority ? (PRIORITY_RANK[b.priority] ?? 99) : 99;
          return aRank - bRank;
        })
        .slice(0, MAX_ROWS);
      setShipments(mapped);
      hasLoadedRef.current = true;
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Unable to load the shipment queue.');
      console.error('Error fetching shipment queue:', err);
    } finally {
      inFlightRef.current = false;
      if (!silent) setIsLoading(false);
    }
  };

  useEffect(() => {
    load(hasLoadedRef.current);
  }, [refreshKey]);

  return (
    <div className="bg-[#0E1320] border border-white/5 rounded-xl p-4 sm:p-5">
      <div className="flex flex-wrap items-center justify-between gap-2 mb-3">
        <div>
          <h3 className="text-sm font-bold text-white tracking-tight">Active Operations</h3>
          <p className="text-[10px] text-slate-500 mt-1">
            Today&apos;s shipment queue (priority first)
          </p>
        </div>
        <Link
          to="/dispatcher/shipments"
          className="text-[10px] font-bold text-[#00E5FF] uppercase tracking-wider hover:text-[#00E5FF]/80 transition-colors"
        >
          View All Shipments →
        </Link>
      </div>

      {isLoading ? (
        <WidgetState state="loading" message="Loading today's shipment queue…" />
      ) : error && !hasLoadedRef.current ? (
        <WidgetState state="error" message={error} onRetry={() => load(false)} />
      ) : shipments.length === 0 ? (
        <WidgetState
          state="empty"
          message="No active shipment operations at this time."
        />
      ) : (
        <div className="rounded-xl border border-white/5 overflow-hidden">
          {shipments.map((shipment) => (
            <ShipmentRow key={shipment.id} shipment={shipment} />
          ))}
        </div>
      )}
    </div>
  );
}
