import { motion } from 'framer-motion';
import { X } from 'lucide-react';
import type { IncidentDetails } from '../../../../../features/dispatcher/notifications/services/notification-center.service';

interface IncidentHeaderProps {
  incident: IncidentDetails;
  onClose: () => void;
}

export default function IncidentHeader({ incident, onClose }: IncidentHeaderProps) {
  const getSeverityColor = (severity: string) => {
    const colors = {
      Critical: 'text-red-400 bg-red-500/10 border-red-500/20',
      High: 'text-orange-400 bg-orange-500/10 border-orange-500/20',
      Medium: 'text-yellow-400 bg-yellow-500/10 border-yellow-500/20',
      Low: 'text-green-400 bg-green-500/10 border-green-500/20',
    };
    return colors[severity as keyof typeof colors] || colors.Low;
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

  const severityClass = getSeverityColor(incident.severity);
  const statusClass = getStatusColor(incident.status);

  return (
    <motion.div
      initial={{ opacity: 0, y: -10 }}
      animate={{ opacity: 1, y: 0 }}
      transition={{ duration: 0.2 }}
      className="flex items-start justify-between gap-4 pb-4 border-b border-white/10"
    >
      <div className="flex-1">
        <div className="flex items-center gap-3 mb-3">
          <span className="text-xs font-mono text-slate-500">
            {incident.id}
          </span>
          <div className={`px-2 py-1 rounded-md border text-[10px] font-semibold uppercase tracking-wider ${severityClass}`}>
            {incident.severity}
          </div>
          <div className={`px-2 py-1 rounded-md border text-[10px] font-semibold uppercase tracking-wider bg-white/5 border-white/10 text-slate-400`}>
            {incident.priority}
          </div>
          <div className={`px-2 py-1 rounded-md border text-[10px] font-semibold uppercase tracking-wider bg-white/5 border-white/10 ${statusClass}`}>
            {incident.status}
          </div>
        </div>

        <div className="flex flex-wrap items-center gap-4 text-xs">
          <div className="flex items-center gap-2">
            <span className="text-slate-500">Created</span>
            <span className="text-slate-300 font-mono">{incident.createdTime}</span>
          </div>
          <div className="h-3 w-px bg-white/10" />
          <div className="flex items-center gap-2">
            <span className="text-slate-500">Assigned to</span>
            <span className="text-white font-semibold">{incident.assignedOperator}</span>
          </div>
        </div>
      </div>

      <button
        onClick={onClose}
        className="flex items-center justify-center w-8 h-8 rounded-lg border border-white/10 bg-white/5 text-slate-400 hover:bg-white/10 hover:text-white transition-all"
      >
        <X className="h-4 w-4" />
      </button>
    </motion.div>
  );
}
