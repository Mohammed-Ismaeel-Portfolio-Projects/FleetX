import { memo, useEffect, useState } from 'react';

interface LiveIndicatorProps {
  isLive: boolean;
  lastUpdate: Date | null;
}

function LiveIndicatorComponent({ isLive, lastUpdate }: LiveIndicatorProps) {
  const [timeSinceUpdate, setTimeSinceUpdate] = useState<string>('...');

  useEffect(() => {
    const updateTime = (): string => {
      if (!lastUpdate) {
        return '...';
      }
      const now = Date.now();
      const diff = now - lastUpdate.getTime();
      const seconds = Math.floor(diff / 1000);
      
      if (seconds < 60) return `${seconds} sec ago`;
      const minutes = Math.floor(seconds / 60);
      return `${minutes} min ago`;
    };

    setTimeSinceUpdate(updateTime());
    
    // Update every second
    const interval = setInterval(() => {
      setTimeSinceUpdate(updateTime());
    }, 1000);

    return () => clearInterval(interval);
  }, [lastUpdate]);

  return (
    <div className="flex items-center gap-2 rounded-lg bg-[#0A0E17] border border-cyan-500/20 px-3 py-1.5">
      <div className="flex items-center gap-1.5">
        <div 
          className={`h-2 w-2 rounded-full ${
            isLive 
              ? 'bg-green-400 animate-pulse' 
              : 'bg-slate-500'
          }`}
        />
        <span className="text-xs font-semibold text-cyan-400">
          {isLive ? 'LIVE' : 'OFFLINE'}
        </span>
      </div>
      <div className="h-3 w-px bg-white/10" />
      <div className="text-xs text-slate-400">
        Updating every 5 sec
      </div>
      <div className="h-3 w-px bg-white/10" />
      <div className="text-xs text-slate-500">
        Last update {timeSinceUpdate}
      </div>
    </div>
  );
}

export default memo(LiveIndicatorComponent);
