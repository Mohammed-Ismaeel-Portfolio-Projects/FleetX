import { User } from 'lucide-react';

export default function DriverHealthCard() {
  return (
    <div className="bg-white/[0.02] border border-white/5 rounded-xl p-4">
      <h3 className="text-xs font-bold text-slate-400 uppercase tracking-wider mb-4 flex items-center gap-2">
        <User className="w-4 h-4" />
        Driver Health & Status
      </h3>

      <div className="flex flex-col items-center justify-center py-8 text-center">
        <User className="w-12 h-12 text-slate-600 mb-3" />
        <p className="text-sm font-semibold text-slate-400 mb-2">Driver Health Coming Soon</p>
        <p className="text-xs text-slate-500 max-w-md">
          Driver health monitoring, HOS compliance, and status tracking will be available in a future update.
        </p>
      </div>
    </div>
  );
}
