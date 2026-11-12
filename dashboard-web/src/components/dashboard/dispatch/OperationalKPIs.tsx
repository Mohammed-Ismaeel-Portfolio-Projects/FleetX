import { Package, Users, Truck, AlertTriangle } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { Link } from 'react-router-dom';
import { dashboardService, type DashboardKPI, DashboardApiError } from '../../../features/dispatcher/dashboard/services/dashboard.service';

interface KPIData {
  icon: any;
  value: string;
  label: string;
  color: string;
  bgColor: string;
  href: string;
}

/** Order: attention first, then workload capacity. */
const kpiConfig: Omit<KPIData, 'value'>[] = [
  {
    icon: AlertTriangle,
    label: 'Critical Issues',
    color: 'text-orange-400',
    bgColor: 'bg-orange-500/10 border-orange-500/20',
    href: '/dispatcher/notifications',
  },
  {
    icon: Package,
    label: 'Active Shipments',
    color: 'text-blue-400',
    bgColor: 'bg-blue-500/10 border-blue-500/20',
    href: '/dispatcher/shipments',
  },
  {
    icon: Truck,
    label: 'Vehicles on Assignment',
    color: 'text-cyan-400',
    bgColor: 'bg-cyan-500/10 border-cyan-500/20',
    href: '/dispatcher/fleet',
  },
  {
    icon: Users,
    label: 'Drivers Available',
    color: 'text-emerald-400',
    bgColor: 'bg-emerald-500/10 border-emerald-500/20',
    href: '/dispatcher/drivers',
  },
];

function formatKpiValue(value: number | null | undefined): string {
  if (value === null || value === undefined) {
    return 'N/A';
  }
  return value.toString();
}

function mapKpiData(kpi: DashboardKPI): KPIData[] {
  return [
    { ...kpiConfig[0], value: formatKpiValue(kpi.critical_alerts) },
    { ...kpiConfig[1], value: formatKpiValue(kpi.active_shipments) },
    { ...kpiConfig[2], value: formatKpiValue(kpi.vehicles_in_transit) },
    { ...kpiConfig[3], value: formatKpiValue(kpi.available_drivers) },
  ];
}

function EnterpriseKPICard({ data }: { data: KPIData }) {
  const Icon = data.icon;

  return (
    <Link
      to={data.href}
      className="bg-[#0E1320] border border-white/5 rounded-xl p-4 block hover:border-white/15 transition-colors"
    >
      <div className={`w-8 h-8 rounded-lg ${data.bgColor} flex items-center justify-center border mb-3`}>
        <Icon className={`w-4 h-4 ${data.color}`} />
      </div>
      <div className="mb-1">
        <span className="text-2xl font-extrabold text-white tracking-tight font-sans">
          {data.value}
        </span>
      </div>
      <span className="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">
        {data.label}
      </span>
    </Link>
  );
}

interface OperationalKPIsProps {
  refreshKey?: number;
  onLoaded?: (meta: { refreshedAt: Date; kpi: DashboardKPI }) => void;
}

export default function OperationalKPIs({ refreshKey = 0, onLoaded }: OperationalKPIsProps) {
  const [kpiData, setKpiData] = useState<KPIData[]>(kpiConfig.map(config => ({
    ...config,
    value: '—',
  })));
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);
  const hasLoadedRef = useRef(false);
  const inFlightRef = useRef(false);

  useEffect(() => {
    let cancelled = false;

    async function fetchKPI() {
      if (inFlightRef.current) return;
      inFlightRef.current = true;
      const silent = hasLoadedRef.current;

      try {
        if (!silent) {
          setLoading(true);
        }
        setError(null);
        const kpi = await dashboardService.getKPI();
        if (cancelled) return;
        setKpiData(mapKpiData(kpi));
        hasLoadedRef.current = true;
        onLoaded?.({ refreshedAt: new Date(), kpi });
      } catch (err) {
        if (cancelled) return;
        setError(err instanceof DashboardApiError ? err.message : 'Unable to load operational KPIs.');
        console.error('Error fetching KPI:', err);
      } finally {
        inFlightRef.current = false;
        if (!cancelled && !silent) {
          setLoading(false);
        }
      }
    }

    fetchKPI();

    return () => {
      cancelled = true;
    };
  }, [refreshKey, onLoaded]);

  if (error && !hasLoadedRef.current) {
    return (
      <div className="bg-red-500/10 border border-red-500/20 rounded-xl p-4 text-red-400">
        <p className="text-sm font-medium">KPI data unavailable</p>
        <p className="text-xs text-red-300/80 mt-1">{error}</p>
      </div>
    );
  }

  return (
    <div className="space-y-2">
      {loading ? (
        <p className="text-xs text-slate-500">Loading operational KPIs…</p>
      ) : null}
      <div className="grid grid-cols-2 lg:grid-cols-4 gap-3">
        {kpiData.map((kpi) => (
          <EnterpriseKPICard key={kpi.label} data={kpi} />
        ))}
      </div>
    </div>
  );
}
