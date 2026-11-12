import { motion } from 'framer-motion';
import { MoreVertical } from 'lucide-react';
import { cn } from '../../../lib/utils';

interface StreamItem {
  id: string;
  title: string;
  description: string;
  time: string;
  source: string;
  indicatorColor: string;
}

interface AlertStreamProps {
  onAlertClick?: (alertId: string) => void;
}

const streamLogs: StreamItem[] = [
  {
    id: 'log-1',
    title: 'Route Deviation Detected',
    description: 'Unit #992 moved 1.2km off the corridor. GPS Lock: Stable.',
    time: '14:22:45',
    source: 'GPS/Telemetry',
    indicatorColor: 'bg-amber-500',
  },
  {
    id: 'log-2',
    title: 'AI Rerouting Triggered',
    description: 'Optimized flow for Terminal 4 due to incoming surge detections.',
    time: '14:18:15',
    source: 'Fleet Intelligence',
    indicatorColor: 'bg-cyan-500',
  },
];

export default function AlertStream({ onAlertClick }: AlertStreamProps) {
  return (
    <div className="flex flex-col gap-3.5 w-full text-left select-none">
      
      {/* Section Title */}
      <div className="flex items-center gap-2 mb-3">
        <h3 className="text-xs font-bold text-slate-500 uppercase tracking-widest font-sans">
          Global Alert Stream
        </h3>
      </div>

      {/* Logs Feed Stack */}
      <div className="flex flex-col gap-3 w-full">
        {streamLogs.map((log) => (
          <motion.div
            key={log.id}
            whileHover={{ x: 2, backgroundColor: '#121829' }}
            onClick={() => onAlertClick?.(log.id)}
            className="bg-[#0E1320] border border-white/5 rounded-xl p-4.5 flex items-center justify-between gap-6 transition-colors duration-150 relative overflow-hidden cursor-pointer"
          >
            {/* Left Edge Priority Indicator Line */}
            <div className={cn('absolute left-0 top-0 bottom-0 w-1.5 rounded-l-xl', log.indicatorColor)} />

            {/* Left Content Stack */}
            <div className="flex-grow pl-3.5 min-w-0">
              <h4 className="text-xs font-bold text-slate-200 font-sans tracking-tight mb-1">
                {log.title}
              </h4>
              <p className="text-slate-400 text-[11px] font-normal leading-normal truncate max-w-[400px] md:max-w-none">
                {log.description}
              </p>
            </div>

            {/* Middle Metadata (Time & Source) */}
            <div className="flex items-center gap-6 flex-shrink-0 text-right">
              {/* Time */}
              <span className="text-[10px] text-slate-500 font-mono font-bold">
                {log.time}
              </span>
              
              {/* Source */}
              <div className="flex flex-col items-end">
                <span className="text-[7.5px] font-bold text-slate-500 tracking-wider uppercase font-sans">
                  source
                </span>
                <span className="text-[10px] font-bold text-slate-300 font-sans mt-0.5 leading-none">
                  {log.source}
                </span>
              </div>
            </div>

            {/* Right Action Vertical dots */}
            <button 
              className="p-1 text-slate-500 hover:text-white rounded hover:bg-white/5 cursor-pointer flex-shrink-0"
              title="Actions"
              aria-label="Alert actions"
            >
              <MoreVertical className="w-4 h-4" />
            </button>
          </motion.div>
        ))}
      </div>

    </div>
  );
}
