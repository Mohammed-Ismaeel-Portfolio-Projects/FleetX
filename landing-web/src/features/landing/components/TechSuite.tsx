import { motion } from 'framer-motion';
import { Check } from 'lucide-react';
import logoImg from '../../../assets/logo/logo.jpeg';

export default function TechSuite() {
  const bulletItems = [
    'MQTT Protocol for sub-50ms latency',
    'Neural Network Route Prediction',
    'Self-Healing Infrastructure',
  ];

  return (
    <section id="ai-tech" className="py-24 md:py-28 bg-[#f8fafc] w-full overflow-hidden relative border-t border-slate-100 scroll-mt-20">
      {/* Decorative ambient lighting */}
      <div className="absolute top-1/4 right-0 w-[300px] h-[300px] bg-blue-100/10 rounded-full blur-[80px] pointer-events-none" />

      <div className="max-w-7xl mx-auto px-6 md:px-16 w-full relative z-10">
        <div className="grid grid-cols-1 lg:grid-cols-12 gap-12 lg:gap-16 items-center">
          
          {/* Left Column: Tech Copywriting */}
          <div className="lg:col-span-6 flex flex-col items-start text-left">
            <h2 className="text-3xl md:text-4xl font-extrabold text-slate-900 tracking-tight leading-tight mb-5 font-sans">
              Enterprise-Grade<br />AI Technology
            </h2>
            <p className="text-slate-500 text-[15px] font-normal leading-relaxed mb-8 max-w-xl">
              Proprietary AI engine uses real-time protocols to synchronize data across fleet operations. Zero-lag communication between vehicles and central hub.
            </p>

            {/* Bullets grid */}
            <div className="flex flex-col gap-4 w-full">
              {bulletItems.map((bullet) => (
                <div key={bullet} className="flex items-center gap-3.5">
                  <div className="w-5 h-5 rounded-full bg-[#0250C5] text-white flex items-center justify-center flex-shrink-0 shadow-sm">
                    <Check className="w-3.5 h-3.5 stroke-[3.5]" />
                  </div>
                  <span className="text-slate-700 text-sm font-semibold tracking-tight font-sans">
                    {bullet}
                  </span>
                </div>
              ))}
            </div>
          </div>

          {/* Right Column: Premium Concentric Orbit Graphic */}
          <div className="lg:col-span-6 w-full flex justify-center items-center">
            {/* Concentric Circles Frame */}
            <div className="relative w-[360px] h-[360px] md:w-[420px] md:h-[420px] flex items-center justify-center select-none pointer-events-none">
              
              {/* Outer Glow Ring */}
              <div className="w-[300px] h-[300px] md:w-[360px] md:h-[360px] bg-gradient-to-tr from-blue-500/5 to-cyan-500/5 rounded-full absolute flex items-center justify-center shadow-inner" />

              {/* Concentric Dashed Path Ring 2 (Outer) */}
              <div className="w-[280px] h-[280px] md:w-[320px] md:h-[320px] border border-dashed border-blue-200/50 rounded-full absolute flex items-center justify-center" />

              {/* Concentric Dashed Path Ring 1 (Inner) */}
              <div className="w-[180px] h-[180px] md:w-[220px] md:h-[220px] border border-dashed border-blue-200/40 rounded-full absolute flex items-center justify-center" />

              {/* Orbiting Node Container 1 (Inner Circle - Clockwise) */}
              <motion.div 
                className="w-[180px] h-[180px] md:w-[220px] md:h-[220px] absolute"
                animate={{ rotate: 360 }}
                transition={{ repeat: Infinity, duration: 16, ease: "linear" }}
              >
                {/* Glowing inner orb */}
                <span className="w-3 h-3 rounded-full bg-[#0250C5] absolute top-0 left-1/2 -translate-x-1/2 shadow-lg shadow-blue-500/50 border border-white/80" />
              </motion.div>

              {/* Orbiting Node Container 2 (Outer Circle - Counter-Clockwise) */}
              <motion.div 
                className="w-[280px] h-[280px] md:w-[320px] md:h-[320px] absolute"
                animate={{ rotate: -360 }}
                transition={{ repeat: Infinity, duration: 24, ease: "linear" }}
              >
                {/* Glowing outer orbs */}
                <span className="w-2.5 h-2.5 rounded-full bg-[#00E5FF] absolute bottom-0 left-1/2 -translate-x-1/2 shadow-lg shadow-cyan-400/50 border border-white/80" />
                <span className="w-2 h-2 rounded-full bg-blue-400 absolute top-1/4 left-0 shadow-md border border-white/80" />
              </motion.div>

              {/* Central White Tech Core Circular Node */}
              <div className="w-28 h-28 md:w-32 md:h-32 rounded-full border border-slate-100 shadow-xl bg-white flex flex-col items-center justify-center p-3 relative z-10">
                <img 
                  src={logoImg} 
                  alt="FleetX Logo" 
                  className="w-10 h-10 md:w-11 md:h-11 rounded-xl object-cover border border-slate-100 mb-2 shadow-xs"
                />
                <span className="text-[10px] md:text-[11px] font-bold text-slate-800 tracking-tight font-sans">
                  FleetX
                </span>
                <span className="text-[8px] md:text-[8.5px] text-slate-400 font-bold uppercase tracking-wider font-sans mt-0.5">
                  Tech Core
                </span>
              </div>

            </div>
          </div>

        </div>
      </div>
    </section>
  );
}
