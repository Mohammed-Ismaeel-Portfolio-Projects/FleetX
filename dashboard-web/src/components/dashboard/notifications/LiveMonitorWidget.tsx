import { motion } from 'framer-motion';
import { Plus } from 'lucide-react';

export default function LiveMonitorWidget() {
  return (
    <div className="bg-[#0E1320] border border-white/5 rounded-2xl p-4 flex flex-col justify-between h-[220px] relative overflow-hidden shadow-md select-none">
      
      {/* Radar Map mock grid (HTML/CSS layout) */}
      <div className="absolute inset-0 bg-[#090D16] border border-white/5 rounded-xl m-2 overflow-hidden flex items-center justify-center p-3">
        
        {/* Radar grids overlay */}
        <div 
          className="absolute inset-0 opacity-[0.03]"
          style={{
            backgroundSize: '16px 16px',
            backgroundImage: 'radial-gradient(white 1px, transparent 1px)'
          }}
        />

        {/* Pulse circular radar line */}
        <div className="w-[120px] h-[120px] rounded-full border border-blue-500/10 absolute flex items-center justify-center animate-pulse" />
        <div className="w-[70px] h-[70px] rounded-full border border-blue-500/5 absolute flex items-center justify-center" />

        {/* Dynamic Telemetry nodes */}
        <svg className="w-full h-full text-slate-800 absolute inset-0 p-3" viewBox="0 0 200 120" fill="none">
          {/* Neon Path line */}
          <motion.path 
            d="M 20 80 Q 80 20, 140 100 T 200 40"
            stroke="#0284c7"
            strokeWidth="1.2"
            fill="none"
            initial={{ pathLength: 0 }}
            animate={{ pathLength: 1 }}
            transition={{ duration: 4, repeat: Infinity, ease: 'linear' }}
          />

          {/* Active coordinate dots */}
          <motion.circle 
            cx="80" 
            cy="46" 
            r="3.5" 
            fill="#00E5FF" 
            animate={{ r: [3.5, 5, 3.5] }}
            transition={{ repeat: Infinity, duration: 1.5 }}
          />
          <circle cx="156" cy="80" r="3.5" fill="#334155" />
        </svg>

        {/* Zoom selector bottom right */}
        <button className="absolute bottom-2.5 right-2.5 w-6 h-6 rounded bg-slate-950 border border-white/5 flex items-center justify-center text-slate-400 hover:text-white cursor-pointer hover:bg-slate-800 transition-colors">
          <Plus className="w-3.5 h-3.5" />
        </button>

        {/* Status tag bottom left */}
        <div className="absolute bottom-2.5 left-2.5 bg-slate-950/90 border border-white/5 px-2 py-1 rounded flex items-center gap-1.5">
          <span className="w-1.5 h-1.5 rounded-full bg-cyan-400 animate-ping" />
          <span className="text-[8px] font-bold text-slate-300 uppercase tracking-widest font-mono">
            Current Sector Focus
          </span>
        </div>

      </div>

    </div>
  );
}
