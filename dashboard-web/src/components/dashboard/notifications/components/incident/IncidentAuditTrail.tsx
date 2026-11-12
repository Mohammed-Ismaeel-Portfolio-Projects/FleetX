import { motion } from 'framer-motion';
import { History } from 'lucide-react';
import type { AuditEntry } from '../../../../../features/dispatcher/notifications/services/notification-center.service';

interface IncidentAuditTrailProps {
  auditTrail: AuditEntry[];
}

export default function IncidentAuditTrail({ auditTrail }: IncidentAuditTrailProps) {
  return (
    <motion.div
      initial={{ opacity: 0, y: 10 }}
      animate={{ opacity: 1, y: 0 }}
      transition={{ duration: 0.2 }}
      className="rounded-lg border border-white/5 bg-white/[0.02] p-4"
    >
      <div className="flex items-center gap-2 mb-4">
        <History className="h-4 w-4 text-slate-500" />
        <h3 className="text-[10px] font-semibold uppercase tracking-wider text-slate-500">
          Audit Trail
        </h3>
      </div>

      <div className="space-y-2">
        {auditTrail.map((entry) => (
          <div key={entry.id} className="flex items-start gap-3 p-2 rounded-lg border border-white/5 bg-white/[0.02]">
            <div className="flex-1">
              <div className="flex items-center gap-2 mb-1">
                <span className="text-xs font-semibold text-white">{entry.actor}</span>
                <span className="text-[10px] text-slate-500 font-mono">{entry.timestamp}</span>
              </div>
              <p className="text-xs text-slate-300">{entry.action}</p>
            </div>
          </div>
        ))}

        {auditTrail.length === 0 && (
          <div className="text-center py-4">
            <p className="text-xs text-slate-500">No audit trail</p>
          </div>
        )}
      </div>
    </motion.div>
  );
}
