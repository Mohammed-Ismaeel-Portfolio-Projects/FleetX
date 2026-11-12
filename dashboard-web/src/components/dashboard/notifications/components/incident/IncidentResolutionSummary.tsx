import { motion } from 'framer-motion';
import { CheckCircle, Clock, TrendingDown, FileText, Lightbulb } from 'lucide-react';
import type { IncidentResolutionSummary } from '../../../../../features/dispatcher/notifications/services/notification-center.service';

interface IncidentResolutionSummaryProps {
  resolution: IncidentResolutionSummary;
}

export default function IncidentResolutionSummary({ resolution }: IncidentResolutionSummaryProps) {
  if (!resolution.isResolved) {
    return null;
  }

  return (
    <motion.div
      initial={{ opacity: 0, y: 10 }}
      animate={{ opacity: 1, y: 0 }}
      transition={{ duration: 0.2 }}
      className="rounded-lg border border-green-500/20 bg-green-500/5 p-4"
    >
      <div className="flex items-center gap-2 mb-4">
        <CheckCircle className="h-4 w-4 text-green-400" />
        <h3 className="text-[10px] font-semibold uppercase tracking-wider text-green-400">
          Resolution Summary
        </h3>
      </div>

      <div className="space-y-4">
        <div>
          <div className="flex items-center gap-2 mb-2">
            <FileText className="h-4 w-4 text-slate-500" />
            <span className="text-[10px] text-slate-500 uppercase tracking-wider">Root Cause</span>
          </div>
          <p className="text-sm text-slate-300 leading-relaxed">{resolution.rootCause}</p>
        </div>

        <div>
          <div className="flex items-center gap-2 mb-2">
            <CheckCircle className="h-4 w-4 text-slate-500" />
            <span className="text-[10px] text-slate-500 uppercase tracking-wider">Actions Taken</span>
          </div>
          <ul className="space-y-1">
            {resolution.actionsTaken.map((action, index) => (
              <li key={index} className="text-xs text-slate-300 flex items-start gap-2">
                <span className="text-green-400 mt-0.5">•</span>
                <span>{action}</span>
              </li>
            ))}
          </ul>
        </div>

        <div className="grid grid-cols-2 gap-4">
          <div>
            <div className="flex items-center gap-2 mb-2">
              <Clock className="h-4 w-4 text-slate-500" />
              <span className="text-[10px] text-slate-500 uppercase tracking-wider">Time To Resolve</span>
            </div>
            <p className="text-sm font-mono text-slate-300">{resolution.timeToResolve}</p>
          </div>

          <div>
            <div className="flex items-center gap-2 mb-2">
              <TrendingDown className="h-4 w-4 text-slate-500" />
              <span className="text-[10px] text-slate-500 uppercase tracking-wider">Downtime</span>
            </div>
            <p className="text-sm font-mono text-slate-300">{resolution.downtime}</p>
          </div>
        </div>

        <div>
          <div className="flex items-center gap-2 mb-2">
            <TrendingDown className="h-4 w-4 text-slate-500" />
            <span className="text-[10px] text-slate-500 uppercase tracking-wider">Business Impact</span>
          </div>
          <p className="text-sm text-slate-300">{resolution.businessImpact}</p>
        </div>

        <div className="pt-3 border-t border-white/5">
          <div className="flex items-center gap-2 mb-2">
            <Lightbulb className="h-4 w-4 text-slate-500" />
            <span className="text-[10px] text-slate-500 uppercase tracking-wider">Lessons Learned</span>
          </div>
          <p className="text-sm text-slate-300 leading-relaxed">{resolution.lessonsLearned}</p>
        </div>
      </div>
    </motion.div>
  );
}
