import { useState } from 'react';
import { motion } from 'framer-motion';
import { Play, Pause, SkipBack, SkipForward, RotateCcw, Calendar, Clock } from 'lucide-react';

export default function ReplayWidget() {
  const [isPlaying, setIsPlaying] = useState(false);
  const [currentTime, setCurrentTime] = useState('12:00');
  const [progress, setProgress] = useState(0);

  const handlePlay = () => {
    setIsPlaying(!isPlaying);
  };

  const handleReset = () => {
    setIsPlaying(false);
    setCurrentTime('12:00');
    setProgress(0);
  };

  return (
    <div className="bg-[#0E1320] border border-white/5 rounded-2xl shadow-lg">
      {/* Header */}
      <div className="p-5 border-b border-white/5">
        <div className="flex items-center justify-between">
          <h2 className="text-sm font-bold text-white font-sans flex items-center gap-2">
            <RotateCcw className="w-4 h-4 text-[#00E5FF]" />
            Fleet Replay
          </h2>
          <span className="text-xs font-semibold text-slate-400">Last 24 Hours</span>
        </div>
      </div>

      {/* Content */}
      <div className="p-5">
        {/* Date/Time Selector */}
        <div className="flex items-center gap-4 mb-6">
          <div className="flex-1">
            <label className="flex items-center gap-2 text-[10px] font-semibold text-slate-400 uppercase tracking-wider mb-2">
              <Calendar className="w-3.5 h-3.5" />
              Date
            </label>
            <input
              type="date"
              defaultValue="2026-07-17"
              className="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white focus:outline-none focus:border-[#0250C5]/50 transition-all"
            />
          </div>
          <div className="flex-1">
            <label className="flex items-center gap-2 text-[10px] font-semibold text-slate-400 uppercase tracking-wider mb-2">
              <Clock className="w-3.5 h-3.5" />
              Time Range
            </label>
            <select className="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white focus:outline-none focus:border-[#0250C5]/50 transition-all">
              <option value="1h">Last 1 Hour</option>
              <option value="6h">Last 6 Hours</option>
              <option value="12h">Last 12 Hours</option>
              <option value="24h" selected>Last 24 Hours</option>
              <option value="custom">Custom Range</option>
            </select>
          </div>
        </div>

        {/* Playback Controls */}
        <div className="bg-white/[0.02] border border-white/5 rounded-xl p-4 mb-6">
          <div className="flex items-center justify-between mb-4">
            <div className="flex items-center gap-4">
              <button
                onClick={handleReset}
                className="p-2 bg-white/5 hover:bg-white/10 border border-white/10 rounded-lg transition-colors"
                title="Reset"
              >
                <SkipBack className="w-4 h-4 text-slate-400" />
              </button>
              <button
                onClick={handlePlay}
                className={`p-3 rounded-xl transition-all ${
                  isPlaying 
                    ? 'bg-amber-500/10 border-amber-500/20 text-amber-400' 
                    : 'bg-[#0250C5] hover:bg-[#0244a5] text-white'
                }`}
                title={isPlaying ? 'Pause' : 'Play'}
              >
                {isPlaying ? <Pause className="w-5 h-5" /> : <Play className="w-5 h-5" />}
              </button>
              <button
                className="p-2 bg-white/5 hover:bg-white/10 border border-white/10 rounded-lg transition-colors"
                title="Skip Forward"
              >
                <SkipForward className="w-4 h-4 text-slate-400" />
              </button>
            </div>

            <div className="text-right">
              <p className="text-[10px] text-slate-500 uppercase tracking-wider mb-1">Current Time</p>
              <p className="text-lg font-bold text-white font-mono">{currentTime}</p>
            </div>
          </div>

          {/* Progress Bar */}
          <div className="mb-4">
            <div className="flex items-center justify-between mb-2">
              <span className="text-xs text-slate-400">Replay Progress</span>
              <span className="text-xs font-semibold text-white">{progress.toFixed(0)}%</span>
            </div>
            <div className="w-full h-2 bg-white/5 rounded-full overflow-hidden cursor-pointer">
              <motion.div
                className="h-full bg-gradient-to-r from-[#0250C5] to-[#00E5FF] rounded-full"
                animate={{ width: isPlaying ? `${progress + 1}%` : `${progress}%` }}
                transition={{ duration: 0.1 }}
              />
            </div>
          </div>

          {/* Speed Control */}
          <div className="flex items-center justify-between">
            <span className="text-xs text-slate-400">Replay Speed</span>
            <div className="flex gap-2">
              {['1x', '2x', '4x', '8x'].map((speed) => (
                <button
                  key={speed}
                  className={`px-3 py-1 text-[10px] font-bold rounded-lg transition-all ${
                    speed === '1x' 
                      ? 'bg-[#0250C5] text-white' 
                      : 'bg-white/5 text-slate-400 hover:bg-white/10'
                  }`}
                >
                  {speed}
                </button>
              ))}
            </div>
          </div>
        </div>

        {/* Vehicle Selection */}
        <div className="mb-4">
          <label className="text-[10px] font-semibold text-slate-400 uppercase tracking-wider mb-2 block">
            Select Vehicle
          </label>
          <select className="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white focus:outline-none focus:border-[#0250C5]/50 transition-all">
            <option value="all">All Vehicles</option>
            <option value="VT-402">VT-402 - Ahmed K.</option>
            <option value="VT-119">VT-119 - Sarah J.</option>
            <option value="VT-994">VT-994 - Bill T.</option>
            <option value="VT-881">VT-881 - Mark S.</option>
          </select>
        </div>

        {/* Replay Stats */}
        <div className="grid grid-cols-3 gap-3">
          <div className="bg-white/[0.02] border border-white/5 rounded-lg p-3 text-center">
            <p className="text-[10px] text-slate-500 uppercase tracking-wider mb-1">Distance</p>
            <p className="text-lg font-bold text-white font-mono">245 mi</p>
          </div>
          <div className="bg-white/[0.02] border border-white/5 rounded-lg p-3 text-center">
            <p className="text-[10px] text-slate-500 uppercase tracking-wider mb-1">Duration</p>
            <p className="text-lg font-bold text-white font-mono">4h 32m</p>
          </div>
          <div className="bg-white/[0.02] border border-white/5 rounded-lg p-3 text-center">
            <p className="text-[10px] text-slate-500 uppercase tracking-wider mb-1">Stops</p>
            <p className="text-lg font-bold text-white font-mono">3</p>
          </div>
        </div>
      </div>

      {/* Footer */}
      <div className="p-4 border-t border-white/5">
        <button className="w-full px-4 py-2 bg-[#0250C5] hover:bg-[#0244a5] text-white text-xs font-bold rounded-lg transition-colors">
          Start Replay
        </button>
      </div>
    </div>
  );
}
