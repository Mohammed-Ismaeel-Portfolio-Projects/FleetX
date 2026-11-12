import { Truck } from 'lucide-react';

export default function EmptyState() {
  return (
    <div className="flex flex-col items-center justify-center rounded-2xl border border-white/5 bg-[#0E1320] p-12 text-center shadow-md">
      <div className="mb-4 flex h-20 w-20 items-center justify-center rounded-full bg-cyan-500/10">
        <Truck className="h-10 w-10 text-cyan-400" />
      </div>
      <h3 className="text-lg font-semibold text-white">No tracking data available</h3>
      <p className="mt-2 text-sm text-slate-400">
        Connect to the backend to start tracking your fleet.
      </p>
      <p className="mt-1 text-xs text-slate-500">
        Tracking data will appear here once vehicles are active.
      </p>
    </div>
  );
}
