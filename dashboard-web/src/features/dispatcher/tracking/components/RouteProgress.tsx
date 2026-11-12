import { MapPin } from 'lucide-react';

export default function RouteProgress() {
  return (
    <div className="bg-white/[0.02] border border-white/5 rounded-xl p-4">
      <h3 className="text-xs font-bold text-slate-400 uppercase tracking-wider mb-4 flex items-center gap-2">
        <MapPin className="w-4 h-4" />
        Route Progress
      </h3>

      <div className="flex flex-col items-center justify-center py-8 text-center">
        <MapPin className="w-12 h-12 text-slate-600 mb-3" />
        <p className="text-sm font-semibold text-slate-400 mb-2">Route Progress Coming Soon</p>
        <p className="text-xs text-slate-500 max-w-md">
          Route path visualization and progress tracking will be available in a future update.
        </p>
      </div>
    </div>
  );
}
