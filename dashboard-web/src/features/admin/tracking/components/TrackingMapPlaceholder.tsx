import { Map } from 'lucide-react';

export default function TrackingMapPlaceholder() {
  return (
    <div className="flex h-[300px] items-center justify-center rounded-2xl border border-white/5 bg-[#0E1320]">
      <div className="text-center">
        <Map className="mx-auto h-12 w-12 text-slate-600" />
        <p className="mt-3 text-sm font-semibold text-white">Map View</p>
        <p className="mt-1 text-xs text-slate-500">Live tracking map coming in next sprint</p>
      </div>
    </div>
  );
}
