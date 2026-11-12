import { motion } from 'framer-motion';
import { 
  AlertTriangle, 
  Clock, 
  Users, 
  MapPin, 
  MessageSquare, 
  DollarSign 
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';

interface ProblemItem {
  icon: LucideIcon;
  title: string;
  description: string;
}

const problemsData: ProblemItem[] = [
  {
    icon: AlertTriangle,
    title: 'Manual Dispatching Errors',
    description: 'Human mistakes in assignment lead to delays, wrong routes, and missed deliveries.',
  },
  {
    icon: MapPin,
    title: 'No Real-Time Visibility',
    description: 'Blind spots in shipment tracking create uncertainty and customer frustration.',
  },
  {
    icon: Clock,
    title: 'Border & Customs Delays',
    description: 'Unpredictable wait times disrupt schedules and increase operational costs.',
  },
  {
    icon: Users,
    title: 'Poor Driver Communication',
    description: 'Disconnected teams cause coordination failures and inefficient fleet operations.',
  },
  {
    icon: MessageSquare,
    title: 'Fragmented Communication',
    description: 'Multiple systems and manual processes create information silos.',
  },
  {
    icon: DollarSign,
    title: 'High Operational Costs',
    description: 'Inefficient routing and delays waste fuel, time, and revenue.',
  },
];

export default function ProblemSection() {
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
    <section className="py-24 md:py-28 bg-[#f8fafc] border-t border-slate-100 w-full overflow-hidden">
      {/* Decorative ambient elements */}
      <div className="absolute top-0 right-0 w-[400px] h-[400px] bg-red-50/20 rounded-full blur-[100px] pointer-events-none" />
      <div className="absolute bottom-0 left-0 w-[400px] h-[400px] bg-orange-50/20 rounded-full blur-[100px] pointer-events-none" />

      <div className="max-w-7xl mx-auto px-6 md:px-16 w-full relative z-10">
        
        {/* Header */}
        <div className="text-center mb-16">
          <h2 className="text-3xl md:text-4xl font-extrabold text-slate-900 font-sans tracking-tight mb-4">
            Logistics Operations Without Digital Control
          </h2>
          <p className="text-slate-500 text-[15px] font-normal leading-relaxed max-w-2xl mx-auto">
            Traditional management creates operational chaos, hidden costs, and customer dissatisfaction.
          </p>
        </div>

        {/* Problems Grid */}
        <motion.div 
          className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 w-full"
          variants={containerVariants}
          initial="hidden"
          whileInView="visible"
          viewport={{ once: true, margin: "-100px" }}
        >
          {problemsData.map((item) => (
            <motion.div
              key={item.title}
              variants={cardVariants}
              whileHover={{ y: -6, boxShadow: "0 15px 30px -10px rgba(15, 23, 42, 0.05)" }}
              className="bg-white border border-slate-200 rounded-2xl p-6 flex items-start gap-4 transition-all duration-300 shadow-sm shadow-slate-100/50 cursor-pointer text-left group"
            >
              {/* Icon */}
              <div className="w-10 h-10 rounded-xl bg-red-50/50 border border-red-100/40 flex items-center justify-center flex-shrink-0 transition-colors duration-300 group-hover:bg-red-100/70 group-hover:border-red-200/50">
                <item.icon className="w-5 h-5 text-red-600" />
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
