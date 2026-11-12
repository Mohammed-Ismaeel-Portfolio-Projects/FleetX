import { motion } from 'framer-motion';
import { CheckCircle, Clock, AlertCircle, User } from 'lucide-react';
import type { TimelineEntry } from '../../../../../features/dispatcher/notifications/services/notification-center.service';

interface IncidentTimelineProps {
  timeline: TimelineEntry[];
}

export default function IncidentTimeline({ timeline }: IncidentTimelineProps) {
  const getStatusIcon = (status: string) => {
    const icons = {
      Created: Clock,
      Detected: AlertCircle,
      Acknowledged: CheckCircle,
      Assigned: User,
      'In Progress': Clock,
      Resolved: CheckCircle,
      Escalated: AlertCircle,
    };
    return icons[status as keyof typeof icons] || Clock;
  };

  const getStatusColor = (status: string) => {
    const colors = {
      Created: 'text-slate-400',
      Detected: 'text-blue-400',
      Acknowledged: 'text-cyan-400',
      Assigned: 'text-purple-400',
      'In Progress': 'text-orange-400',
      Resolved: 'text-green-400',
      Escalated: 'text-red-400',
    };
    return colors[status as keyof typeof colors] || colors.Created;
  };

  return (
    <motion.div
      initial={{ opacity: 0, y: 10 }}
      animate={{ opacity: 1, y: 0 }}
      transition={{ duration: 0.2 }}
      className="rounded-lg border border-white/5 bg-white/[0.02] p-4"
    >
      <h3 className="text-[10px] font-semibold uppercase tracking-wider text-slate-500 mb-4">
        Incident Timeline
      </h3>

      <div className="space-y-4">
        {timeline.map((entry, index) => {
          const Icon = getStatusIcon(entry.status);
          const statusColor = getStatusColor(entry.status);
          const isLast = index === timeline.length - 1;

          return (
            <div key={entry.id} className="flex gap-3">
              <div className="flex flex-col items-center">
                <div className={`w-8 h-8 rounded-full border border-white/10 bg-white/5 flex items-center justify-center ${statusColor}`}>
                  <Icon className="h-4 w-4" />
                </div>
                {!isLast && <div className="w-px h-full bg-white/10 mt-2" />}
              </div>

              <div className="flex-1 pb-4">
                <div className="flex items-center gap-2 mb-1">
                  <span className="text-xs font-semibold text-white">{entry.status}</span>
                  <span className="text-[10px] text-slate-500 font-mono">{entry.timestamp}</span>
                </div>
                {entry.actor && (
                  <p className="text-[10px] text-slate-400 mb-1">by {entry.actor}</p>
                )}
                <p className="text-xs text-slate-300">{entry.description}</p>
              </div>
            </div>
          );
        })}
      </div>
    </motion.div>
  );
}
