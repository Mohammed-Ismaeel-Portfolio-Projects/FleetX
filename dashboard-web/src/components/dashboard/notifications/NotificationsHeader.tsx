import { motion } from 'framer-motion';
import { RefreshCw, Download } from 'lucide-react';

export default function NotificationsHeader() {
  return (
    <motion.div
      initial={{ opacity: 0, y: -10 }}
      animate={{ opacity: 1, y: 0 }}
      transition={{ duration: 0.2 }}
      className="rounded-xl border border-white/5 bg-[#0E1320] p-6 shadow-sm"
    >
      <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
        {/* Left Side - Title and Info */}
        <div className="flex flex-col gap-4 flex-1">
          {/* Title Section */}
          <div>
            <h1 className="text-xl font-semibold tracking-tight text-white font-sans">
              System Notifications Center
            </h1>
            <p className="text-xs text-slate-500 font-normal font-sans mt-1">
              Real-time global alert intelligence, telemetry monitoring, and predictive warnings.
            </p>
          </div>

          {/* Metadata Row */}
          <div className="flex flex-wrap items-center gap-6">
            <div className="flex items-center gap-2">
              <span className="text-[10px] font-semibold uppercase tracking-wider text-slate-500">
                Active Alerts
              </span>
              <span className="text-sm font-mono font-semibold text-white">
                128
              </span>
            </div>
            <div className="h-4 w-px bg-white/10" />
            <div className="flex items-center gap-2">
              <span className="text-[10px] font-semibold uppercase tracking-wider text-slate-500">
                Critical
              </span>
              <span className="text-sm font-mono font-semibold text-red-400">
                6
              </span>
            </div>
            <div className="h-4 w-px bg-white/10" />
            <div className="flex items-center gap-2">
              <span className="text-[10px] font-semibold uppercase tracking-wider text-slate-500">
                Resolved Today
              </span>
              <span className="text-sm font-mono font-semibold text-green-400">
                83
              </span>
            </div>
            <div className="h-4 w-px bg-white/10" />
            <div className="flex items-center gap-2">
              <span className="text-[10px] font-semibold uppercase tracking-wider text-slate-500">
                Updated
              </span>
              <span className="text-sm font-mono text-slate-400">
                12 sec ago
              </span>
            </div>
          </div>
        </div>

        {/* Right Side - Enterprise Controls */}
        <div className="flex flex-col gap-3 lg:items-end">
          {/* Live Indicator */}
          <div className="flex items-center gap-3 rounded-lg border border-green-500/20 bg-green-500/5 px-4 py-2">
            <div className="flex items-center gap-2">
              <div className="h-2 w-2 rounded-full bg-green-500 animate-pulse" />
              <span className="text-xs font-semibold text-green-400 uppercase tracking-wider">
                LIVE
              </span>
            </div>
            <div className="h-4 w-px bg-white/10" />
            <div className="flex items-center gap-2">
              <span className="text-[10px] text-slate-400 uppercase tracking-wider">
                Streaming
              </span>
              <span className="text-[10px] font-mono text-slate-500">
                180 ms
              </span>
            </div>
          </div>

          {/* Action Buttons */}
          <div className="flex items-center gap-2">
            <button className="flex items-center gap-2 rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-xs font-medium text-slate-300 hover:bg-white/10 transition-all hover:scale-[1.01]">
              <RefreshCw className="h-4 w-4" />
              <span>Refresh</span>
            </button>
            <button className="flex items-center gap-2 rounded-lg border border-cyan-500/30 bg-cyan-500/10 px-3 py-2 text-xs font-medium text-cyan-400 hover:bg-cyan-500/20 transition-all hover:scale-[1.01]">
              <Download className="h-4 w-4" />
              <span>Export</span>
            </button>
          </div>
        </div>
      </div>
    </motion.div>
  );
}
