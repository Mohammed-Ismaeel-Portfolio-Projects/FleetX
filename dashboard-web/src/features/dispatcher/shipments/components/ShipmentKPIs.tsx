import { Package, Clock, Truck, CheckCircle, Zap } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { shipmentsService } from '../services/shipments.service';

interface KPICardData {
  icon: any;
  value: string;
  label: string;
  detail: string;
  color: string;
  bgColor: string;
}

function EnterpriseKPICard({ data }: { data: KPICardData }) {
  const Icon = data.icon;

  return (
    <div className="bg-[#0E1320] border border-white/5 rounded-2xl p-5 shadow-lg relative overflow-hidden">
      <div className="relative z-10">
        <div className="flex justify-between items-start mb-4">
          <div className={`w-12 h-12 rounded-xl ${data.bgColor} flex items-center justify-center border`}>
            <Icon className={`w-6 h-6 ${data.color}`} />
          </div>
        </div>

        <div className="mb-2">
          <span className="text-3xl font-extrabold text-white tracking-tight font-sans">
            {data.value}
          </span>
        </div>

        <div className="mb-3">
          <span className="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">
            {data.label}
          </span>
        </div>

        <div>
          <span className="text-[10px] font-medium text-slate-400">
            {data.detail}
          </span>
        </div>
      </div>
    </div>
  );
}

interface ShipmentKPIsProps {
  refreshKey?: number;
}

function mapKpis(data: Awaited<ReturnType<typeof shipmentsService.getShipmentKPIs>>): KPICardData[] {
  return [
    {
      icon: Package,
      value: data.total_fleet.toString(),
      label: 'Total Shipments',
      detail: 'All shipments',
      color: 'text-blue-400',
      bgColor: 'bg-blue-500/10 border-blue-500/20',
    },
    {
      icon: Clock,
      value: data.pending.toString(),
      label: 'Pending',
      detail: `${data.pending_percentage}% of total`,
      color: 'text-amber-400',
      bgColor: 'bg-amber-500/10 border-amber-500/20',
    },
    {
      icon: Truck,
      value: data.assigned.toString(),
      label: 'Assigned',
      detail: `${data.assigned_percentage}% of total`,
      color: 'text-purple-400',
      bgColor: 'bg-purple-500/10 border-purple-500/20',
    },
    {
      icon: Truck,
      value: data.in_transit.toString(),
      label: 'In Transit',
      detail: `${data.in_transit_percentage}% of total`,
      color: 'text-emerald-400',
      bgColor: 'bg-emerald-500/10 border-emerald-500/20',
    },
    {
      icon: CheckCircle,
      value: data.delivered.toString(),
      label: 'Delivered',
      detail: `${data.delivered_percentage}% of total`,
      color: 'text-emerald-400',
      bgColor: 'bg-emerald-500/10 border-emerald-500/20',
    },
    {
      icon: Zap,
      value: (data.urgent ?? data.critical ?? 0).toString(),
      label: 'Urgent',
      detail: 'priority = urgent',
      color: 'text-red-400',
      bgColor: 'bg-red-500/10 border-red-500/20',
    },
  ];
}

export default function ShipmentKPIs({ refreshKey = 0 }: ShipmentKPIsProps) {
  const [kpiData, setKpiData] = useState<KPICardData[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const hasLoadedRef = useRef(false);
  const inFlightRef = useRef(false);

  useEffect(() => {
    let cancelled = false;

    async function loadKPIs() {
      if (inFlightRef.current) return;
      inFlightRef.current = true;
      const silent = hasLoadedRef.current;

      try {
        if (!silent) {
          setLoading(true);
        }
        const data = await shipmentsService.getShipmentKPIs();
        if (cancelled) return;
        setKpiData(mapKpis(data));
        setError(null);
        hasLoadedRef.current = true;
      } catch (err) {
        if (cancelled) return;
        console.error('Error loading shipment KPIs:', err);
        if (!hasLoadedRef.current) {
          setError('Failed to load shipment KPIs');
        }
      } finally {
        inFlightRef.current = false;
        if (!cancelled && !silent) {
          setLoading(false);
        }
      }
    }

    void loadKPIs();

    return () => {
      cancelled = true;
    };
  }, [refreshKey]);

  if (loading && !hasLoadedRef.current) {
    return (
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4">
        {[...Array(6)].map((_, i) => (
          <div key={i} className="bg-[#0E1320] border border-white/5 rounded-2xl p-5 animate-pulse">
            <div className="h-6 w-12 bg-white/10 rounded mb-4"></div>
            <div className="h-8 w-16 bg-white/10 rounded mb-3"></div>
          </div>
        ))}
      </div>
    );
  }

  if (error && !hasLoadedRef.current) {
    return (
      <div className="bg-red-500/10 border border-red-500/20 rounded-2xl p-4 text-red-400">
        {error}
      </div>
    );
  }

  return (
    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4">
      {kpiData.map((kpi) => (
        <EnterpriseKPICard key={kpi.label} data={kpi} />
      ))}
    </div>
  );
}
