import { useAuth } from '../../../../core/auth';
import { motion } from 'framer-motion';
import { Truck, Navigation, Pause, WifiOff, AlertTriangle, Route } from 'lucide-react';
import type { FleetKPIs } from '../types/tracking.types';

interface KPICardData {
  icon: any;
  value: string;
  label: string;
  trend: string;
  trendDirection: 'up' | 'down' | 'neutral';
  progress: number;
  color: string;
  bgColor: string;
}

interface TrackingKPIsProps {
  kpis: FleetKPIs | null;
}

function getKPIData(kpis: FleetKPIs | null): KPICardData[] {
  if (!kpis) {
    return [
      {
        icon: Truck,
        value: '0',
        label: 'Active Vehicles',
        trend: 'Loading...',
        trendDirection: 'neutral',
        progress: 0,
        color: 'text-slate-400',
        bgColor: 'bg-slate-500/10 border-slate-500/20',
      },
      {
        icon: Navigation,
        value: '0',
        label: 'Moving Vehicles',
        trend: 'Loading...',
        trendDirection: 'neutral',
        progress: 0,
        color: 'text-slate-400',
        bgColor: 'bg-slate-500/10 border-slate-500/20',
      },
      {
        icon: Pause,
        value: '0',
        label: 'Idle Vehicles',
        trend: 'Loading...',
        trendDirection: 'neutral',
        progress: 0,
        color: 'text-slate-400',
        bgColor: 'bg-slate-500/10 border-slate-500/20',
      },
      {
        icon: WifiOff,
        value: '0',
        label: 'Offline Vehicles',
        trend: 'Loading...',
        trendDirection: 'neutral',
        progress: 0,
        color: 'text-slate-400',
        bgColor: 'bg-slate-500/10 border-slate-500/20',
      },
      {
        icon: AlertTriangle,
        value: '0',
        label: 'Maintenance',
        trend: 'Loading...',
        trendDirection: 'neutral',
        progress: 0,
        color: 'text-slate-400',
        bgColor: 'bg-slate-500/10 border-slate-500/20',
      },
      {
        icon: Route,
        value: '0',
        label: 'Utilization',
        trend: 'Loading...',
        trendDirection: 'neutral',
        progress: 0,
        color: 'text-slate-400',
        bgColor: 'bg-slate-500/10 border-slate-500/20',
      },
    ];
  }

  return [
    {
      icon: Truck,
      value: kpis.total_fleet.toString(),
      label: 'Total Fleet',
      trend: `${kpis.available_percentage.toFixed(1)}% Available`,
      trendDirection: 'neutral',
      progress: 100,
      color: 'text-emerald-400',
      bgColor: 'bg-emerald-500/10 border-emerald-500/20',
    },
    {
      icon: Navigation,
      value: kpis.assigned.toString(),
      label: 'Assigned',
      trend: 'In Operation',
      trendDirection: 'up',
      progress: kpis.assigned_percentage,
      color: 'text-cyan-400',
      bgColor: 'bg-cyan-500/10 border-cyan-500/20',
    },
    {
      icon: Pause,
      value: kpis.available.toString(),
      label: 'Available',
      trend: 'Ready for Assignment',
      trendDirection: 'neutral',
      progress: kpis.available_percentage,
      color: 'text-amber-400',
      bgColor: 'bg-amber-500/10 border-amber-500/20',
    },
    {
      icon: WifiOff,
      value: kpis.offline.toString(),
      label: 'Offline',
      trend: 'Needs Attention',
      trendDirection: 'down',
      progress: kpis.offline_percentage,
      color: 'text-slate-400',
      bgColor: 'bg-slate-500/10 border-slate-500/20',
    },
    {
      icon: AlertTriangle,
      value: kpis.maintenance.toString(),
      label: 'Maintenance',
      trend: 'In Service',
      trendDirection: 'neutral',
      progress: kpis.maintenance_percentage,
      color: 'text-red-400',
      bgColor: 'bg-red-500/10 border-red-500/20',
    },
    {
      icon: Route,
      value: `${kpis.utilization}%`,
      label: 'Utilization',
      trend: 'Fleet Efficiency',
      trendDirection: 'up',
      progress: kpis.utilization,
      color: 'text-emerald-400',
      bgColor: 'bg-emerald-500/10 border-emerald-500/20',
    },
  ];
}

function EnterpriseKPICard({ data }: { data: KPICardData }) {
  const Icon = data.icon;
  
  return (
    <motion.div
      whileHover={{ y: -4, scale: 1.02 }}
      transition={{ duration: 0.2, ease: [0.16, 1, 0.3, 1] }}
      className="bg-[#0E1320] border border-white/5 rounded-2xl p-5 shadow-lg hover:shadow-xl hover:border-white/10 transition-all cursor-pointer relative overflow-hidden group"
    >
      {/* Background gradient on hover */}
      <div className={`absolute inset-0 bg-gradient-to-br ${data.bgColor} opacity-0 group-hover:opacity-100 transition-opacity duration-300`} />
      
      <div className="relative z-10">
        {/* Header */}
        <div className="flex justify-between items-start mb-4">
          <div className={`w-12 h-12 rounded-xl ${data.bgColor} flex items-center justify-center border`}>
            <Icon className={`w-6 h-6 ${data.color}`} />
          </div>
          <div className="flex items-center gap-1.5">
            {data.trendDirection === 'up' && (
              <span className="text-[10px] font-bold text-emerald-400 bg-emerald-500/10 px-2 py-0.5 rounded-full">
                ↑
              </span>
            )}
            {data.trendDirection === 'down' && (
              <span className="text-[10px] font-bold text-red-400 bg-red-500/10 px-2 py-0.5 rounded-full">
                ↓
              </span>
            )}
          </div>
        </div>

        {/* Value */}
        <div className="mb-2">
          <span className="text-3xl font-extrabold text-white tracking-tight font-sans">
            {data.value}
          </span>
        </div>

        {/* Label */}
        <div className="mb-3">
          <span className="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">
            {data.label}
          </span>
        </div>

        {/* Trend */}
        <div className="mb-3">
          <span className={`text-[10px] font-medium ${
            data.trendDirection === 'up' ? 'text-emerald-400' :
            data.trendDirection === 'down' ? 'text-red-400' :
            'text-slate-400'
          }`}>
            {data.trend}
          </span>
        </div>

        {/* Progress Bar */}
        <div className="w-full h-1.5 bg-white/5 rounded-full overflow-hidden">
          <motion.div
            initial={{ width: 0 }}
            animate={{ width: `${data.progress}%` }}
            transition={{ duration: 0.8, ease: [0.16, 1, 0.3, 1] }}
            className={`h-full ${data.color.replace('text-', 'bg-')} rounded-full`}
          />
        </div>
      </div>
    </motion.div>
  );
}

export default function TrackingKPIs({ kpis }: TrackingKPIsProps) {
  const { user } = useAuth();
  if (user?.isPartnerCarrier) {
    return null;
  }

  const kpiData = getKPIData(kpis);

  const containerVariants = {
    hidden: { opacity: 0 },
    visible: {
      opacity: 1,
      transition: {
        staggerChildren: 0.08,
      },
    },
  };

  const itemVariants = {
    hidden: { opacity: 0, y: 20 },
    visible: {
      opacity: 1,
      y: 0,
      transition: { duration: 0.5, ease: [0.16, 1, 0.3, 1] as const },
    },
  };

  return (
    <motion.div
      variants={containerVariants}
      initial="hidden"
      animate="visible"
      className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4"
    >
      {kpiData.map((kpi) => (
        <motion.div key={kpi.label} variants={itemVariants}>
          <EnterpriseKPICard data={kpi} />
        </motion.div>
      ))}
    </motion.div>
  );
}
