import { motion } from 'framer-motion';
import { AlertTriangle, ShieldCheck } from 'lucide-react';
import type { CriticalIncident } from '../../../../features/dispatcher/notifications/services/notification-center.service';

interface CriticalIncidentStatusProps {
  incident: CriticalIncident;
}

export default function CriticalIncidentStatus({ incident }: CriticalIncidentStatusProps) {
  if (incident.exists) {
    return (
      <motion.div
        initial={{ opacity: 0, x: -10 }}
        animate={{ opacity: 1, x: 0 }}
        transition={{ duration: 0.2 }}
        className="flex items-center gap-3"
      >
        <div className="flex items-center gap-2 rounded-lg border border-red-500/30 bg-red-500/10 px-4 py-2">
          <AlertTriangle className="h-5 w-5 text-red-400" />
          <div className="flex flex-col">
            <span className="text-[10px] font-semibold uppercase tracking-wider text-red-400">
              Critical Operational Incident
            </span>
            <span className="text-xs font-semibold text-white">
              Priority: {incident.priority}
            </span>
          </div>
        </div>
      </motion.div>
    );
  }

  return (
    <motion.div
      initial={{ opacity: 0, x: -10 }}
      animate={{ opacity: 1, x: 0 }}
      transition={{ duration: 0.2 }}
      className="flex items-center gap-3"
    >
      <div className="flex items-center gap-2 rounded-lg border border-green-500/30 bg-green-500/10 px-4 py-2">
        <ShieldCheck className="h-5 w-5 text-green-400" />
        <div className="flex flex-col">
          <span className="text-[10px] font-semibold uppercase tracking-wider text-green-400">
            Fleet Operating Normally
          </span>
          <span className="text-xs font-semibold text-white">
            No critical incidents detected
          </span>
        </div>
      </div>
    </motion.div>
  );
}
