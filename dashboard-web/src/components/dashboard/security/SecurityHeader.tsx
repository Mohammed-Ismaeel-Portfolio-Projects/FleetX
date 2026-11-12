import { RefreshCw, Download, ShieldCheck } from 'lucide-react';

export default function SecurityHeader() {
  return (
    <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6 w-full select-none text-left">
      
      {/* Title stack */}
      <div className="flex flex-col gap-1.5">
        <h1 className="text-2xl font-bold tracking-tight text-white font-sans flex items-center gap-2">
          <span>Security & Access Management</span>
        </h1>
        <p className="text-slate-500 text-xs font-normal font-sans">
          Identity federation, permissions configuration, audit logs, and AI threat mitigation.
        </p>
      </div>

      {/* Action triggers */}
      <div className="flex items-center gap-3.5">
        {/* Security Health status badge */}
        <div className="flex items-center gap-2 px-3 py-1.5 rounded-full bg-emerald-500/5 border border-emerald-500/10">
          <ShieldCheck className="w-4 h-4 text-emerald-400" />
          <span className="text-[8.5px] font-bold text-emerald-400 font-sans uppercase tracking-widest leading-none">
            Shield Active
          </span>
        </div>

        {/* Action button triggers */}
        <button 
          className="p-2 text-slate-400 hover:text-white rounded-lg border border-white/5 bg-white/2 hover:bg-white/5 active:scale-[0.98] transition-all cursor-pointer"
          title="Refresh logs"
          aria-label="Refresh logs"
        >
          <RefreshCw className="w-4 h-4" />
        </button>

        <button 
          className="px-4 py-2 bg-[#0250C5] hover:bg-[#0244a5] active:scale-[0.98] transition-all text-white text-[10px] font-bold rounded-lg uppercase tracking-wider flex items-center gap-1.5 cursor-pointer select-none"
        >
          <Download className="w-3.5 h-3.5" />
          <span>Export Audit</span>
        </button>
      </div>

    </div>
  );
}
