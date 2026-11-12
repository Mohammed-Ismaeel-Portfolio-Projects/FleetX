import { motion } from 'framer-motion';
import { Map, Sparkles } from 'lucide-react';
import heroImg from '../../../assets/images/hero.png';

export default function Hero() {
  const containerVariants = {
    hidden: { opacity: 0 },
    visible: {
      opacity: 1,
      transition: {
        staggerChildren: 0.15,
        delayChildren: 0.1,
      },
    },
  };

  const itemVariants = {
    hidden: { opacity: 0, y: 20 },
    visible: {
      opacity: 1,
      y: 0,
      transition: { duration: 0.7, ease: [0.16, 1, 0.3, 1] as const },
    },
  };

  return (
    <section 
      style={{
        backgroundImage: `url(${heroImg})`,
        backgroundSize: 'cover',
        backgroundPosition: 'center',
        backgroundRepeat: 'no-repeat'
      }}
      className="relative min-h-screen pt-32 pb-24 md:py-36 overflow-hidden flex items-center justify-center"
    >
      {/* Background Image dark overlay for legibility */}
      <div className="absolute inset-0 bg-gradient-to-br from-[#121620]/97 via-[#121620]/84 to-[#121620]/97 opacity-95" />

      {/* Background patterns */}
      <div className="absolute inset-0 bg-dot-pattern-hero opacity-60" />
      <div className="absolute top-1/4 left-1/3 w-[500px] h-[500px] bg-blue-500/10 rounded-full blur-[120px] pointer-events-none" />
      <div className="absolute bottom-1/4 right-1/4 w-[400px] h-[400px] bg-cyan-500/10 rounded-full blur-[100px] pointer-events-none" />

      {/* Grid Border Lines */}
      <div className="absolute top-0 inset-x-0 h-px bg-gradient-to-r from-transparent via-white/10 to-transparent" />
      <div className="absolute bottom-0 inset-x-0 h-px bg-gradient-to-r from-transparent via-white/10 to-transparent" />

      <div className="max-w-7xl mx-auto px-6 md:px-16 w-full relative z-10">
        <div className="grid grid-cols-1 lg:grid-cols-12 gap-12 lg:gap-8 items-center">
          
          {/* Copywriting Left Column */}
          <motion.div 
            className="lg:col-span-6 flex flex-col items-start text-left"
            variants={containerVariants}
            initial="hidden"
            animate="visible"
          >
            {/* New Feature Badge */}
            <motion.div 
              variants={itemVariants}
              className="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full border border-white/10 bg-white/5 text-[11px] font-semibold uppercase tracking-wider text-slate-300 mb-6 select-none"
            >
              <Sparkles className="w-3.5 h-3.5 text-cyan-400" />
              <span>Enterprise Logistics Platform</span>
            </motion.div>

            {/* Headline */}
            <motion.h1 
              variants={itemVariants}
              className="text-4xl md:text-5xl lg:text-[54px] font-extrabold tracking-tight text-white font-sans leading-[1.1] mb-6"
            >
              Enterprise Fleet<br />
              <span className="bg-clip-text text-transparent bg-gradient-to-r from-cyan-400 to-blue-500">
                Management Platform
              </span>
            </motion.h1>

            {/* Supporting Description */}
            <motion.p 
              variants={itemVariants}
              className="text-slate-400 text-[16px] md:text-[17px] max-w-xl font-normal leading-relaxed mb-8"
            >
              Real-time tracking, AI optimization, and complete shipment visibility for logistics operations.
            </motion.p>

            {/* CTA Buttons */}
            <motion.div 
              variants={itemVariants}
              className="flex flex-col sm:flex-row gap-4 w-full sm:w-auto mb-6"
            >
              <a
                href="#contact"
                className="inline-flex items-center justify-center px-7 py-3.5 rounded-full text-sm font-bold text-white bg-[#0250C5] hover:bg-[#0244a5] transition-all duration-200 shadow-lg shadow-blue-900/40 active:scale-98 cursor-pointer select-none text-center"
              >
                Request Demo
              </a>
              
              <a
                href="#features"
                className="inline-flex items-center justify-center px-7 py-3.5 rounded-full text-sm font-bold text-slate-300 hover:text-white bg-white/5 hover:bg-white/10 transition-all duration-200 border border-white/10 active:scale-98 gap-2 cursor-pointer select-none text-center"
              >
                <Map className="w-4 h-4 text-slate-400" />
                <span>Explore Platform</span>
              </a>
            </motion.div>

            {/* Trust Signal */}
            <motion.div
              variants={itemVariants}
              className="flex items-center gap-2 text-slate-500 text-xs font-medium"
            >
              <div className="flex items-center gap-1">
                <div className="w-2 h-2 rounded-full bg-green-500" />
                <span>Enterprise-grade security</span>
              </div>
              <span className="text-slate-600">•</span>
              <span>99.9% uptime</span>
              <span className="text-slate-600">•</span>
              <span>Real-time tracking</span>
            </motion.div>
          </motion.div>

          {/* Interactive Mockup Dashboard Right Column */}
          <motion.div 
            className="lg:col-span-6 w-full flex justify-center items-center"
            initial={{ opacity: 0, scale: 0.96, y: 15 }}
            animate={{ opacity: 1, scale: 1, y: 0 }}
            transition={{ duration: 0.8, ease: [0.16, 1, 0.3, 1] as const, delay: 0.25 }}
          >
            {/* Soft bouncing animation to make dashboard feel alive */}
            <motion.div
              animate={{ y: [0, -6, 0] }}
              transition={{ duration: 5, repeat: Infinity, ease: "easeInOut" }}
              className="w-full max-w-[480px] bg-[#1b253b] rounded-[24px] border border-white/5 shadow-2xl p-6 relative overflow-hidden"
            >
              {/* Mockup Header bar */}
              <div className="flex items-center justify-between pb-5 border-b border-white/5 mb-5">
                <div className="flex items-center gap-2">
                  <span className="w-2.5 h-2.5 rounded-full bg-[#EF4444]" />
                  <span className="w-2.5 h-2.5 rounded-full bg-[#F59E0B]" />
                  <span className="w-2.5 h-2.5 rounded-full bg-[#3B82F6]" />
                </div>
                <span className="text-[10px] font-semibold text-slate-500 tracking-wider font-mono uppercase">
                  en-route: system_active
                </span>
              </div>

              {/* Stats Cards Grid */}
              <div className="grid grid-cols-2 gap-4 mb-4">
                {/* Efficiency card */}
                <div className="bg-[#232f4a] border border-white/5 rounded-xl p-5 flex flex-col items-start text-left">
                  <span className="text-[9px] font-bold text-slate-400 tracking-widest uppercase font-sans">
                    efficiency
                  </span>
                  <span className="text-3xl font-extrabold mt-1.5 text-cyan-400 font-sans tracking-tight">
                    +28.4%
                  </span>
                </div>

                {/* Active trucks card */}
                <div className="bg-[#232f4a] border border-white/5 rounded-xl p-5 flex flex-col items-start text-left">
                  <span className="text-[9px] font-bold text-slate-400 tracking-widest uppercase font-sans">
                    active trucks
                  </span>
                  <span className="text-3xl font-extrabold mt-1.5 text-white font-sans tracking-tight">
                    142
                  </span>
                </div>
              </div>

              {/* Fleet Route Map card */}
              <div className="bg-[#232f4a] border border-white/5 rounded-xl p-5 flex flex-col justify-between h-[135px] text-left">
                <span className="text-[9px] font-bold text-slate-400 tracking-widest uppercase font-sans block">
                  fleet route map
                </span>

                {/* Path line animated progress graphic in middle */}
                <div className="flex-grow flex items-center justify-start my-1 px-1">
                  <div className="w-full h-1 bg-slate-700/40 rounded-full relative overflow-hidden">
                    <motion.div 
                      className="absolute top-0 left-0 bottom-0 bg-cyan-400 rounded-full"
                      initial={{ width: "0%" }}
                      animate={{ width: "55%" }}
                      transition={{ duration: 1.8, ease: "easeOut" }}
                    />
                  </div>
                </div>

                <div className="flex justify-between items-center text-[10px]">
                  {/* Cyan status bar on the left */}
                  <div className="w-32 h-[3px] bg-cyan-400 rounded-full" />
                  
                  {/* Status text on the right */}
                  <span className="text-slate-500 font-mono tracking-tight font-semibold uppercase">
                    truck_098 • 4.2mi to origin
                  </span>
                </div>
              </div>
            </motion.div>
          </motion.div>

        </div>
      </div>
    </section>
  );
}
