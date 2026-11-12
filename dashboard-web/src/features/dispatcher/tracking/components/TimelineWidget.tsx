import { Clock } from 'lucide-react';

export default function TimelineWidget() {
  return (
    <div className="bg-[#0E1320] border border-white/5 rounded-2xl shadow-lg">
      {/* Header */}
      <div className="p-5 border-b border-white/5">
        <div className="flex items-center justify-between">
          <h2 className="text-sm font-bold text-white font-sans flex items-center gap-2">
            <Clock className="w-4 h-4 text-[#00E5FF]" />
            Live Operations Timeline
          </h2>
          <span className="text-xs font-semibold text-slate-400">Coming Soon</span>
        </div>
      </div>

      {/* Content */}
      <div className="p-5 max-h-[400px] overflow-y-auto">
        <div className="flex flex-col items-center justify-center py-12 text-center">
          <Clock className="w-16 h-16 text-slate-600 mb-4" />
          <p className="text-sm font-semibold text-slate-400 mb-2">Timeline Coming Soon</p>
          <p className="text-xs text-slate-500 max-w-md">
            Activity timeline and event logging will be available in a future update.
          </p>
        </div>
      </div>

      {/* Footer */}
      <div className="p-4 border-t border-white/5">
        <div className="flex items-center justify-between">
          <div className="flex items-center gap-4">
            <div className="flex items-center gap-2">
              <div className="w-2 h-2 rounded-full bg-emerald-400" />
              <span className="text-[10px] text-slate-400">Info</span>
            </div>
            <div className="flex items-center gap-2">
              <div className="w-2 h-2 rounded-full bg-amber-400" />
              <span className="text-[10px] text-slate-400">Warning</span>
            </div>
            <div className="flex items-center gap-2">
              <div className="w-2 h-2 rounded-full bg-red-400" />
              <span className="text-[10px] text-slate-400">Critical</span>
            </div>
          </div>
          <button className="text-xs font-semibold text-slate-500 cursor-not-allowed">
            View All Activity
          </button>
        </div>
      </div>
    </div>
  );
}
