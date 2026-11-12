import { motion } from 'framer-motion';
import { Target, TrendingUp, AlertTriangle } from 'lucide-react';
import type { IncidentPriorityMatrix } from '../../../../../features/dispatcher/notifications/services/notification-center.service';

interface IncidentPriorityMatrixProps {
  matrix: IncidentPriorityMatrix;
}

export default function IncidentPriorityMatrix({ matrix }: IncidentPriorityMatrixProps) {
  const getPriorityColor = (priority: string) => {
    const colors = {
      Low: 'text-green-400 bg-green-500/10 border-green-500/20',
      Medium: 'text-yellow-400 bg-yellow-500/10 border-yellow-500/20',
      High: 'text-orange-400 bg-orange-500/10 border-orange-500/20',
      Critical: 'text-red-400 bg-red-500/10 border-red-500/20',
    };
    return colors[priority as keyof typeof colors] || colors.Low;
  };

  const getLevelColor = (level: string) => {
    const colors = {
      Low: 'text-green-400',
      Medium: 'text-yellow-400',
      High: 'text-orange-400',
      Critical: 'text-red-400',
    };
    return colors[level as keyof typeof colors] || colors.Low;
  };

  return (
    <motion.div
      initial={{ opacity: 0, y: 10 }}
      animate={{ opacity: 1, y: 0 }}
      transition={{ duration: 0.2 }}
      className="rounded-lg border border-white/5 bg-white/[0.02] p-4"
    >
      <h3 className="text-[10px] font-semibold uppercase tracking-wider text-slate-500 mb-4">
        Priority Matrix
      </h3>

      <div className="space-y-3">
        <div className="flex items-center gap-3">
          <Target className="h-4 w-4 text-slate-500" />
          <div className="flex-1">
            <span className="text-[10px] text-slate-500 uppercase tracking-wider">Impact</span>
            <p className={`text-sm font-semibold ${getLevelColor(matrix.impact)}`}>{matrix.impact}</p>
          </div>
        </div>

        <div className="flex items-center gap-3">
          <TrendingUp className="h-4 w-4 text-slate-500" />
          <div className="flex-1">
            <span className="text-[10px] text-slate-500 uppercase tracking-wider">Likelihood</span>
            <p className={`text-sm font-semibold ${getLevelColor(matrix.likelihood)}`}>{matrix.likelihood}</p>
          </div>
        </div>

        <div className="flex items-center gap-3">
          <AlertTriangle className="h-4 w-4 text-slate-500" />
          <div className="flex-1">
            <span className="text-[10px] text-slate-500 uppercase tracking-wider">Business Criticality</span>
            <p className={`text-sm font-semibold ${getLevelColor(matrix.businessCriticality)}`}>{matrix.businessCriticality}</p>
          </div>
        </div>

        <div className="pt-3 border-t border-white/5">
          <span className="text-[10px] text-slate-500 uppercase tracking-wider">Calculated Priority</span>
          <div className={`mt-2 px-3 py-2 rounded-lg border text-sm font-bold uppercase tracking-wider ${getPriorityColor(matrix.calculatedPriority)}`}>
            {matrix.calculatedPriority}
          </div>
        </div>
      </div>
    </motion.div>
  );
}
