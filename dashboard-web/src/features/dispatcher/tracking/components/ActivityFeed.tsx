import { motion, AnimatePresence } from 'framer-motion';
import { Rss, CheckCircle, AlertTriangle, AlertCircle, Info } from 'lucide-react';
import type { TrackingActivity } from '../types/tracking.types';

interface ActivityFeedProps {
  activities: TrackingActivity[];
}

export default function ActivityFeed({ activities }: ActivityFeedProps) {
  return (
    <div className="bg-[#0E1320] border border-white/5 rounded-2xl p-5 flex flex-col h-[180px] shadow-md select-none text-left w-full overflow-hidden">
      
      {/* Title */}
      <div className="flex items-center gap-2 mb-3.5 flex-shrink-0">
        <Rss className="w-4.5 h-4.5 text-[#0250C5]" />
        <h3 className="text-xs font-bold text-white font-sans uppercase tracking-wider">
          Telemetry Activity Stream
        </h3>
      </div>

      {/* Activities list */}
      <div className="flex-grow overflow-y-auto pr-1 flex flex-col gap-2.5 scrollbar-none">
        <AnimatePresence initial={false}>
          {activities.map((act) => (
            <motion.div
              key={act.id}
              initial={{ opacity: 0, x: -10 }}
              animate={{ opacity: 1, x: 0 }}
              exit={{ opacity: 0, scale: 0.95 }}
              transition={{ duration: 0.25 }}
              className="bg-[#090D16] border border-white/5 rounded-xl p-3 flex items-center justify-between gap-4"
            >
              <div className="flex items-center gap-3">
                {/* Status indicator icon */}
                {act.status === 'success' && <CheckCircle className="w-4 h-4 text-emerald-400 shrink-0" />}
                {act.status === 'warning' && <AlertTriangle className="w-4 h-4 text-amber-400 shrink-0" />}
                {act.status === 'error' && <AlertCircle className="w-4 h-4 text-red-400 shrink-0" />}
                {act.status === 'info' && <Info className="w-4 h-4 text-blue-400 shrink-0" />}

                <span className="text-[10.5px] text-slate-300 font-sans font-medium">
                  {act.message}
                </span>
              </div>

              <span className="text-[8.5px] text-slate-500 font-mono font-bold leading-none shrink-0">
                {act.time}
              </span>
            </motion.div>
          ))}
        </AnimatePresence>
      </div>

    </div>
  );
}
export type { ActivityFeedProps };
