import { motion } from 'framer-motion';
import { 
  MapPin, 
  Route, 
  Zap, 
  Smartphone, 
  Clock, 
  BarChart3, 
  ArrowRight,
  Package,
  RefreshCw
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';

interface FeatureItem {
  icon: LucideIcon;
  title: string;
  description: string;
  category: string;
}

const featuresSuiteData: FeatureItem[] = [
  // Fleet Management
  {
    icon: Zap,
    title: 'Smart Dispatching',
    description: 'AI-driven assignment of drivers and trucks based on load type and proximity.',
    category: 'Fleet Management',
  },
  {
    icon: Smartphone,
    title: 'Driver Mobile App',
    description: 'Unified driver experience with digital navigation and delivery confirmation.',
    category: 'Fleet Management',
  },
  
  // Real-Time Tracking
  {
    icon: MapPin,
    title: 'Live GPS Tracking',
    description: 'Real-time satellite telemetry for complete shipment visibility.',
    category: 'Real-Time Tracking',
  },
  {
    icon: Clock,
    title: 'Predictive ETA',
    description: 'ML-powered arrival time forecasting for accurate customer updates.',
    category: 'Real-Time Tracking',
  },
  
  // AI Intelligence
  {
    icon: Route,
    title: 'AI Route Optimization',
    description: 'Reduces delivery time and fuel costs with intelligent path planning.',
    category: 'AI Intelligence',
  },
  {
    icon: RefreshCw,
    title: 'Dynamic Re-routing',
    description: 'Automatic route adjustments for accidents, closures, and traffic.',
    category: 'AI Intelligence',
  },
  
  // Shipment Control
  {
    icon: Package,
    title: 'Shipment Lifecycle',
    description: 'End-to-end order management from creation to delivery confirmation.',
    category: 'Shipment Control',
  },
  {
    icon: BarChart3,
    title: 'Analytics Dashboard',
    description: 'Real-time fleet performance metrics and operational insights.',
    category: 'Shipment Control',
  },
];

export default function FeatureSuite() {
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
    <section id="features" className="py-24 md:py-28 bg-[#f8fafc] border-t border-slate-100 w-full overflow-hidden scroll-mt-20">
      <div className="max-w-7xl mx-auto px-6 md:px-16 w-full">
        
        {/* Header containing title/desc on left and link on right */}
        <div className="flex flex-col md:flex-row md:items-end justify-between gap-6 mb-16">
          <div className="text-left max-w-xl">
            <h2 className="text-3xl md:text-4xl font-extrabold text-slate-900 font-sans tracking-tight mb-4">
              Enterprise Features
            </h2>
            <p className="text-slate-500 text-[15px] font-normal leading-relaxed">
              Core logistics capabilities organized for maximum operational efficiency.
            </p>
          </div>
          
          <div className="flex justify-start">
            <a 
              href="#features" 
              className="inline-flex items-center gap-2 text-[#0250C5] hover:text-[#0244a5] font-bold text-[12px] uppercase tracking-wider transition-colors select-none group"
            >
              <span>Explore All Features</span>
              <ArrowRight className="w-4 h-4 transition-transform duration-200 group-hover:translate-x-1" />
            </a>
          </div>
        </div>

        {/* Feature Grid by Category */}
        <motion.div 
          className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 w-full"
          variants={containerVariants}
          initial="hidden"
          whileInView="visible"
          viewport={{ once: true, margin: "-50px" }}
        >
          {featuresSuiteData.map((item) => (
            <motion.div
              key={item.title}
              variants={cardVariants}
              whileHover={{ y: -6, boxShadow: "0 15px 30px -10px rgba(15, 23, 42, 0.05)", borderColor: "rgba(2, 80, 197, 0.1)" }}
              className="bg-white border border-slate-200 rounded-2xl p-6 flex items-start gap-4 transition-all duration-300 shadow-sm shadow-slate-100/50 cursor-pointer text-left group"
            >
              {/* Left-aligned clean rounded icon */}
              <div className="w-10 h-10 rounded-xl bg-blue-50/50 border border-blue-100/40 flex items-center justify-center flex-shrink-0 transition-colors duration-300 group-hover:bg-[#0250C5]/10 group-hover:border-[#0250C5]/20">
                <item.icon className="w-5 h-5 text-[#0250C5]" />
              </div>
              
              {/* Right-aligned text stack */}
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
