import { MapPin, Gauge, AlertTriangle, CheckCircle } from 'lucide-react';
import type { TrackingEvent } from '../types/tracking-dashboard.types';

interface TrackingTimelineProps {
  events: TrackingEvent[];
}

function getEventIcon(type: TrackingEvent['type']) {
  switch (type) {
    case 'location':
      return MapPin;
    case 'speed':
      return Gauge;
    case 'status':
      return CheckCircle;
    case 'alert':
      return AlertTriangle;
    default:
      return MapPin;
  }
}

function getSeverityColor(severity: TrackingEvent['severity']): string {
  switch (severity) {
    case 'info':
      return 'text-cyan-400 bg-cyan-500/10 border-cyan-500/20';
    case 'warning':
      return 'text-yellow-400 bg-yellow-500/10 border-yellow-500/20';
    case 'error':
      return 'text-red-400 bg-red-500/10 border-red-500/20';
    default:
      return 'text-slate-400 bg-slate-500/10 border-slate-500/20';
  }
}

export default function TrackingTimeline({ events }: TrackingTimelineProps) {
  return (
    <div className="rounded-xl border border-white/5 bg-[#0E1320] p-5 shadow-sm">
      <h2 className="mb-3 text-sm font-semibold text-white">Activity Timeline</h2>
      
      {events.length === 0 ? (
        <div className="flex h-28 items-center justify-center text-center">
          <div>
            <p className="text-sm font-semibold text-white">No recent activity</p>
            <p className="mt-1 text-xs text-slate-500">
              Tracking events will appear here.
            </p>
          </div>
        </div>
      ) : (
        <div className="space-y-3">
          {events.map((event, index) => {
            const Icon = getEventIcon(event.type);
            return (
              <div key={event.id} className="relative pl-5">
                {index !== events.length - 1 && (
                  <div className="absolute left-[9px] top-7 h-full w-0.5 bg-white/5" />
                )}
                <div className="absolute left-0 top-0 flex h-5 w-5 items-center justify-center rounded-full border border-white/10 bg-[#0A0E17]">
                  <Icon className="h-2.5 w-2.5 text-cyan-400" />
                </div>
                <div className="rounded-lg border border-white/5 bg-[#0A0E17] p-3">
                  <div className="flex items-start justify-between gap-3">
                    <div className="flex-1 min-w-0">
                      <div className="flex items-center gap-2">
                        <span className={`inline-flex rounded-full border px-2 py-0.5 text-[9px] font-medium uppercase capitalize ${getSeverityColor(event.severity)}`}>
                          {event.type}
                        </span>
                        <span className="text-xs text-slate-400 truncate">{event.vehicleName}</span>
                      </div>
                      <p className="mt-1.5 text-xs text-white leading-relaxed">{event.message}</p>
                    </div>
                    <span className="text-[10px] text-slate-500 whitespace-nowrap font-mono">
                      {new Date(event.timestamp).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
                    </span>
                  </div>
                </div>
              </div>
            );
          })}
        </div>
      )}
    </div>
  );
}
