import { motion } from 'framer-motion';
import { Clock, DollarSign, AlertTriangle, Building2 } from 'lucide-react';
import type { IncidentDetails } from '../../../../../features/dispatcher/notifications/services/notification-center.service';

interface IncidentBusinessImpactProps {
  incident: IncidentDetails;
}

export default function IncidentBusinessImpact({ incident }: IncidentBusinessImpactProps) {
  const getRiskColor = (risk: string) => {
    const colors = {
      Low: 'text-green-400 bg-green-500/10 border-green-500/20',
      Medium: 'text-yellow-400 bg-yellow-500/10 border-yellow-500/20',
      High: 'text-orange-400 bg-orange-500/10 border-orange-500/20',
      Critical: 'text-red-400 bg-red-500/10 border-red-500/20',
    };
    return colors[risk as keyof typeof colors] || colors.Low;
  };

  const riskClass = getRiskColor(incident.riskLevel);

  return (
    <motion.div
      initial={{ opacity: 0, y: 10 }}
      animate={{ opacity: 1, y: 0 }}
      transition={{ duration: 0.2 }}
      className="rounded-lg border border-white/5 bg-white/[0.02] p-4"
    >
      <h3 className="text-[10px] font-semibold uppercase tracking-wider text-slate-500 mb-4">
        Business Impact
      </h3>

      <div className="grid grid-cols- md:grid-cols-2 gap-4 mb-4">
        <div className="flex items-center gap-3">
          <Clock className="h-4 w-4 text-slate-500" />
          <div className="flex-1">
            <span className="text-[10px] text-slate-500 uppercase tracking-wider">Estimated Delay</span>
            <p className="text-sm font-mono font-semibold text-orange-400">{incident.estimatedDelay}</p>
          </div>
        </div>

        <div className="flex items-center gap-3">
          <DollarSign className="h-4 w-4 text-slate-500" />
          <div className="flex-1">
            <span className="text-[10px] text-slate-500 uppercase tracking-wider">Financial Impact</span>
            <p className="text-sm font-mono font-semibold text-orange-400">{incident.financialImpact}</p>
          </div>
        </div>

        <div className="flex items-center gap-3">
          <AlertTriangle className="h-4 w-4 text-slate-500" />
          <div className="flex-1">
            <span className="text-[10px] text-slate-500 uppercase tracking-wider">Affected Deliveries</span>
            <p className="text-sm font-mono font-semibold text-white">{incident.affectedDeliveries}</p>
          </div>
        </div>

        <div className="flex items-center gap-3">
          <Building2 className="h-4 w-4 text-slate-500" />
          <div className="flex-1">
            <span className="text-[10px] text-slate-500 uppercase tracking-wider">Risk Level</span>
            <div className={`px-2 py-1 rounded-md border text-[10px] font-semibold uppercase tracking-wider ${riskClass}`}>
              {incident.riskLevel}
            </div>
          </div>
        </div>
      </div>

      <div className="pt-3 border-t border-white/5">
        <span className="text-[10px] text-slate-500 uppercase tracking-wider">Customer Impact</span>
        <p className="text-sm text-slate-300 mt-1">{incident.customerImpact}</p>
      </div>
    </motion.div>
  );
}
