import { motion } from 'framer-motion';
import { ArrowUp, CheckCircle, Circle } from 'lucide-react';
import type { IncidentEscalationStatus } from '../../../../../features/dispatcher/notifications/services/notification-center.service';

interface IncidentEscalationStatusProps {
  escalation: IncidentEscalationStatus;
}

export default function IncidentEscalationStatus({ escalation }: IncidentEscalationStatusProps) {
  const getLevelIcon = (status: string) => {
    if (status === 'active') return CheckCircle;
    if (status === 'escalated') return ArrowUp;
    return Circle;
  };

  const getLevelColor = (status: string) => {
    if (status === 'active') return 'text-green-400 bg-green-500/10 border-green-500/20';
    if (status === 'escalated') return 'text-orange-400 bg-orange-500/10 border-orange-500/20';
    return 'text-slate-600 bg-slate-500/5 border-slate-500/10';
  };

  return (
    <motion.div
      initial={{ opacity: 0, y: 10 }}
      animate={{ opacity: 1, y: 0 }}
      transition={{ duration: 0.2 }}
      className="rounded-lg border border-white/5 bg-white/[0.02] p-4"
    >
      <h3 className="text-[10px] font-semibold uppercase tracking-wider text-slate-500 mb-4">
        Escalation Status
      </h3>

      <div className="space-y-3">
        {escalation.escalationPath.map((level, index) => {
          const Icon = getLevelIcon(level.status);
          const colorClass = getLevelColor(level.status);
          const isLast = index === escalation.escalationPath.length - 1;

          return (
            <div key={level.id} className="flex gap-3">
              <div className="flex flex-col items-center">
                <div className={`w-8 h-8 rounded-full border ${colorClass} flex items-center justify-center`}>
                  <Icon className="h-4 w-4" />
                </div>
                {!isLast && <div className="w-px h-full bg-white/10 mt-2" />}
              </div>

              <div className="flex-1 pb-4">
                <div className="flex items-center gap-2 mb-1">
                  <span className="text-xs font-semibold text-white">{level.level}</span>
                  {level.timestamp && (
                    <span className="text-[10px] text-slate-500 font-mono">{level.timestamp}</span>
                  )}
                </div>
                {level.actor && (
                  <p className="text-[10px] text-slate-400">by {level.actor}</p>
                )}
              </div>
            </div>
          );
        })}
      </div>

      <div className="pt-3 border-t border-white/5 mt-3">
        <span className="text-[10px] text-slate-500 uppercase tracking-wider">Current Level</span>
        <div className="mt-2 px-3 py-2 rounded-lg border border-green-500/20 bg-green-500/10">
          <p className="text-sm font-bold text-green-400 uppercase tracking-wider">{escalation.currentLevel}</p>
        </div>
      </div>
    </motion.div>
  );
}
