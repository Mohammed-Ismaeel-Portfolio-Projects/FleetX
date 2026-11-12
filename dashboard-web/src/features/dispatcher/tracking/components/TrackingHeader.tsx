import { useState, useEffect } from 'react';
import { RefreshCw, Search, Crosshair, Globe } from 'lucide-react';

export default function TrackingHeader() {
  const [currentTime, setCurrentTime] = useState('');
  const [isRefreshing, setIsRefreshing] = useState(false);

  useEffect(() => {
    const updateTime = () => {
      const now = new Date();
      setCurrentTime(now.toLocaleTimeString('en-US', { 
        hour: '2-digit', 
        minute: '2-digit',
        second: '2-digit',
        hour12: true 
      }));
    };
    updateTime();
    const interval = setInterval(updateTime, 1000);
    return () => clearInterval(interval);
  }, []);

  const handleRefresh = () => {
    setIsRefreshing(true);
    setTimeout(() => setIsRefreshing(false), 1000);
  };

  const quickStats = [
    { label: 'Active', value: '4', color: 'text-emerald-400' },
    { label: 'Moving', value: '2', color: 'text-cyan-400' },
    { label: 'Idle', value: '1', color: 'text-amber-400' },
    { label: 'Offline', value: '1', color: 'text-slate-400' },
  ];

  return (
    <div className="bg-[#0E1320] border border-white/5 rounded-2xl p-5 shadow-lg">
      <div className="flex flex-col lg:flex-row items-start lg:items-center justify-between gap-4">
        {/* Left: Title & Status */}
        <div className="flex items-center gap-4">
          <div className="flex items-center gap-3">
            <div className="w-12 h-12 rounded-xl bg-gradient-to-br from-[#0250C5] to-[#00E5FF] flex items-center justify-center">
              <Globe className="w-6 h-6 text-white" />
            </div>
            <div>
              <h1 className="text-xl font-bold text-white font-sans">Live Fleet Operations</h1>
              <div className="flex items-center gap-2 mt-1">
                <div className="flex items-center gap-1.5">
                  <div className="w-2 h-2 rounded-full bg-emerald-400 animate-pulse" />
                  <span className="text-xs font-semibold text-emerald-400">Live</span>
                </div>
                <span className="text-xs text-slate-500">•</span>
                <span className="text-xs text-slate-400">{currentTime}</span>
              </div>
            </div>
          </div>
        </div>

        {/* Center: Quick Stats */}
        <div className="flex items-center gap-6">
          {quickStats.map((stat) => (
            <div key={stat.label} className="flex items-center gap-2">
              <span className={`text-lg font-bold ${stat.color}`}>{stat.value}</span>
              <span className="text-xs text-slate-500 uppercase tracking-wider">{stat.label}</span>
            </div>
          ))}
        </div>

        {/* Right: Actions */}
        <div className="flex items-center gap-3">
          {/* Search */}
          <div className="relative">
            <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-500" />
            <input
              type="text"
              placeholder="Search vehicle..."
              className="pl-10 pr-4 py-2.5 bg-white/5 border border-white/10 rounded-xl text-sm text-white placeholder-slate-500 focus:outline-none focus:border-[#0250C5]/50 focus:ring-1 focus:ring-[#0250C5]/50 transition-all w-48"
            />
          </div>

          {/* Center Fleet */}
          <button className="flex items-center gap-2 px-4 py-2.5 bg-white/5 hover:bg-white/10 border border-white/10 rounded-xl transition-all">
            <Crosshair className="w-4 h-4 text-slate-400" />
            <span className="text-sm font-semibold text-slate-300">Center Fleet</span>
          </button>

          {/* Refresh */}
          <button
            onClick={handleRefresh}
            className={`flex items-center gap-2 px-4 py-2.5 bg-white/5 hover:bg-white/10 border border-white/10 rounded-xl transition-all ${
              isRefreshing ? 'animate-spin' : ''
            }`}
          >
            <RefreshCw className="w-4 h-4 text-slate-400" />
            <span className="text-sm font-semibold text-slate-300">Refresh</span>
          </button>
        </div>
      </div>
    </div>
  );
}
