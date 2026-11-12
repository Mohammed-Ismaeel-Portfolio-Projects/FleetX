import { useState } from 'react';
import { Sparkles, EyeOff, ShieldAlert } from 'lucide-react';
import { motion } from 'framer-motion';

export default function ThreatDetectionAi() {
  const [autoBlock, setAutoBlock] = useState(true);

  return (
    <div className="bg-[#0E1320] border border-white/5 rounded-2xl p-5 flex flex-col justify-between h-[360px] shadow-md select-none text-left">
      
      {/* Title Header */}
      <div className="flex items-center gap-2 mb-5 flex-shrink-0">
        <div className="w-6 h-6 rounded-md bg-[#00E5FF]/10 flex items-center justify-center border border-[#00E5FF]/20 text-[#00E5FF]">
          <Sparkles className="w-3.5 h-3.5" />
        </div>
        <div className="flex flex-col">
          <h3 className="text-sm font-bold text-white font-sans tracking-tight leading-none">
            Threat Detection AI
          </h3>
          <span className="text-[9px] text-slate-500 uppercase tracking-widest font-sans font-bold mt-1">
            Neural Watch Pattern Analysis
          </span>
        </div>
      </div>

      {/* Main Grid: Risk Score vs Active Detections */}
      <div className="grid grid-cols-1 md:grid-cols-12 gap-6 items-stretch flex-grow">
        
        {/* Left column: Overall Risk Score (col-span-4) */}
        <div className="md:col-span-4 rounded-xl bg-[#090D16] border border-white/5 p-4 flex flex-col justify-between items-center text-center">
          <span className="text-[8px] font-bold text-slate-500 uppercase tracking-widest font-sans leading-none mt-1">
            Overall Risk Score
          </span>
          
          <div className="flex flex-col items-center justify-center my-3 leading-none">
            <span className="text-4xl font-extrabold text-[#00E5FF] font-mono leading-none tracking-tighter">
              08
            </span>
          </div>

          <div className="flex flex-col items-center gap-1.5 w-full">
            <span className="text-[7.5px] font-extrabold text-emerald-400 uppercase tracking-wider font-sans bg-emerald-500/10 border border-emerald-500/20 px-2 py-0.5 rounded-full">
              System Stable
            </span>
          </div>
        </div>

        {/* Right column: Detected Patterns (col-span-8) */}
        <div className="md:col-span-8 flex flex-col justify-between gap-4">
          
          {/* Patterns list */}
          <div className="flex flex-col gap-3">
            <div className="flex justify-between items-center text-[9px] font-sans">
              <span className="font-bold text-slate-500 uppercase tracking-widest">
                Detected Patterns
              </span>
              <span className="font-bold text-[#00E5FF] uppercase tracking-widest font-sans animate-pulse">
                Live Analysis
              </span>
            </div>

            {/* Pattern 1 */}
            <div className="flex justify-between items-center bg-white/2 border border-white/5 rounded-lg p-2.5">
              <div className="flex items-center gap-2 text-xs text-slate-300 font-sans">
                <EyeOff className="w-3.5 h-3.5 text-slate-500" />
                <span>Unusual IP-Geolocation</span>
              </div>
              <span className="text-[9px] font-bold text-slate-500 font-sans uppercase">
                No threats
              </span>
            </div>

            {/* Pattern 2 */}
            <div className="flex justify-between items-center bg-white/2 border border-white/5 rounded-lg p-2.5">
              <div className="flex items-center gap-2 text-xs text-slate-300 font-sans">
                <ShieldAlert className="w-3.5 h-3.5 text-red-400" />
                <span>Brute force attempt</span>
              </div>
              <span className="text-[8.5px] font-bold text-red-400 font-sans uppercase bg-red-500/10 border border-red-500/20 px-2 py-0.5 rounded">
                1 Mitigation active
              </span>
            </div>
          </div>

          {/* Toggle Option */}
          <div className="flex justify-between items-center pt-3 border-t border-white/5 mt-auto">
            <span className="text-[10px] font-bold text-slate-400 font-sans">
              Auto-block malicious patterns
            </span>
            
            {/* Toggle slider */}
            <button
              onClick={() => setAutoBlock(!autoBlock)}
              className={`w-8 h-4 rounded-full transition-colors flex items-center p-0.5 cursor-pointer ${
                autoBlock ? 'bg-[#0250C5]' : 'bg-slate-800'
              }`}
              aria-label="Toggle auto block"
              aria-checked={autoBlock}
              role="switch"
            >
              <motion.div 
                layout 
                className="w-3 h-3 rounded-full bg-white"
                animate={{ x: autoBlock ? 14 : 0 }}
                transition={{ type: 'spring', stiffness: 500, damping: 30 }}
              />
            </button>
          </div>

        </div>

      </div>

    </div>
  );
}
