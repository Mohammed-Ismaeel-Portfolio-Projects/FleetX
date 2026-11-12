import { motion } from 'framer-motion';
import { AlertOctagon } from 'lucide-react';

export default function EmergencyAlertWidget() {
  return (
    <motion.div
      initial={{ opacity: 0, y: 10 }}
      animate={{ opacity: 1, y: 0 }}
      transition={{ duration: 0.5, ease: [0.16, 1, 0.3, 1] as const }}
      className="bg-[#1C0F16] border border-red-500/25 rounded-2xl p-5 md:p-6 shadow-lg shadow-red-500/5 relative overflow-hidden text-left w-full select-none"
    >
      {/* Absolute pulsing background red glow */}
      <div className="absolute top-0 right-0 w-[120px] h-[120px] bg-red-500/5 rounded-full blur-[40px] pointer-events-none" />

      {/* Main Flex columns */}
      <div className="flex flex-col md:flex-row justify-between items-start gap-6 relative z-10">
        
        {/* Left: Icon and alert content stack */}
        <div className="flex items-start gap-4 flex-grow min-w-0">
          {/* Glowing Red Warning Icon */}
          <div className="w-10 h-10 rounded-xl bg-red-500/10 border border-red-500/30 flex items-center justify-center flex-shrink-0 text-red-500 animate-pulse">
            <AlertOctagon className="w-5 h-5" />
          </div>

          <div className="flex flex-col min-w-0">
            {/* Title & Status Badge Row */}
            <div className="flex items-center gap-3 mb-2 flex-wrap">
              <h3 className="text-sm font-bold text-white font-sans tracking-tight">
                Emergency Route Failure
              </h3>
              {/* Pulsing Active Now Badge */}
              <span className="inline-flex items-center gap-1.5 px-2 py-0.5 rounded bg-red-500 text-[8px] font-bold uppercase tracking-wider text-white shadow-sm shadow-red-500/20">
                <span className="w-1 h-1 bg-white rounded-full animate-ping" />
                <span>Active Now</span>
              </span>
            </div>

            {/* Description Details */}
            <p className="text-red-200/70 text-[11.5px] leading-relaxed mb-4 max-w-xl">
              Sector 7-B: Total signal loss for 12 transit units. Predicted logistics cascade failure in <span className="text-white font-mono font-bold">08m:45s</span>. Urgent manual route overrides recommended.
            </p>

            {/* Metadata Tags */}
            <div className="flex items-center gap-3 text-[9px] font-bold uppercase tracking-widest font-mono text-red-400">
              <span className="px-2 py-0.5 rounded bg-red-500/10 border border-red-500/15">ID: #SYS-0994</span>
              <span className="px-2 py-0.5 rounded bg-red-500/10 border border-red-500/15">LOC: N-PACIFIC HUB</span>
            </div>
          </div>
        </div>

        {/* Right: Actions Button Stack */}
        <div className="flex flex-col gap-2.5 w-full md:w-auto flex-shrink-0 md:min-w-[120px]">
          <button className="w-full py-2 px-4 bg-red-500 hover:bg-red-600 active:scale-[0.98] transition-all text-white text-[10px] font-bold rounded-lg uppercase tracking-wider cursor-pointer select-none text-center shadow-md shadow-red-500/10">
            Resolve
          </button>
          <button className="w-full py-2 px-4 border border-white/10 hover:border-white/20 hover:bg-white/5 active:scale-[0.98] transition-all text-slate-300 text-[10px] font-bold rounded-lg uppercase tracking-wider cursor-pointer select-none text-center">
            Assign
          </button>
          <button className="w-full py-1.5 text-red-400 hover:text-red-300 active:scale-[0.98] transition-all text-[10px] font-bold uppercase tracking-wider cursor-pointer select-none text-center font-sans">
            Escalate
          </button>
        </div>

      </div>

    </motion.div>
  );
}
