import { motion } from 'framer-motion';
import { CheckCircle, Clock, Circle } from 'lucide-react';
import type { IncidentLifecycle } from '../../../../../features/dispatcher/notifications/services/notification-center.service';

interface IncidentLifecycleProps {
  lifecycle: IncidentLifecycle;
}

export default function IncidentLifecycle({ lifecycle }: IncidentLifecycleProps) {
  const getStepIcon = (status: string) => {
    if (status === 'completed') return CheckCircle;
    if (status === 'current') return Clock;
    return Circle;
  };

  const getStepColor = (status: string) => {
    if (status === 'completed') return 'text-green-400';
    if (status === 'current') return 'text-orange-400';
    return 'text-slate-600';
  };

  const getStepBgColor = (status: string) => {
    if (status === 'completed') return 'bg-green-500/10 border-green-500/20';
    if (status === 'current') return 'bg-orange-500/10 border-orange-500/20';
    return 'bg-slate-500/5 border-slate-500/10';
  };

  return (
    <motion.div
      initial={{ opacity: 0, y: 10 }}
      animate={{ opacity: 1, y: 0 }}
      transition={{ duration: 0.2 }}
      className="rounded-lg border border-white/5 bg-white/[0.02] p-4"
    >
      <h3 className="text-[10px] font-semibold uppercase tracking-wider text-slate-500 mb-4">
        Incident Lifecycle
      </h3>

      <div className="space-y-3">
        {lifecycle.steps.map((step, index) => {
          const Icon = getStepIcon(step.status);
          const colorClass = getStepColor(step.status);
          const bgClass = getStepBgColor(step.status);
          const isLast = index === lifecycle.steps.length - 1;

          return (
            <div key={step.id} className="flex gap-3">
              <div className="flex flex-col items-center">
                <div className={`w-8 h-8 rounded-full border ${bgClass} flex items-center justify-center ${colorClass}`}>
                  <Icon className="h-4 w-4" />
                </div>
                {!isLast && <div className="w-px h-full bg-white/10 mt-2" />}
              </div>

              <div className="flex-1 pb-4">
                <div className="flex items-center gap-2 mb-1">
                  <span className="text-xs font-semibold text-white">{step.name}</span>
                  {step.timestamp && (
                    <span className="text-[10px] text-slate-500 font-mono">{step.timestamp}</span>
                  )}
                </div>
                {step.actor && (
                  <p className="text-[10px] text-slate-400">by {step.actor}</p>
                )}
              </div>
            </div>
          );
        })}
      </div>
    </motion.div>
  );
}
