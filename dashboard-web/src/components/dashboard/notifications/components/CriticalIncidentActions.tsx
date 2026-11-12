import { motion } from 'framer-motion';
import type { CriticalIncident } from '../../../../features/dispatcher/notifications/services/notification-center.service';

interface CriticalIncidentActionsProps {
  incident: CriticalIncident;
}

export default function CriticalIncidentActions({ incident }: CriticalIncidentActionsProps) {
  if (!incident.exists) {
    return null;
  }

  return (
    <motion.div
      initial={{ opacity: 0, y: 10 }}
      animate={{ opacity: 1, y: 0 }}
      transition={{ duration: 0.2 }}
      className="flex flex-wrap items-center gap-2"
    >
      <button className="flex items-center gap-2 rounded-lg border border-red-500/30 bg-red-500/10 px-4 py-2 text-xs font-semibold text-red-400 hover:bg-red-500/20 transition-all hover:scale-[1.01]">
        <span>Open Incident</span>
      </button>
      <button className="flex items-center gap-2 rounded-lg border border-white/10 bg-white/5 px-4 py-2 text-xs font-medium text-slate-300 hover:bg-white/10 transition-all hover:scale-[1.01]">
        <span>Assign Operator</span>
      </button>
      <button className="flex items-center gap-2 rounded-lg border border-orange-500/30 bg-orange-500/10 px-4 py-2 text-xs font-medium text-orange-400 hover:bg-orange-500/20 transition-all hover:scale-[1.01]">
        <span>Escalate</span>
      </button>
      <button className="flex items-center gap-2 rounded-lg border border-white/10 bg-white/5 px-4 py-2 text-xs font-medium text-slate-300 hover:bg-white/10 transition-all hover:scale-[1.01]">
        <span>Notify Driver</span>
      </button>
      <button className="flex items-center gap-2 rounded-lg border border-white/10 bg-white/5 px-4 py-2 text-xs font-medium text-slate-300 hover:bg-white/10 transition-all hover:scale-[1.01]">
        <span>Notify Customer</span>
      </button>
      <button className="flex items-center gap-2 rounded-lg border border-cyan-500/30 bg-cyan-500/10 px-4 py-2 text-xs font-medium text-cyan-400 hover:bg-cyan-500/20 transition-all hover:scale-[1.01]">
        <span>Create Maintenance Ticket</span>
      </button>
    </motion.div>
  );
}
