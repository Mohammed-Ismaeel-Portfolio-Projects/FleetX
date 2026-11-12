import { CheckCircle, Clock, Wrench, MapPin, Fuel, Package, Truck, Play } from 'lucide-react';
import type { VehicleTimelineEvent } from '../types/fleet-operations.types';

interface VehicleTimelineProps {
  events: VehicleTimelineEvent[];
}

export default function VehicleTimeline({ events }: VehicleTimelineProps) {
  const getEventIcon = (type: string) => {
    switch (type) {
      case 'vehicle_assigned':
        return <Truck className="w-4 h-4" />;
      case 'route_started':
        return <Play className="w-4 h-4" />;
      case 'checkpoint':
        return <MapPin className="w-4 h-4" />;
      case 'maintenance':
        return <Wrench className="w-4 h-4" />;
      case 'inspection':
        return <CheckCircle className="w-4 h-4" />;
      case 'fuel_refill':
        return <Fuel className="w-4 h-4" />;
      case 'delivery_completed':
        return <Package className="w-4 h-4" />;
      case 'vehicle_returned':
        return <CheckCircle className="w-4 h-4" />;
      default:
        return <Clock className="w-4 h-4" />;
    }
  };

  const getEventColor = (status: string) => {
    switch (status) {
      case 'success':
        return 'bg-emerald-500/10 border-emerald-500/20 text-emerald-400';
      case 'warning':
        return 'bg-amber-500/10 border-amber-500/20 text-amber-400';
      case 'error':
        return 'bg-red-500/10 border-red-500/20 text-red-400';
      case 'info':
        return 'bg-blue-500/10 border-blue-500/20 text-blue-400';
      default:
        return 'bg-slate-500/10 border-slate-500/20 text-slate-400';
    }
  };

  const getEventIconBg = (status: string) => {
    switch (status) {
      case 'success':
        return 'bg-emerald-500';
      case 'warning':
        return 'bg-amber-500';
      case 'error':
        return 'bg-red-500';
      case 'info':
        return 'bg-blue-500';
      default:
        return 'bg-slate-500';
    }
  };

  if (events.length === 0) {
    return (
      <div className="bg-[#0E1320] border border-white/5 rounded-2xl shadow-lg p-8 flex flex-col items-center justify-center h-full">
        <Clock className="w-16 h-16 text-slate-600 mb-4" />
        <p className="text-sm text-slate-400">Timeline data unavailable</p>
        <p className="text-xs text-slate-500 mt-2 text-center">No vehicle activity timeline API is connected</p>
      </div>
    );
  }

  return (
    <div className="bg-[#0E1320] border border-white/5 rounded-2xl shadow-lg flex flex-col h-full">
      {/* Header */}
      <div className="p-5 border-b border-white/5">
        <h2 className="text-sm font-bold text-white font-sans flex items-center gap-2">
          <Clock className="w-4 h-4 text-[#00E5FF]" />
          Vehicle Timeline
        </h2>
      </div>

      {/* Content */}
      <div className="flex-1 overflow-y-auto p-5">
        <div className="relative">
          {/* Timeline Line */}
          <div className="absolute left-4 top-0 bottom-0 w-0.5 bg-white/10" />

          {/* Events */}
          <div className="space-y-4">
            {events.map((event) => (
              <div key={event.id} className="relative pl-10">
                {/* Timeline Dot */}
                <div className={`absolute left-2.5 w-3 h-3 rounded-full ${getEventIconBg(event.status)} border-2 border-[#0E1320]`} />

                {/* Event Card */}
                <div className={`p-4 rounded-xl border ${getEventColor(event.status)}`}>
                  <div className="flex items-start justify-between mb-2">
                    <div className="flex items-center gap-3">
                      <div className={`w-8 h-8 rounded-lg ${getEventIconBg(event.status)} flex items-center justify-center`}>
                        {getEventIcon(event.type)}
                      </div>
                      <div>
                        <p className="text-sm font-bold text-white">{event.message}</p>
                        <p className="text-xs text-slate-400">{event.time}</p>
                      </div>
                    </div>
                  </div>
                  {event.details && (
                    <p className="text-xs text-slate-400 mt-2">{event.details}</p>
                  )}
                </div>
              </div>
            ))}
          </div>
        </div>
      </div>

      {/* Footer — activity log action omitted until timeline API exists */}
    </div>
  );
}
