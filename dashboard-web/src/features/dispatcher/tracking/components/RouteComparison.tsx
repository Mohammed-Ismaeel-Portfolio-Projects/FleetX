import { Route, AlertTriangle, Clock, MapPin } from 'lucide-react';

export default function RouteComparison() {
  return (
    <div className="bg-[#0E1320] border border-white/5 rounded-2xl shadow-lg">
      {/* Header */}
      <div className="p-5 border-b border-white/5">
        <div className="flex items-center justify-between">
          <h2 className="text-sm font-bold text-white font-sans flex items-center gap-2">
            <Route className="w-4 h-4 text-[#00E5FF]" />
            Route Comparison
          </h2>
          <span className="text-xs font-semibold text-slate-400">VT-402</span>
        </div>
      </div>

      {/* Content */}
      <div className="p-5">
        {/* Route Visualization */}
        <div className="bg-white/[0.02] border border-white/5 rounded-xl p-4 mb-6">
          <div className="flex items-center gap-4 mb-4">
            {/* Recommended Route */}
            <div className="flex-1">
              <div className="flex items-center gap-2 mb-2">
                <div className="w-3 h-3 rounded-full bg-emerald-400" />
                <span className="text-xs font-semibold text-emerald-400">Recommended</span>
              </div>
              <div className="h-20 bg-emerald-500/10 border border-emerald-500/20 rounded-lg flex items-center justify-center">
                <svg className="w-full h-full" viewBox="0 0 100 40">
                  <path d="M 10 20 Q 30 10, 50 20 T 90 20" stroke="#34d399" strokeWidth="2" fill="none" />
                </svg>
              </div>
            </div>

            {/* VS */}
            <div className="flex items-center justify-center">
              <div className="w-8 h-8 rounded-full bg-white/5 border border-white/10 flex items-center justify-center">
                <span className="text-xs font-bold text-slate-400">VS</span>
              </div>
            </div>

            {/* Actual Route */}
            <div className="flex-1">
              <div className="flex items-center gap-2 mb-2">
                <div className="w-3 h-3 rounded-full bg-amber-400" />
                <span className="text-xs font-semibold text-amber-400">Actual</span>
              </div>
              <div className="h-20 bg-amber-500/10 border border-amber-500/20 rounded-lg flex items-center justify-center">
                <svg className="w-full h-full" viewBox="0 0 100 40">
                  <path d="M 10 20 Q 25 30, 40 15 T 60 25 T 90 20" stroke="#fbbf24" strokeWidth="2" fill="none" />
                </svg>
              </div>
            </div>
          </div>
        </div>

        {/* Comparison Stats */}
        <div className="space-y-4">
          {/* Extra Distance */}
          <div className="bg-white/[0.02] border border-white/5 rounded-xl p-4">
            <div className="flex items-center justify-between mb-2">
              <div className="flex items-center gap-2">
                <MapPin className="w-4 h-4 text-red-400" />
                <span className="text-xs font-semibold text-slate-400">Extra Distance</span>
              </div>
              <span className="text-lg font-bold text-red-400 font-mono">+12 mi</span>
            </div>
            <div className="flex items-center justify-between text-[10px] text-slate-500">
              <span>Recommended: 245 mi</span>
              <span>Actual: 257 mi</span>
            </div>
          </div>

          {/* Extra Time */}
          <div className="bg-white/[0.02] border border-white/5 rounded-xl p-4">
            <div className="flex items-center justify-between mb-2">
              <div className="flex items-center gap-2">
                <Clock className="w-4 h-4 text-amber-400" />
                <span className="text-xs font-semibold text-slate-400">Extra Time</span>
              </div>
              <span className="text-lg font-bold text-amber-400 font-mono">+18 min</span>
            </div>
            <div className="flex items-center justify-between text-[10px] text-slate-500">
              <span>Recommended: 4h 32m</span>
              <span>Actual: 4h 50m</span>
            </div>
          </div>

          {/* Deviation */}
          <div className="bg-white/[0.02] border border-white/5 rounded-xl p-4">
            <div className="flex items-center justify-between mb-2">
              <div className="flex items-center gap-2">
                <AlertTriangle className="w-4 h-4 text-orange-400" />
                <span className="text-xs font-semibold text-slate-400">Deviation</span>
              </div>
              <span className="text-lg font-bold text-orange-400 font-mono">4.9%</span>
            </div>
            <div className="w-full h-2 bg-white/5 rounded-full overflow-hidden">
              <div className="h-full bg-gradient-to-r from-emerald-500 to-orange-500" style={{ width: '4.9%' }} />
            </div>
          </div>
        </div>

        {/* Deviation Points */}
        <div className="mt-6">
          <h3 className="text-xs font-bold text-slate-400 uppercase tracking-wider mb-3">Deviation Points</h3>
          <div className="space-y-2">
            <div className="flex items-center gap-3 p-3 bg-white/[0.02] border border-white/5 rounded-lg">
              <div className="w-2 h-2 rounded-full bg-amber-400" />
              <div className="flex-1">
                <p className="text-xs font-semibold text-white">Cleveland Area</p>
                <p className="text-[10px] text-slate-500">Traffic detour +8 mi</p>
              </div>
              <span className="text-xs font-bold text-amber-400 font-mono">+12 min</span>
            </div>
          </div>
        </div>
      </div>

      {/* Footer */}
      <div className="p-4 border-t border-white/5">
        <button className="w-full px-4 py-2 bg-white/5 hover:bg-white/10 border border-white/10 text-white text-xs font-bold rounded-lg transition-colors">
          View Detailed Analysis
        </button>
      </div>
    </div>
  );
}
