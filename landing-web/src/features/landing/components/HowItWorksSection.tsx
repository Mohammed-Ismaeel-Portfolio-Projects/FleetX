import { motion } from 'framer-motion';
import { 
  FileText, 
  User, 
  Truck, 
  MapPin, 
  ShieldCheck, 
  CheckCircle, 
  CreditCard 
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';

interface WorkflowStep {
  step: number;
  icon: LucideIcon;
  title: string;
  description: string;
}

const workflowSteps: WorkflowStep[] = [
  {
    step: 1,
    icon: FileText,
    title: 'Client Creates Shipment',
    description: 'Submit cargo details, pickup location, and destination through the client portal.',
  },
  {
    step: 2,
    icon: User,
    title: 'Dispatcher Assigns Resources',
    description: 'AI-assisted assignment selects optimal driver and truck for the shipment.',
  },
  {
    step: 3,
    icon: Truck,
    title: 'System Generates Route Plan',
    description: 'Automated route optimization calculates the most efficient path.',
  },
  {
    step: 4,
    icon: MapPin,
    title: 'Shipment Activates',
    description: 'Status changes to IN TRANSIT and real-time GPS tracking begins.',
  },
  {
    step: 5,
    icon: ShieldCheck,
    title: 'Customs & Checkpoints',
    description: 'Cross-border documentation and checkpoint handling automated.',
  },
  {
    step: 6,
    icon: CheckCircle,
    title: 'Delivery Completed',
    description: 'Digital proof of delivery captured with signature and photos.',
  },
  {
    step: 7,
    icon: CreditCard,
    title: 'Invoice & Payment',
    description: 'Automated billing and payment processing triggered.',
  },
];

export default function HowItWorksSection() {
  const containerVariants = {
    hidden: { opacity: 0 },
    visible: {
      opacity: 1,
      transition: {
        staggerChildren: 0.1,
      },
    },
  };

  const stepVariants = {
    hidden: { opacity: 0, x: -20 },
    visible: {
      opacity: 1,
      x: 0,
      transition: { duration: 0.5, ease: [0.16, 1, 0.3, 1] as const },
    },
  };

  return (
    <section className="py-24 md:py-28 bg-[#f8fafc] border-t border-slate-100 w-full overflow-hidden">
      {/* Decorative ambient elements */}
      <div className="absolute top-0 right-0 w-[400px] h-[400px] bg-green-50/20 rounded-full blur-[100px] pointer-events-none" />
      <div className="absolute bottom-0 left-0 w-[400px] h-[400px] bg-emerald-50/20 rounded-full blur-[100px] pointer-events-none" />

      <div className="max-w-7xl mx-auto px-6 md:px-16 w-full relative z-10">
        
        {/* Header */}
        <div className="text-center mb-16">
          <h2 className="text-3xl md:text-4xl font-extrabold text-slate-900 font-sans tracking-tight mb-4">
            How It Works
          </h2>
          <p className="text-slate-500 text-[15px] font-normal leading-relaxed max-w-2xl mx-auto">
            Simple workflow from shipment to delivery, powered by AI automation.
          </p>
        </div>

        {/* Workflow Timeline */}
        <motion.div 
          className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 w-full"
          variants={containerVariants}
          initial="hidden"
          whileInView="visible"
          viewport={{ once: true, margin: "-100px" }}
        >
          {workflowSteps.map((step) => (
            <motion.div
              key={step.step}
              variants={stepVariants}
              whileHover={{ y: -4, boxShadow: "0 15px 30px -10px rgba(15, 23, 42, 0.05)" }}
              className="bg-white border border-slate-200 rounded-2xl p-6 flex items-start gap-4 transition-all duration-300 shadow-sm shadow-slate-100/50 cursor-pointer text-left group relative"
            >
              {/* Step Number Badge */}
              <div className="absolute top-4 right-4 w-8 h-8 rounded-full bg-[#0250C5] text-white text-xs font-bold flex items-center justify-center font-sans">
                {step.step}
              </div>

              {/* Icon */}
              <div className="w-12 h-12 rounded-xl bg-green-50/50 border border-green-100/40 flex items-center justify-center flex-shrink-0 transition-colors duration-300 group-hover:bg-green-100/70 group-hover:border-green-200/50">
                <step.icon className="w-6 h-6 text-green-600" />
              </div>
              
              {/* Text */}
              <div className="flex flex-col pr-10">
                <h3 className="font-bold text-slate-800 text-[15px] mb-1.5 tracking-tight font-sans">
                  {step.title}
                </h3>
                <p className="text-slate-400 text-[12px] font-normal leading-relaxed font-sans">
                  {step.description}
                </p>
              </div>
            </motion.div>
          ))}
        </motion.div>

        {/* Bottom Note */}
        <motion.div
          initial={{ opacity: 0, y: 20 }}
          whileInView={{ opacity: 1, y: 0 }}
          viewport={{ once: true }}
          transition={{ duration: 0.6, delay: 0.3 }}
          className="mt-12 text-center"
        >
          <p className="text-slate-500 text-sm font-medium">
            <span className="text-[#0250C5] font-semibold">AI-powered automation</span> handles 80% of manual tasks, reducing errors and accelerating operations.
          </p>
        </motion.div>

      </div>
    </section>
  );
}
