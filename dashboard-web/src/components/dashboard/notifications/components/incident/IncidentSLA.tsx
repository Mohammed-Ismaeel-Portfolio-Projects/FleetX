import { motion } from 'framer-motion';
import { Clock } from 'lucide-react';
import type { IncidentSLA } from '../../../../../features/dispatcher/notifications/services/notification-center.service';

interface IncidentSLAProps {
  sla: IncidentSLA;
}

export default function IncidentSLA({ sla }: IncidentSLAProps) {
  const getStatusColor = (status: string) => {
    if (status === 'On Track') return 'text-green-400 bg-green-500/10 border-green-500/20';
    if (status === 'Warning') return 'text-orange-400 bg-orange-500/10 border-orange-500/20';
    return 'text-red-400 bg-red-500/10 border-red-500/20';
  };

  const getProgressColor = (status: string) => {
    if (status === 'On Track') return 'bg-green-500';
    if (status === 'Warning') return 'bg-orange-500';
    return 'bg-red-500';
  };

  const calculateProgress = () => {
    if (sla.responseStatus === 'On Track') return 33;
    if (sla.responseStatus === 'Warning') return 75;
    return 95;
  };

  return (
    <motion.div
      initial={{ opacity: 0, y: 10 }}
      animate={{ opacity: 1, y: 0 }}
      transition={{ duration: 0.2 }}
      className="rounded-lg border border-white/5 bg-white/[0.02] p-4"
    >
      <h3 className="text-[10px] font-semibold uppercase tracking-wider text-slate-500 mb-4">
        Service Level Agreement
      </h3>

      <div className="space-y-4">
        {/* Response SLA */}
        <div>
          <div className="flex items-center justify-between mb-2">
            <div className="flex items-center gap-2">
              <Clock className="h-4 w-4 text-slate-500" />
              <span className="text-xs font-semibold text-white">Response SLA</span>
            </div>
            <div className={`flex items-center gap-1 px-2 py-1 rounded-md border text-[10px] font-semibold uppercase tracking-wider ${getStatusColor(sla.responseStatus)}`}>
              {sla.responseStatus}
            </div>
          </div>

          <div className="grid grid-cols-3 gap-2 mb-2">
            <div>
              <span className="text-[10px] text-slate-500 uppercase tracking-wider">Target</span>
              <p className="text-xs font-mono text-slate-300">{sla.responseSLA}</p>
            </div>
            <div>
              <span className="text-[10px] text-slate-500 uppercase tracking-wider">Elapsed</span>
              <p className="text-xs font-mono text-slate-300">{sla.responseElapsed}</p>
            </div>
            <div>
              <span className="text-[10px] text-slate-500 uppercase tracking-wider">Remaining</span>
              <p className="text-xs font-mono text-slate-300">{sla.responseRemaining}</p>
            </div>
          </div>

          <div className="h-2 bg-slate-800 rounded-full overflow-hidden">
            <motion.div
              initial={{ width: 0 }}
              animate={{ width: `${calculateProgress()}%` }}
              transition={{ duration: 0.5 }}
              className={`h-full ${getProgressColor(sla.responseStatus)}`}
            />
          </div>
        </div>

        {/* Resolution SLA */}
        <div className="pt-3 border-t border-white/5">
          <div className="flex items-center justify-between mb-2">
            <div className="flex items-center gap-2">
              <Clock className="h-4 w-4 text-slate-500" />
              <span className="text-xs font-semibold text-white">Resolution SLA</span>
            </div>
            <div className={`flex items-center gap-1 px-2 py-1 rounded-md border text-[10px] font-semibold uppercase tracking-wider ${getStatusColor(sla.resolutionStatus)}`}>
              {sla.resolutionStatus}
            </div>
          </div>

          <div className="grid grid-cols-3 gap-2 mb-2">
            <div>
              <span className="text-[10px] text-slate-500 uppercase tracking-wider">Target</span>
              <p className="text-xs font-mono text-slate-300">{sla.resolutionSLA}</p>
            </div>
            <div>
              <span className="text-[10px] text-slate-500 uppercase tracking-wider">Elapsed</span>
              <p className="text-xs font-mono text-slate-300">{sla.resolutionElapsed}</p>
            </div>
            <div>
              <span className="text-[10px] text-slate-500 uppercase tracking-wider">Remaining</span>
              <p className="text-xs font-mono text-slate-300">{sla.resolutionRemaining}</p>
            </div>
          </div>

          <div className="h-2 bg-slate-800 rounded-full overflow-hidden">
            <motion.div
              initial={{ width: 0 }}
              animate={{ width: '5%' }}
              transition={{ duration: 0.5 }}
              className={`h-full ${getProgressColor(sla.resolutionStatus)}`}
            />
          </div>
        </div>
      </div>
    </motion.div>
  );
}
