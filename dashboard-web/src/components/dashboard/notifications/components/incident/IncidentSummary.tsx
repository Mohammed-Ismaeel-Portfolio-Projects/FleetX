import { motion } from 'framer-motion';
import type { IncidentDetails } from '../../../../../features/dispatcher/notifications/services/notification-center.service';

interface IncidentSummaryProps {
  incident: IncidentDetails;
}

export default function IncidentSummary({ incident }: IncidentSummaryProps) {
  return (
    <motion.div
      initial={{ opacity: 0, y: 10 }}
      animate={{ opacity: 1, y: 0 }}
      transition={{ duration: 0.2 }}
      className="rounded-lg border border-white/5 bg-white/[0.02] p-4 space-y-4"
    >
      <div>
        <h3 className="text-[10px] font-semibold uppercase tracking-wider text-slate-500 mb-2">
          Description
        </h3>
        <p className="text-sm text-slate-300 leading-relaxed">
          {incident.description}
        </p>
      </div>

      <div className="grid grid-cols-1 gap-4">
        <div>
          <h3 className="text-[10px] font-semibold uppercase tracking-wider text-slate-500 mb-2">
            Root Cause
          </h3>
          <p className="text-sm text-slate-300 leading-relaxed">
            {incident.rootCause}
          </p>
        </div>

        <div>
          <h3 className="text-[10px] font-semibold uppercase tracking-wider text-slate-500 mb-2">
            Affected System
          </h3>
          <p className="text-sm text-slate-300 leading-relaxed">
            {incident.affectedSystem}
          </p>
        </div>

        <div>
          <h3 className="text-[10px] font-semibold uppercase tracking-wider text-slate-500 mb-2">
            Current Situation
          </h3>
          <p className="text-sm text-slate-300 leading-relaxed">
            {incident.currentSituation}
          </p>
        </div>
      </div>
    </motion.div>
  );
}
