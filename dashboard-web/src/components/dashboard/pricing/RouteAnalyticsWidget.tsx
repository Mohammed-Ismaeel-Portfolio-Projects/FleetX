import { motion } from 'framer-motion';
import { BarChart3 } from 'lucide-react';

export default function RouteAnalyticsWidget() {
  return (
    <div className="bg-[#0E1320] border border-white/5 rounded-2xl p-5 flex flex-col justify-between h-[340px] relative overflow-hidden shadow-md select-none text-left">
      
      {/* Header & Legend Panel */}
      <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-4 z-10">
        <div className="flex flex-col">
          <h3 className="text-sm font-bold text-white font-sans tracking-tight">
            Route Cost Analytics
          </h3>
          <span className="text-[10px] text-slate-500 font-sans mt-0.5">
            Operational spend vs revenue yields
          </span>
        </div>

        {/* Custom Legend circles */}
        <div className="flex items-center gap-4 flex-wrap">
          <div className="flex items-center gap-1.5 text-[9px] font-bold text-slate-400 uppercase tracking-widest font-sans">
            <span className="w-2.5 h-2.5 rounded-full bg-[#0250C5] block" />
            <span>Revenue</span>
          </div>
          <div className="flex items-center gap-1.5 text-[9px] font-bold text-slate-400 uppercase tracking-widest font-sans">
            <span className="w-2.5 h-2.5 rounded-full bg-[#00E5FF] block" />
            <span>Route Cost</span>
          </div>
          <div className="flex items-center gap-1.5 text-[9px] font-bold text-slate-400 uppercase tracking-widest font-sans">
            <span className="w-2.5 h-2.5 rounded-full bg-red-400 block" />
            <span>Fuel Cost</span>
          </div>
        </div>
      </div>

      {/* Main Chart SVG view */}
      <div className="flex-grow w-full rounded-xl bg-[#090D16] border border-white/5 relative overflow-hidden flex items-end p-4">
        
        {/* Subtle grid indicators */}
        <div className="absolute inset-0 flex flex-col justify-between py-6 px-4 opacity-[0.03] pointer-events-none">
          <div className="h-px bg-white w-full" />
          <div className="h-px bg-white w-full" />
          <div className="h-px bg-white w-full" />
          <div className="h-px bg-white w-full" />
        </div>

        {/* Drawing SVG Lines */}
        <svg className="w-full h-full text-slate-800 absolute inset-0 px-4 py-6" viewBox="0 0 200 60" fill="none">
          <defs>
            <linearGradient id="revenue-grad" x1="0" y1="0" x2="0" y2="1">
              <stop offset="0%" stopColor="#0250C5" stopOpacity="0.15" />
              <stop offset="100%" stopColor="#0250C5" stopOpacity="0" />
            </linearGradient>
            <linearGradient id="cost-grad" x1="0" y1="0" x2="0" y2="1">
              <stop offset="0%" stopColor="#00E5FF" stopOpacity="0.1" />
              <stop offset="100%" stopColor="#00E5FF" stopOpacity="0" />
            </linearGradient>
          </defs>

          {/* 1. Revenue Line (Blue - Upward trend) */}
          <motion.path 
            d="M 0 45 C 30 38, 60 25, 100 22 C 140 18, 170 12, 200 8"
            stroke="#0250C5"
            strokeWidth="1.8"
            fill="none"
            initial={{ pathLength: 0 }}
            animate={{ pathLength: 1 }}
            transition={{ duration: 1.5, ease: 'easeOut' }}
          />
          <path 
            d="M 0 45 C 30 38, 60 25, 100 22 C 140 18, 170 12, 200 8 L 200 60 L 0 60 Z"
            fill="url(#revenue-grad)"
          />

          {/* 2. Route Cost Line (Cyan - Mid level fluctuation) */}
          <motion.path 
            d="M 0 52 C 30 45, 60 48, 100 38 C 140 28, 170 34, 200 24"
            stroke="#00E5FF"
            strokeWidth="1.5"
            fill="none"
            initial={{ pathLength: 0 }}
            animate={{ pathLength: 1 }}
            transition={{ duration: 1.8, ease: 'easeOut', delay: 0.2 }}
          />
          <path 
            d="M 0 52 C 30 45, 60 48, 100 38 C 140 28, 170 34, 200 24 L 200 60 L 0 60 Z"
            fill="url(#cost-grad)"
          />

          {/* 3. Fuel Cost Line (Red - Low level trend) */}
          <motion.path 
            d="M 0 58 Q 40 50, 80 54 T 140 45 T 200 38"
            stroke="#f87171"
            strokeWidth="1.2"
            fill="none"
            initial={{ pathLength: 0 }}
            animate={{ pathLength: 1 }}
            transition={{ duration: 2, ease: 'easeOut', delay: 0.4 }}
          />
        </svg>

        {/* Monospace details legend tags */}
        <div className="absolute bottom-2 left-4 flex gap-4 text-[8px] font-bold text-slate-500 font-mono">
          <span>Q1-Q2 INTERVAL</span>
          <span>SYNC STATUS: ACTIVE</span>
        </div>
      </div>
      
      {/* Footer Sparkline analysis */}
      <div className="flex items-center gap-1.5 mt-3 select-none">
        <BarChart3 className="w-3.5 h-3.5 text-slate-500" />
        <span className="text-[10px] text-slate-500 font-sans">
          Margin expansion tracked at <span className="text-emerald-400 font-semibold font-mono">+1.8%</span> relative to fuel volatility indexes.
        </span>
      </div>

    </div>
  );
}
