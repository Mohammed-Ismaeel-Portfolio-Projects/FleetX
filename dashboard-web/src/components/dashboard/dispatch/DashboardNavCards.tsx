import { Link } from 'react-router-dom';
import { Truck, Users, Package, Navigation, Bell, BarChart2 } from 'lucide-react';

export interface WorkspaceMetrics {
  vehiclesOnAssignment: number;
  driversAvailable: number;
  activeShipments: number;
  criticalIssues: number;
}

interface NavCard {
  label: string;
  href: string;
  icon: typeof Truck;
  metric: (metrics: WorkspaceMetrics | null) => string;
}

/** Ordered by typical dispatcher workflow priority. */
const navCards: NavCard[] = [
  {
    label: 'Shipments',
    href: '/dispatcher/shipments',
    icon: Package,
    metric: (m) => (m ? `${m.activeShipments} active` : '—'),
  },
  {
    label: 'Tracking',
    href: '/dispatcher/tracking',
    icon: Navigation,
    metric: (m) => (m ? `${m.activeShipments} active shipments` : '—'),
  },
  {
    label: 'Fleet',
    href: '/dispatcher/fleet',
    icon: Truck,
    metric: (m) => (m ? `${m.vehiclesOnAssignment} vehicles on assignment` : '—'),
  },
  {
    label: 'Drivers',
    href: '/dispatcher/drivers',
    icon: Users,
    metric: (m) => (m ? `${m.driversAvailable} available` : '—'),
  },
  {
    label: 'Alerts',
    href: '/dispatcher/notifications',
    icon: Bell,
    metric: (m) => (m ? `${m.criticalIssues} critical` : '—'),
  },
  {
    label: 'Analytics',
    href: '/dispatcher/analytics',
    icon: BarChart2,
    metric: () => 'Operational insights',
  },
];

interface DashboardNavCardsProps {
  metrics: WorkspaceMetrics | null;
}

export default function DashboardNavCards({ metrics }: DashboardNavCardsProps) {
  return (
    <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-3 xl:grid-cols-6 gap-2 sm:gap-3">
      {navCards.map((card) => {
        const Icon = card.icon;
        return (
          <Link
            key={card.href}
            to={card.href}
            className="flex flex-col gap-1.5 px-3 py-3 rounded-xl border border-white/5 bg-[#0E1320] hover:border-white/15 hover:bg-white/[0.03] transition-colors min-h-[68px]"
          >
            <div className="flex items-center gap-2">
              <Icon className="w-4 h-4 text-slate-400 flex-shrink-0" />
              <span className="text-[11px] font-semibold text-slate-200 truncate">
                {card.label}
              </span>
            </div>
            <span className="text-[10px] text-slate-500 leading-snug pl-6">
              {card.metric(metrics)}
            </span>
          </Link>
        );
      })}
    </div>
  );
}
