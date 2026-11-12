import { Map } from 'lucide-react';

export default function MapPlaceholder() {
  return (
    <div className="flex h-full min-h-[400px] items-center justify-center rounded-2xl border border-white/5 bg-[#0E1320]">
      <div className="text-center">
        <div className="mx-auto mb-4 flex h-20 w-20 items-center justify-center rounded-full bg-cyan-500/10">
          <Map className="h-10 w-10 text-cyan-400" />
        </div>
        <h3 className="text-lg font-semibold text-white">Map View</h3>
        <p className="mt-2 text-sm text-slate-400">
          Map will display live fleet positions.
        </p>
        <p className="mt-1 text-xs text-slate-500">
          Interactive map coming in next sprint.
        </p>
      </div>
    </div>
  );
}
