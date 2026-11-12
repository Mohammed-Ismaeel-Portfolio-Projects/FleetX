import { motion } from 'framer-motion';
import { Truck, AlertTriangle, MapPin, CheckCircle } from 'lucide-react';

export default function CommandCenter() {
  return (
    <section id="live-tracking" className="py-24 md:py-28 bg-[#0B0F19] text-white relative w-full overflow-hidden scroll-mt-20">
      {/* Background ambient lighting */}
      <div className="absolute top-1/4 left-1/4 w-[500px] h-[500px] bg-blue-500/5 rounded-full blur-[120px] pointer-events-none" />
      <div className="absolute bottom-1/4 right-1/4 w-[400px] h-[400px] bg-cyan-500/5 rounded-full blur-[100px] pointer-events-none" />

      {/* Grid Border Lines */}
      <div className="absolute top-0 inset-x-0 h-px bg-gradient-to-r from-transparent via-white/5 to-transparent" />
      <div className="absolute bottom-0 inset-x-0 h-px bg-gradient-to-r from-transparent via-white/5 to-transparent" />

      <div className="max-w-7xl mx-auto px-6 md:px-16 w-full relative z-10">
        <div className="grid grid-cols-1 lg:grid-cols-12 gap-12 lg:gap-16 items-center">
          
          {/* Left Column: Text & Metrics */}
          <div className="lg:col-span-5 flex flex-col items-start text-left">
            {/* Top blue line accent */}
            <div className="w-12 h-1 bg-[#0250C5] mb-6 rounded-full" />
            
            <h2 className="text-3xl md:text-4xl font-extrabold text-white tracking-tight mb-4 font-sans">
              Real-Time Fleet Tracking
            </h2>
            <p className="text-slate-400 text-[15px] font-normal leading-relaxed mb-8 max-w-md">
              Monitor your entire fleet with AI-powered visibility and instant updates from a single dashboard.
            </p>

            {/* Live Status Indicator */}
            <div className="flex items-center gap-2 mb-6">
              <div className="w-2 h-2 rounded-full bg-emerald-400 animate-pulse" />
              <span className="text-emerald-400 text-xs font-semibold tracking-wide uppercase">System Online • Live Updates Active</span>
            </div>

            {/* Quick Status Cards Stack */}
            <div className="flex flex-col gap-3 w-full max-w-sm">
              {/* Active Shipments Card */}
              <div className="bg-[#121724]/80 border border-white/5 rounded-2xl p-4 flex items-center justify-between shadow-lg">
                <div className="flex items-center gap-3">
                  <div className="w-9 h-9 rounded-xl bg-blue-500/10 flex items-center justify-center border border-blue-500/20">
                    <Truck className="w-4 h-4 text-blue-400" />
                  </div>
                  <div className="flex flex-col">
                    <span className="text-[9px] font-bold text-slate-500 tracking-wider uppercase font-sans">
                      Active Shipments
                    </span>
                    <span className="text-base font-bold text-white font-sans mt-0.5">
                      1,248 Units
                    </span>
                  </div>
                </div>
                <div className="text-right">
                  <span className="text-emerald-400 text-xs font-semibold">+12%</span>
                </div>
              </div>

              {/* Trucks Online Card */}
              <div className="bg-[#121724]/80 border border-white/5 rounded-2xl p-4 flex items-center justify-between shadow-lg">
                <div className="flex items-center gap-3">
                  <div className="w-9 h-9 rounded-xl bg-emerald-500/10 flex items-center justify-center border border-emerald-500/20">
                    <MapPin className="w-4 h-4 text-emerald-400" />
                  </div>
                  <div className="flex flex-col">
                    <span className="text-[9px] font-bold text-slate-500 tracking-wider uppercase font-sans">
                      Trucks Online
                    </span>
                    <span className="text-base font-bold text-white font-sans mt-0.5">
                      342 Vehicles
                    </span>
                  </div>
                </div>
                <div className="text-right">
                  <span className="text-emerald-400 text-xs font-semibold">98%</span>
                </div>
              </div>

              {/* Delivered Today Card */}
              <div className="bg-[#121724]/80 border border-white/5 rounded-2xl p-4 flex items-center justify-between shadow-lg">
                <div className="flex items-center gap-3">
                  <div className="w-9 h-9 rounded-xl bg-cyan-500/10 flex items-center justify-center border border-cyan-500/20">
                    <CheckCircle className="w-4 h-4 text-cyan-400" />
                  </div>
                  <div className="flex flex-col">
                    <span className="text-[9px] font-bold text-slate-500 tracking-wider uppercase font-sans">
                      Delivered Today
                    </span>
                    <span className="text-base font-bold text-white font-sans mt-0.5">
                      856 Orders
                    </span>
                  </div>
                </div>
                <div className="text-right">
                  <span className="text-emerald-400 text-xs font-semibold">On Track</span>
                </div>
              </div>

              {/* Alerts Requiring Action Card */}
              <div className="bg-[#121724]/80 border border-white/5 rounded-2xl p-4 flex items-center justify-between shadow-lg">
                <div className="flex items-center gap-3">
                  <div className="w-9 h-9 rounded-xl bg-amber-500/10 flex items-center justify-center border border-amber-500/20">
                    <AlertTriangle className="w-4 h-4 text-amber-400" />
                  </div>
                  <div className="flex flex-col">
                    <span className="text-[9px] font-bold text-slate-500 tracking-wider uppercase font-sans">
                      Alerts Requiring Action
                    </span>
                    <span className="text-base font-bold text-red-500 font-sans mt-0.5">
                      3 Critical
                    </span>
                  </div>
                </div>
                <div className="text-right">
                  <span className="text-amber-400 text-xs font-semibold">Action Needed</span>
                </div>
              </div>
            </div>
          </div>

          {/* Right Column: Telemetry Dashboard Mockup */}
          <div className="lg:col-span-7 w-full flex justify-center items-center">
            <div className="w-full max-w-[560px] bg-[#070A13] border border-white/5 rounded-2xl p-5 shadow-2xl relative min-h-[300px] md:min-h-[340px] flex flex-col justify-between overflow-hidden">
              
              {/* Telemetry Visual lines & Nodes */}
              <div className="absolute inset-0 flex items-center justify-center p-8 select-none pointer-events-none">
                <svg className="w-full h-full text-slate-900" viewBox="0 0 320 180" fill="none">
                  {/* Constellation routing nodes */}
                  <g opacity="0.4">
                    <line x1="30" y1="40" x2="130" y2="70" stroke="#1e293b" strokeWidth="1" />
                    <line x1="130" y1="70" x2="200" y2="50" stroke="#1e293b" strokeWidth="1" />
                    <line x1="200" y1="50" x2="280" y2="80" stroke="#1e293b" strokeWidth="1" />

                    <line x1="40" y1="140" x2="150" y2="120" stroke="#1e293b" strokeWidth="1" />
                    <line x1="150" y1="120" x2="260" y2="150" stroke="#1e293b" strokeWidth="1" />
                  </g>

                  {/* Active Transit Path 1 (Top Neon Route) */}
                  <motion.path 
                    d="M 30 40 L 130 70 L 200 50 L 280 80"
                    stroke="#0284c7"
                    strokeWidth="1.5"
                    fill="none"
                    initial={{ pathLength: 0 }}
                    animate={{ pathLength: 1 }}
                    transition={{ duration: 3, repeat: Infinity, ease: "linear" }}
                  />

                  {/* Active Transit Path 2 (Bottom Neon Route) */}
                  <motion.path 
                    d="M 40 140 L 150 120 L 260 150"
                    stroke="#0d9488"
                    strokeWidth="1.5"
                    fill="none"
                    initial={{ pathLength: 0 }}
                    animate={{ pathLength: 1 }}
                    transition={{ duration: 4, repeat: Infinity, ease: "linear", delay: 1 }}
                  />

                  {/* Node Dots */}
                  <circle cx="30" cy="40" r="3" fill="#334155" />
                  <circle cx="280" cy="80" r="3" fill="#334155" />
                  <circle cx="40" cy="140" r="3" fill="#334155" />
                  <circle cx="260" cy="150" r="3" fill="#334155" />

                  {/* Dynamic Glowing Nodes */}
                  <motion.circle 
                    cx="130" 
                    cy="70" 
                    r="4" 
                    fill="#00E5FF" 
                    animate={{ r: [3.5, 5, 3.5] }}
                    transition={{ repeat: Infinity, duration: 2 }}
                  />
                  
                  <motion.circle 
                    cx="200" 
                    cy="50" 
                    r="4" 
                    fill="#00E5FF" 
                    animate={{ r: [3.5, 4.5, 3.5] }}
                    transition={{ repeat: Infinity, duration: 1.5, delay: 0.5 }}
                  />

                  <motion.circle 
                    cx="150" 
                    cy="120" 
                    r="4" 
                    fill="#14b8a6" 
                    animate={{ r: [3.5, 5, 3.5] }}
                    transition={{ repeat: Infinity, duration: 2.2, delay: 0.8 }}
                  />
                </svg>

                {/* Grid dots background pattern overlay inside visualizer */}
                <div 
                  className="absolute inset-0 opacity-[0.03]"
                  style={{
                    backgroundSize: '16px 16px',
                    backgroundImage: 'radial-gradient(white 1px, transparent 1px)'
                  }}
                />
              </div>

              {/* Floating Overlay Tooltip box for node matching screenshot */}
              <motion.div
                initial={{ opacity: 0, y: 5 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ duration: 0.6, delay: 0.8 }}
                className="absolute top-8 left-12 bg-[#0F172A]/90 backdrop-blur-md rounded-lg border border-slate-700/50 p-3 shadow-xl text-left min-w-[120px]"
              >
                <div className="text-[10px] font-bold text-slate-300 font-mono">VEHICLE_023</div>
                <div className="text-[9px] text-slate-400 font-medium mt-1">Status: <span className="text-cyan-400 font-semibold">En Route</span></div>
                <div className="text-[9px] text-slate-400 font-medium">ETA: <span className="text-white font-semibold">14 mins</span></div>
              </motion.div>

              <div className="flex-grow" />

              {/* Bottom Details Telemetry matching the reference screenshot */}
              <div className="flex justify-end mt-auto z-10">
                <div className="bg-[#0b0f19] border border-white/5 rounded-xl px-4 py-2 flex items-center gap-6 shadow-md">
                  <div className="flex flex-col text-left">
                    <span className="text-[7.5px] font-bold text-slate-500 tracking-wider uppercase font-sans">
                      network load
                    </span>
                    <span className="text-[11px] font-bold text-emerald-400 uppercase font-mono mt-0.5">
                      optimal
                    </span>
                  </div>
                  <div className="h-6 w-px bg-slate-800" />
                  <div className="flex flex-col text-left">
                    <span className="text-[7.5px] font-bold text-slate-500 tracking-wider uppercase font-sans">
                      latency
                    </span>
                    <span className="text-[11px] font-bold text-white uppercase font-mono mt-0.5">
                      12ms
                    </span>
                  </div>
                </div>
              </div>

            </div>
          </div>

        </div>
      </div>
    </section>
  );
}
