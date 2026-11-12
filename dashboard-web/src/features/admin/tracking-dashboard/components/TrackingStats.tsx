import { Truck, Clock, AlertTriangle, Gauge, MapPin, Activity } from 'lucide-react';
import type { TrackingStats } from '../types/tracking-dashboard.types';
import AnimatedNumber from './AnimatedNumber';

interface TrackingStatsProps {
  stats: TrackingStats;
}

interface StatCard {
  label: string;
  value: string | number;
  secondaryValue?: string;
  icon: any;
  color: string;
  bgColor: string;
  borderColor: string;
}

export default function TrackingStats({ stats }: TrackingStatsProps) {
  const cards: StatCard[] = [
    {
      label: 'Moving Vehicles',
      value: stats.movingVehicles,
      secondaryValue: stats.movingVehiclesChange !== undefined
        ? (stats.movingVehiclesChange > 0 ? `+${stats.movingVehiclesChange}` : `${stats.movingVehiclesChange}`)
        : `${stats.movingVehiclesPercentage}% of fleet`,
      icon: Truck,
      color: 'text-green-400',
      bgColor: 'bg-green-500/10',
      borderColor: 'border-green-500/20',
    },
    {
      label: 'Idle Vehicles',
      value: stats.idleVehicles,
      secondaryValue: stats.idleVehiclesLongestIdle ? `Longest idle: ${stats.idleVehiclesLongestIdle}` : undefined,
      icon: Clock,
      color: 'text-yellow-400',
      bgColor: 'bg-yellow-500/10',
      borderColor: 'border-yellow-500/20',
    },
    {
      label: 'Offline Vehicles',
      value: stats.offlineVehicles,
      secondaryValue: stats.offlineVehiclesLastDisconnect ? `Last disconnect: ${stats.offlineVehiclesLastDisconnect}` : undefined,
      icon: MapPin,
      color: 'text-red-400',
      bgColor: 'bg-red-500/10',
      borderColor: 'border-red-500/20',
    },
    {
      label: 'Active Assignments',
      value: stats.activeAssignments,
      secondaryValue: stats.activeAssignmentsUtilization !== undefined ? `Fleet utilization: ${stats.activeAssignmentsUtilization}%` : undefined,
      icon: Activity,
      color: 'text-cyan-400',
      bgColor: 'bg-cyan-500/10',
      borderColor: 'border-cyan-500/20',
    },
    {
      label: 'Open Alerts',
      value: stats.openAlerts,
      secondaryValue: stats.openAlertsCritical !== undefined && stats.openAlertsWarning !== undefined
        ? `Critical: ${stats.openAlertsCritical} | Warning: ${stats.openAlertsWarning}`
        : undefined,
      icon: AlertTriangle,
      color: 'text-orange-400',
      bgColor: 'bg-orange-500/10',
      borderColor: 'border-orange-500/20',
    },
    {
      label: 'Average Speed',
      value: `${stats.averageSpeed} km/h`,
      secondaryValue: stats.averageSpeedPeak !== undefined ? `Peak today: ${stats.averageSpeedPeak} km/h` : undefined,
      icon: Gauge,
      color: 'text-purple-400',
      bgColor: 'bg-purple-500/10',
      borderColor: 'border-purple-500/20',
    },
  ];

  return (
    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
      {cards.map((card) => (
        <div
          key={card.label}
          className={`rounded-xl border ${card.borderColor} ${card.bgColor} bg-[#0E1320] p-4 shadow-sm transition-all duration-200 hover:border-white/10 hover:shadow-md`}
        >
          <div className="flex items-start gap-3">
            <div className={`rounded-lg ${card.bgColor} p-2`}>
              <card.icon className={`h-4 w-4 ${card.color}`} />
            </div>
            <div className="flex-1 min-w-0">
              <p className="text-[9px] font-semibold uppercase tracking-wider text-slate-500">
                {card.label}
              </p>
              <p className={`mt-1 font-mono text-lg font-bold ${card.color} transition-all duration-200`}>
                {typeof card.value === 'number' ? (
                  <AnimatedNumber value={card.value} />
                ) : (
                  card.value
                )}
              </p>
              {card.secondaryValue && (
                <p className="mt-1 text-[9px] text-slate-400 transition-opacity duration-200 truncate">
                  {card.secondaryValue}
                </p>
              )}
            </div>
          </div>
        </div>
      ))}
    </div>
  );
}
