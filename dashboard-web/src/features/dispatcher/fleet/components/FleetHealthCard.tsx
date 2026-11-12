import { Activity, Wifi, Navigation, Gauge } from 'lucide-react';
import type { FleetHealth } from '../types/fleet-operations.types';

interface FleetHealthCardProps {
  health: FleetHealth | null;
}

export default function FleetHealthCard({ health }: FleetHealthCardProps) {
  if (!health) {
    return (
      <div className="bg-[#0E1320] border border-white/5 rounded-2xl shadow-lg p-8 flex flex-col items-center justify-center h-full">
        <Activity className="w-16 h-16 text-slate-600 mb-4" />
        <p className="text-sm text-slate-400">Select a vehicle to view operational state</p>
      </div>
    );
  }

  const metrics = [
    {
      label: 'Availability',
      value: health.availability,
      icon: Activity,
      color: health.availability === 'Available' ? 'text-emerald-400' : 'text-slate-300',
      bg: health.availability === 'Available' ? 'bg-emerald-500/10' : 'bg-slate-500/10',
    },
    {
      label: 'Active Assignment',
      value: health.hasActiveAssignment ? 'Yes' : 'No',
      icon: Activity,
      color: 'text-cyan-400',
      bg: 'bg-cyan-500/10',
    },
    health.onlineStatus
      ? {
          label: 'Online Status',
          value: health.onlineStatus,
          icon: Wifi,
          color: 'text-emerald-400',
          bg: 'bg-emerald-500/10',
        }
      : {
          label: 'Online Status',
          value: 'Unavailable',
          icon: Wifi,
          color: 'text-slate-400',
          bg: 'bg-slate-500/10',
        },
    health.signalQuality
      ? {
          label: 'Signal Quality',
          value: health.signalQuality,
          icon: Wifi,
          color: 'text-blue-400',
          bg: 'bg-blue-500/10',
        }
      : {
          label: 'Signal Quality',
          value: 'Unavailable',
          icon: Wifi,
          color: 'text-slate-400',
          bg: 'bg-slate-500/10',
        },
    health.speed !== undefined
      ? {
          label: 'Speed',
          value: String(health.speed),
          icon: Gauge,
          color: 'text-amber-400',
          bg: 'bg-amber-500/10',
        }
      : {
          label: 'Speed',
          value: 'Unavailable',
          icon: Gauge,
          color: 'text-slate-400',
          bg: 'bg-slate-500/10',
        },
    health.movementStatus
      ? {
          label: 'Movement',
          value: health.movementStatus,
          icon: Navigation,
          color: 'text-purple-400',
          bg: 'bg-purple-500/10',
        }
      : {
          label: 'Movement',
          value: 'Unavailable',
          icon: Navigation,
          color: 'text-slate-400',
          bg: 'bg-slate-500/10',
        },
    health.odometer !== undefined
      ? {
          label: 'Odometer',
          value: health.odometer.toLocaleString(),
          icon: Activity,
          color: 'text-slate-300',
          bg: 'bg-slate-500/10',
        }
      : {
          label: 'Odometer',
          value: 'Unavailable',
          icon: Activity,
          color: 'text-slate-400',
          bg: 'bg-slate-500/10',
        },
  ];

  return (
    <div className="bg-[#0E1320] border border-white/5 rounded-2xl shadow-lg flex flex-col h-full">
      <div className="p-5 border-b border-white/5">
        <h2 className="text-sm font-bold text-white font-sans flex items-center gap-2">
          <Activity className="w-4 h-4 text-[#00E5FF]" />
          Operational State
        </h2>
      </div>

      <div className="flex-1 overflow-y-auto p-5 space-y-5">
        <div className="p-4 rounded-xl border bg-white/[0.02] border-white/5">
          <p className="text-xs text-slate-500 uppercase tracking-wider">Vehicle Status</p>
          <p className="text-2xl font-bold text-white mt-1">{health.status || 'Unavailable'}</p>
        </div>

        <div className="grid grid-cols-2 gap-3">
          {metrics.map((metric) => (
            <div key={metric.label} className="bg-white/[0.02] border border-white/5 rounded-xl p-4">
              <div className={`w-10 h-10 rounded-lg ${metric.bg} flex items-center justify-center mb-3`}>
                <metric.icon className={`w-5 h-5 ${metric.color}`} />
              </div>
              <p className="text-lg font-bold text-white">{metric.value}</p>
              <p className="text-[10px] text-slate-500 uppercase tracking-wider">{metric.label}</p>
            </div>
          ))}
        </div>
      </div>
    </div>
  );
}
