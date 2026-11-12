import { MapPin, Gauge, Clock, Activity } from 'lucide-react';
import type { VehicleDetails } from '../../types/tracking.types';
import AnimatedNumber from '../AnimatedNumber';

interface QuickMetricsProps {
  details: VehicleDetails;
}

export default function QuickMetrics({ details }: QuickMetricsProps) {
  const metrics = details.quickMetrics;

  if (!metrics) {
    return null;
  }

  const metricItems = [
    { key: 'todayDistance', label: 'Today\'s Distance', value: metrics.todayDistance, unit: 'km', icon: MapPin },
    { key: 'averageSpeed', label: 'Average Speed', value: metrics.averageSpeed, unit: 'km/h', icon: Gauge },
    { key: 'maxSpeed', label: 'Max Speed', value: metrics.maxSpeed, unit: 'km/h', icon: Gauge },
    { key: 'idleTime', label: 'Idle Time', value: metrics.idleTime, unit: '', icon: Clock },
    { key: 'drivingTime', label: 'Driving Time', value: metrics.drivingTime, unit: '', icon: Activity },
  ];

  return (
    <div className="space-y-3">
      <h3 className="text-xs font-semibold text-white uppercase tracking-wider">Quick Metrics</h3>
      
      <div className="grid grid-cols-3 gap-2">
        {metricItems.map((item) => {
          const Icon = item.icon;
          const isNumeric = typeof item.value === 'number';
          return (
            <div
              key={item.key}
              className="rounded-lg border border-white/5 bg-[#0A0E17] p-2 transition-all duration-300 hover:border-white/10"
            >
              <div className="flex items-center gap-2">
                <Icon className="h-3 w-3 text-cyan-400" />
                <p className="text-[9px] font-bold uppercase tracking-wider text-slate-500">{item.label}</p>
              </div>
              <p className="mt-1 font-mono text-sm font-semibold text-white">
                {isNumeric && typeof item.value === 'number' ? (
                  <AnimatedNumber value={item.value} />
                ) : (
                  item.value
                )}
                {item.unit && <span className="ml-1 text-xs text-slate-400">{item.unit}</span>}
              </p>
            </div>
          );
        })}
      </div>
    </div>
  );
}
