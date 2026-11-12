import { Clock } from 'lucide-react';

export default function DriverTimeline() {
  return (
    <div className="bg-[#0E1320] border border-white/5 rounded-2xl shadow-lg flex flex-col h-full">
      <div className="p-5 border-b border-white/5">
        <h2 className="text-sm font-bold text-white font-sans flex items-center gap-2">
          <Clock className="w-4 h-4 text-[#00E5FF]" />
          Driver Timeline
        </h2>
      </div>
      <div className="flex-1 flex items-center justify-center p-8">
        <p className="text-sm text-slate-400 text-center">
          Unavailable — no driver activity timeline endpoint
        </p>
      </div>
    </div>
  );
}
