import { useAuth } from '../../../../core/auth';
import { Users, UserCheck, Briefcase, WifiOff, CalendarOff, Ban } from 'lucide-react';
import type { DriverKPIs } from '../types/driver-operations.types';

interface DriverKPIsProps {
  kpis: DriverKPIs | null;
  error?: string | null;
}

function Card({
  title,
  value,
  detail,
  icon: Icon,
  color,
  bg,
}: {
  title: string;
  value: string;
  detail: string;
  icon: typeof Users;
  color: string;
  bg: string;
}) {
  return (
    <div className="bg-[#0E1320] border border-white/5 rounded-2xl p-5 shadow-lg">
      <div className={`w-12 h-12 rounded-xl ${bg} flex items-center justify-center mb-4 border`}>
        <Icon className={`w-6 h-6 ${color}`} />
      </div>
      <p className="text-3xl font-extrabold text-white tracking-tight font-sans mb-2">{value}</p>
      <p className="text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-2">{title}</p>
      <p className="text-[10px] font-medium text-slate-400">{detail}</p>
    </div>
  );
}

export default function DriverKPIs({ kpis, error }: DriverKPIsProps) {
  const { user } = useAuth();
  if (user?.isPartnerCarrier) {
    return null;
  }
  if (error && !kpis) {
    return (
      <div className="bg-red-500/10 border border-red-500/20 rounded-2xl p-4 text-red-400 text-sm">
        {error}
      </div>
    );
  }

  if (!kpis) {
    return (
      <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
        {[...Array(6)].map((_, i) => (
          <div key={i} className="bg-[#0E1320] border border-white/5 rounded-2xl p-5 animate-pulse h-36" />
        ))}
      </div>
    );
  }

  const cards = [
    {
      title: 'Total Drivers',
      value: String(kpis.total_drivers),
      detail: 'Active employment roster',
      icon: Users,
      color: 'text-blue-400',
      bg: 'bg-blue-500/10 border-blue-500/20',
    },
    {
      title: 'Drivers Available',
      value: String(kpis.available),
      detail: `${kpis.available_percentage}% of total`,
      icon: UserCheck,
      color: 'text-emerald-400',
      bg: 'bg-emerald-500/10 border-emerald-500/20',
    },
    {
      title: 'Drivers Busy',
      value: String(kpis.busy),
      detail: `${kpis.busy_percentage}% of total`,
      icon: Briefcase,
      color: 'text-amber-400',
      bg: 'bg-amber-500/10 border-amber-500/20',
    },
    {
      title: 'Drivers Offline',
      value: String(kpis.offline),
      detail: `${kpis.offline_percentage}% of total`,
      icon: WifiOff,
      color: 'text-slate-400',
      bg: 'bg-slate-500/10 border-slate-500/20',
    },
    {
      title: 'Drivers On Leave',
      value: String(kpis.on_leave),
      detail: `${kpis.on_leave_percentage}% of total`,
      icon: CalendarOff,
      color: 'text-cyan-400',
      bg: 'bg-cyan-500/10 border-cyan-500/20',
    },
    {
      title: 'Drivers Suspended',
      value: String(kpis.suspended),
      detail: `${kpis.suspended_percentage}% of total`,
      icon: Ban,
      color: 'text-red-400',
      bg: 'bg-red-500/10 border-red-500/20',
    },
  ];

  return (
    <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
      {cards.map((card) => (
        <Card key={card.title} {...card} />
      ))}
    </div>
  );
}
