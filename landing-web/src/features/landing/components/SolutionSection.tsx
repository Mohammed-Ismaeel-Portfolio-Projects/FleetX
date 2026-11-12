import { motion } from 'framer-motion';
import { 
  CheckCircle, 
  MapPin, 
  Clock, 
  Smartphone, 
  Layers, 
  Zap 
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';

interface SolutionItem {
  icon: LucideIcon;
  title: string;
  description: string;
}

const solutionsData: SolutionItem[] = [
  {
    icon: CheckCircle,
    title: 'AI-Powered Dispatching',
    description: 'Automated driver and truck assignment eliminates human errors and optimizes fleet utilization.',
  },
  {
    icon: MapPin,
    title: 'Real-Time GPS Tracking',
    description: 'Live satellite telemetry provides complete shipment visibility from pickup to delivery.',
  },
  {
    icon: Clock,
    title: 'Predictive ETA Engine',
    description: 'Machine learning models forecast arrival times accounting for traffic, weather, and border delays.',
  },
  {
    icon: Smartphone,
    title: 'Unified Driver App',
    description: 'Centralized mobile platform ensures seamless communication and digital proof of delivery.',
  },
  {
    icon: Layers,
    title: 'Centralized Operations Hub',
    description: 'Single dashboard for dispatchers, clients, and admins eliminates information silos.',
  },
  {
    icon: Zap,
    title: 'Intelligent Route Optimization',
    description: 'AI algorithms reduce fuel consumption and transit time by finding optimal paths.',
  },
];

export default function SolutionSection() {
  const containerVariants = {
    hidden: { opacity: 0 },
    visible: {
      opacity: 1,
      transition: {
        staggerChildren: 0.08,
      },
    },
  };

  const cardVariants = {
    hidden: { opacity: 0, y: 15 },
    visible: {
      opacity: 1,
      y: 0,
      transition: { duration: 0.5, ease: [0.16, 1, 0.3, 1] as const },
    },
  };

  return (
    <section className="py-24 md:py-28 bg-white border-t border-slate-100 w-full overflow-hidden">
      {/* Decorative ambient elements */}
      <div className="absolute top-0 right-0 w-[400px] h-[400px] bg-blue-50/20 rounded-full blur-[100px] pointer-events-none" />
      <div className="absolute bottom-0 left-0 w-[400px] h-[400px] bg-cyan-50/20 rounded-full blur-[100px] pointer-events-none" />

      <div className="max-w-7xl mx-auto px-6 md:px-16 w-full relative z-10">
        
        {/* Header */}
        <div className="text-center mb-16">
          <h2 className="text-3xl md:text-4xl font-extrabold text-slate-900 font-sans tracking-tight mb-4">
            The Intelligent Logistics Solution
          </h2>
          <p className="text-slate-500 text-[15px] font-normal leading-relaxed max-w-2xl mx-auto">
            AI-powered platform that transforms chaos into synchronized, predictable operations.
          </p>
        </div>

        {/* Solutions Grid */}
        <motion.div 
          className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 w-full"
          variants={containerVariants}
          initial="hidden"
          whileInView="visible"
          viewport={{ once: true, margin: "-100px" }}
        >
          {solutionsData.map((item) => (
            <motion.div
              key={item.title}
              variants={cardVariants}
              whileHover={{ y: -6, boxShadow: "0 15px 30px -10px rgba(15, 23, 42, 0.05)" }}
              className="bg-white border border-slate-200 rounded-2xl p-6 flex items-start gap-4 transition-all duration-300 shadow-sm shadow-slate-100/50 cursor-pointer text-left group"
            >
              {/* Icon */}
              <div className="w-10 h-10 rounded-xl bg-blue-50/50 border border-blue-100/40 flex items-center justify-center flex-shrink-0 transition-colors duration-300 group-hover:bg-blue-100/70 group-hover:border-blue-200/50">
                <item.icon className="w-5 h-5 text-[#0250C5]" />
              </div>
              
              {/* Text */}
              <div className="flex flex-col">
                <h3 className="font-bold text-slate-800 text-[15px] mb-1.5 tracking-tight font-sans">
                  {item.title}
                </h3>
                <p className="text-slate-400 text-[12px] font-normal leading-relaxed font-sans">
                  {item.description}
                </p>
              </div>
            </motion.div>
          ))}
        </motion.div>

      </div>
    </section>
  );
}
