import { useAuth } from '../../../../core/auth';
import { Truck, CheckCircle, Wrench, WifiOff, TrendingUp } from 'lucide-react';
import { getFleetKPIs } from '../services/fleet-operations.service';
import { useEffect, useRef, useState } from 'react';

interface KPICardProps {
  title: string;
  value: string;
  icon: any;
  color: string;
}

function KPICard({ title, value, icon: Icon, color }: KPICardProps) {
  return (
    <div className="bg-[#0E1320] border border-white/5 rounded-2xl p-5 shadow-lg">
      <div className="flex items-start justify-between mb-4">
        <div className={`w-12 h-12 rounded-xl ${color} flex items-center justify-center`}>
          <Icon className="w-6 h-6 text-white" />
        </div>
      </div>

      <div>
        <p className="text-3xl font-bold text-white font-mono">{value}</p>
        <p className="text-xs text-slate-400 uppercase tracking-wider mt-1">{title}</p>
      </div>
    </div>
  );
}

interface FleetKPIsProps {
  refreshKey?: number;
}

export default function FleetKPIs({ refreshKey = 0 }: FleetKPIsProps) {
  const { user } = useAuth();
  const isPartner = Boolean(user?.isPartnerCarrier);
  const [kpiData, setKpiData] = useState<KPICardProps[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const hasLoadedRef = useRef(false);
  const inFlightRef = useRef(false);

  useEffect(() => {
    let cancelled = false;

    async function loadKPIs() {
      if (isPartner) {
        setLoading(false);
        return;
      }
      if (inFlightRef.current) return;
      inFlightRef.current = true;
      const silent = hasLoadedRef.current;

      try {
        if (!silent) {
          setLoading(true);
        }
        const data = await getFleetKPIs();
        if (cancelled) return;

        setKpiData([
          {
            title: 'Total Fleet',
            value: data.total_fleet.toString(),
            icon: Truck,
            color: 'bg-[#0250C5]',
          },
          {
            title: 'Available',
            value: data.available.toString(),
            icon: CheckCircle,
            color: 'bg-emerald-500',
          },
          {
            title: 'Assigned',
            value: data.assigned.toString(),
            icon: Truck,
            color: 'bg-blue-500',
          },
          {
            title: 'Maintenance',
            value: data.maintenance.toString(),
            icon: Wrench,
            color: 'bg-amber-500',
          },
          {
            title: 'Offline',
            value: data.offline.toString(),
            icon: WifiOff,
            color: 'bg-slate-500',
          },
          {
            title: 'Utilization',
            value: `${data.utilization}%`,
            icon: TrendingUp,
            color: 'bg-cyan-500',
          },
        ]);
        setError(null);
        hasLoadedRef.current = true;
      } catch (err) {
        if (cancelled) return;
        console.error('Error loading fleet KPIs:', err);
        if (!hasLoadedRef.current) {
          setError('Failed to load fleet KPIs');
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

  if (isPartner) {
    return null;
  }

  if (loading && !hasLoadedRef.current) {
    return (
      <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
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
    <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
      {kpiData.map((kpi) => (
        <KPICard key={kpi.title} {...kpi} />
      ))}
    </div>
  );
}
