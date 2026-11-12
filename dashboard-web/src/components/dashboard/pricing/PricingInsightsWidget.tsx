import { HelpCircle, TrendingUp, TrendingDown, Fuel, ShieldAlert } from 'lucide-react';

export default function PricingInsightsWidget() {
  return (
    <div className="bg-[#0E1320] border border-white/5 rounded-2xl p-5 flex flex-col justify-between h-[340px] shadow-md select-none text-left">
      
      {/* Title Header */}
      <div className="flex justify-between items-center mb-5 flex-shrink-0">
        <h3 className="text-sm font-bold text-white font-sans tracking-tight">
          Pricing Insights
        </h3>
        <HelpCircle className="w-4 h-4 text-slate-500" />
      </div>

      {/* Insights categories lists */}
      <div className="flex flex-col gap-4 overflow-y-auto pr-1 flex-grow scrollbar-none">
        
        {/* 1. Top Profitable */}
        <div className="flex flex-col gap-2">
          <div className="flex items-center gap-1.5 text-[9px] font-bold text-slate-500 uppercase tracking-widest font-sans">
            <TrendingUp className="w-3.5 h-3.5 text-emerald-400" />
            <span>Top Profitable Routes</span>
          </div>
          <div className="flex flex-col gap-1.5 pl-5 text-xs text-slate-300 font-sans">
            <div className="flex justify-between">
              <span>Texas Corridor</span>
              <span className="text-emerald-400 font-bold font-mono">+$24.2k</span>
            </div>
            <div className="flex justify-between">
              <span>Midwest Logistics Loop</span>
              <span className="text-emerald-400 font-bold font-mono">+$18.1k</span>
            </div>
          </div>
        </div>

        {/* 2. Most Expensive */}
        <div className="flex flex-col gap-2">
          <div className="flex items-center gap-1.5 text-[9px] font-bold text-slate-500 uppercase tracking-widest font-sans">
            <TrendingDown className="w-3.5 h-3.5 text-red-400" />
            <span>Most Expensive Lanes</span>
          </div>
          <div className="flex flex-col gap-1.5 pl-5 text-xs text-slate-300 font-sans">
            <div className="flex justify-between">
              <span>California Long Haul</span>
              <span className="text-slate-400 font-mono">$4.82/km</span>
            </div>
            <div className="flex justify-between">
              <span>East Coast Congestion</span>
              <span className="text-slate-400 font-mono">$4.15/km</span>
            </div>
          </div>
        </div>

        {/* 3. Fuel Volatility Impact */}
        <div className="flex flex-col gap-2">
          <div className="flex items-center gap-1.5 text-[9px] font-bold text-slate-500 uppercase tracking-widest font-sans">
            <Fuel className="w-3.5 h-3.5 text-[#00E5FF]" />
            <span>Highest Fuel Impact</span>
          </div>
          <div className="flex justify-between pl-5 text-xs text-slate-300 font-sans">
            <span>Florida Coast Supply Lane</span>
            <span className="text-slate-400 font-bold font-mono">+14.2%</span>
          </div>
        </div>

        {/* 4. Weekly Recommendation summary */}
        <div className="bg-blue-500/5 border border-blue-500/10 rounded-xl p-3 flex gap-2 mt-2">
          <ShieldAlert className="w-4 h-4 text-[#0250C5] flex-shrink-0" />
          <p className="text-[10px] text-slate-400 font-normal leading-relaxed font-sans">
            Weekly Tip: Shift capacity to Texas Corridors to capture surge rates and capitalize on high demand.
          </p>
        </div>

      </div>

    </div>
  );
}
