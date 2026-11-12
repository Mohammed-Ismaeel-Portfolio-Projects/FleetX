import { Clock, AlertTriangle, User, Package, Truck } from 'lucide-react';

interface WaitingTimeData {
  loadingWaitingTime: number;
  unloadingWaitingTime: number;
  trafficDelay: number;
  customerDelay: number;
}

interface WaitingTimeProps {
  data: WaitingTimeData;
  onChange: (data: WaitingTimeData) => void;
}

export default function WaitingTime({ data, onChange }: WaitingTimeProps) {
  const handleChange = (field: keyof WaitingTimeData, value: number) => {
    onChange({ ...data, [field]: value });
  };

  return (
    <div className="rounded-xl border border-white/5 bg-[#0E1320] p-5 shadow-sm">
      <div className="mb-4 flex items-center gap-2">
        <Clock className="h-4 w-4 text-cyan-400" />
        <h3 className="text-sm font-semibold text-white">Waiting Time</h3>
      </div>

      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        {/* Loading Waiting Time */}
        <div className="rounded-lg border border-white/5 bg-[#0A0E17] p-4">
          <div className="mb-2 flex items-center gap-2">
            <Package className="h-3 w-3 text-green-400" />
            <label className="text-[10px] font-semibold uppercase tracking-wider text-slate-500">
              Loading Waiting Time
            </label>
          </div>
          <div className="flex items-center gap-2">
            <input
              type="number"
              value={data.loadingWaitingTime}
              onChange={(e) => handleChange('loadingWaitingTime', parseFloat(e.target.value) || 0)}
              className="w-full rounded border border-white/10 bg-[#0E1320] px-2 py-1.5 text-xs text-white outline-none focus:border-cyan-500/50"
            />
            <span className="text-xs text-slate-400">min</span>
          </div>
        </div>

        {/* Unloading Waiting Time */}
        <div className="rounded-lg border border-white/5 bg-[#0A0E17] p-4">
          <div className="mb-2 flex items-center gap-2">
            <Truck className="h-3 w-3 text-blue-400" />
            <label className="text-[10px] font-semibold uppercase tracking-wider text-slate-500">
              Unloading Waiting Time
            </label>
          </div>
          <div className="flex items-center gap-2">
            <input
              type="number"
              value={data.unloadingWaitingTime}
              onChange={(e) => handleChange('unloadingWaitingTime', parseFloat(e.target.value) || 0)}
              className="w-full rounded border border-white/10 bg-[#0E1320] px-2 py-1.5 text-xs text-white outline-none focus:border-cyan-500/50"
            />
            <span className="text-xs text-slate-400">min</span>
          </div>
        </div>

        {/* Traffic Delay */}
        <div className="rounded-lg border border-white/5 bg-[#0A0E17] p-4">
          <div className="mb-2 flex items-center gap-2">
            <AlertTriangle className="h-3 w-3 text-orange-400" />
            <label className="text-[10px] font-semibold uppercase tracking-wider text-slate-500">
              Traffic Delay
            </label>
          </div>
          <div className="flex items-center gap-2">
            <input
              type="number"
              value={data.trafficDelay}
              onChange={(e) => handleChange('trafficDelay', parseFloat(e.target.value) || 0)}
              className="w-full rounded border border-white/10 bg-[#0E1320] px-2 py-1.5 text-xs text-white outline-none focus:border-cyan-500/50"
            />
            <span className="text-xs text-slate-400">min</span>
          </div>
        </div>

        {/* Customer Delay */}
        <div className="rounded-lg border border-white/5 bg-[#0A0E17] p-4">
          <div className="mb-2 flex items-center gap-2">
            <User className="h-3 w-3 text-purple-400" />
            <label className="text-[10px] font-semibold uppercase tracking-wider text-slate-500">
              Customer Delay
            </label>
          </div>
          <div className="flex items-center gap-2">
            <input
              type="number"
              value={data.customerDelay}
              onChange={(e) => handleChange('customerDelay', parseFloat(e.target.value) || 0)}
              className="w-full rounded border border-white/10 bg-[#0E1320] px-2 py-1.5 text-xs text-white outline-none focus:border-cyan-500/50"
            />
            <span className="text-xs text-slate-400">min</span>
          </div>
        </div>
      </div>

      <div className="mt-4 rounded-lg border border-white/5 bg-[#0A0E17] p-3">
        <div className="text-[10px] text-slate-400">
          <span className="font-semibold text-slate-300">Note:</span> Waiting time charges apply based on configured pricing policies. Free time is included for loading and unloading.
        </div>
      </div>
    </div>
  );
}
