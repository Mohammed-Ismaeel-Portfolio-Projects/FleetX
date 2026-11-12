import { motion } from 'framer-motion';

export default function SystemIntegrityWidget() {
  const hotspotBars = [
    { name: 'API Gateway', height: 'h-8' },
    { name: 'MQTT Nodes', height: 'h-12' },
    { name: 'DB Sync', height: 'h-6' },
    { name: 'Sat Coms', height: 'h-10 bg-cyan-500 shadow-xs shadow-cyan-400' },
    { name: 'AI Optimization', height: 'h-4' },
    { name: 'Telemetry Engine', height: 'h-10 bg-cyan-500 shadow-xs shadow-cyan-400' },
  ];

  return (
    <div className="bg-[#0E1320] border border-white/5 rounded-2xl p-5 flex flex-col justify-between h-[340px] shadow-md select-none text-left">
      
      {/* Header Info */}
      <div className="flex justify-between items-start mb-4 flex-shrink-0">
        <div className="flex flex-col">
          <h3 className="text-sm font-bold text-white font-sans tracking-tight">
            System Integrity
          </h3>
        </div>
      </div>

      {/* Hotspots visual graph panel */}
      <div className="flex flex-col gap-2 mb-6">
        <div className="flex justify-between items-center text-[9px] font-sans">
          <span className="font-bold text-slate-500 uppercase tracking-widest">
            Fleet Failure Hotspots
          </span>
          <span className="font-bold text-cyan-400 uppercase tracking-widest font-mono">
            8% Reduction Today
          </span>
        </div>

        {/* Small Bar columns stack */}
        <div className="w-full h-16 rounded-xl bg-white/2 border border-white/5 flex items-end justify-between px-6 py-2.5">
          {hotspotBars.map((bar, idx) => (
            <div key={idx} className="flex flex-col items-center gap-1.5 w-4">
              <div className={`w-2.5 rounded-sm bg-slate-800 ${bar.height} transition-all duration-300`} />
            </div>
          ))}
        </div>
      </div>

      {/* Main progress index */}
      <div className="flex flex-col gap-2 mt-auto">
        <div className="flex justify-between items-center text-[10px] font-sans">
          <span className="font-bold text-slate-500 uppercase tracking-widest">
            System Reliability Score
          </span>
          <span className="font-extrabold text-white font-mono text-[13px]">
            99.8%
          </span>
        </div>

        {/* Reliability progress bar */}
        <div className="w-full bg-white/5 h-1.5 rounded-full overflow-hidden">
          <motion.div 
            initial={{ width: 0 }}
            animate={{ width: '99.8%' }}
            transition={{ duration: 1.2, ease: 'easeOut' }}
            className="bg-cyan-500 h-full rounded-full"
          />
        </div>
      </div>

      {/* Status Footer message */}
      <div className="border-t border-white/5 pt-3.5 mt-4 text-[10px] text-slate-500 font-sans leading-none text-center">
        All redundant data centers online and synced.
      </div>

    </div>
  );
}
