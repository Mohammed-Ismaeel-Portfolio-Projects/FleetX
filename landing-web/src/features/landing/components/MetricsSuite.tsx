import { motion } from 'framer-motion';

interface MetricItem {
  value: string;
  label: string;
  description: string;
}

const metricsData: MetricItem[] = [
  {
    value: '99.9%',
    label: 'Tracking Accuracy',
    description: 'Real-time GPS precision for complete shipment visibility.',
  },
  {
    value: '25%',
    label: 'Delay Reduction',
    description: 'AI optimization cuts delivery delays by a quarter.',
  },
  {
    value: '30%',
    label: 'Fleet Utilization',
    description: 'Improved vehicle efficiency and resource allocation.',
  },
  {
    value: '18%',
    label: 'Cost Savings',
    description: 'Reduced operational expenses through smart routing.',
  },
];

export default function MetricsSuite() {
  const containerVariants = {
    hidden: { opacity: 0 },
    visible: {
      opacity: 1,
      transition: {
        staggerChildren: 0.12,
      },
    },
  };

  const itemVariants = {
    hidden: { opacity: 0, y: 15 },
    visible: {
      opacity: 1,
      y: 0,
      transition: { duration: 0.6, ease: [0.16, 1, 0.3, 1] as const },
    },
  };

  return (
    <section id="performance" className="py-20 md:py-24 bg-[#050b17] text-white w-full overflow-hidden relative scroll-mt-20">
      {/* Subtle lines */}
      <div className="absolute top-0 inset-x-0 h-px bg-white/5" />
      <div className="absolute bottom-0 inset-x-0 h-px bg-white/5" />

      <div className="max-w-7xl mx-auto px-6 md:px-16 w-full">
        {/* Header */}
        <div className="text-center mb-16">
          <h2 className="text-3xl md:text-4xl font-extrabold text-white font-sans tracking-tight mb-3">
            Proven Business Impact
          </h2>
          <p className="text-slate-400 text-[15px] font-normal leading-relaxed max-w-2xl mx-auto">
            Measurable results from companies using FleetX to optimize operations.
          </p>
        </div>

        {/* Metrics Grid */}
        <motion.div 
          className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-8 md:gap-12"
          variants={containerVariants}
          initial="hidden"
          whileInView="visible"
          viewport={{ once: true, margin: "-50px" }}
        >
          {metricsData.map((metric) => (
            <motion.div 
              key={metric.label}
              variants={itemVariants}
              className="flex flex-col items-center lg:items-start text-center lg:text-left select-none"
            >
              {/* Value */}
              <span className="text-4xl md:text-[46px] font-extrabold text-white tracking-tight leading-none mb-3 font-sans">
                {metric.value}
              </span>
              
              {/* Label */}
              <span className="text-[9px] font-bold text-amber-500 uppercase tracking-widest font-sans mb-2 block">
                {metric.label}
              </span>
              
              {/* Description */}
              <p className="text-slate-400 text-[12.5px] font-normal leading-relaxed max-w-[200px] font-sans">
                {metric.description}
              </p>
            </motion.div>
          ))}
        </motion.div>
      </div>
    </section>
  );
}
