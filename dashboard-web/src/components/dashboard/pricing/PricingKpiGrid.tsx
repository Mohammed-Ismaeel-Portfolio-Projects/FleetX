import { motion } from 'framer-motion';
import PricingKpiCard from './PricingKpiCard';

export default function PricingKpiGrid() {
  const containerVariants = {
    hidden: { opacity: 0 },
    visible: {
      opacity: 1,
      transition: {
        staggerChildren: 0.05,
      },
    },
  };

  const itemVariants = {
    hidden: { opacity: 0, y: 10 },
    visible: {
      opacity: 1,
      y: 0,
      transition: { duration: 0.45, ease: [0.16, 1, 0.3, 1] as const },
    },
  };

  return (
    <motion.div
      variants={containerVariants}
      initial="hidden"
      animate="visible"
      className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4 w-full"
    >
      {/* 1. Avg Cost / Shipment */}
      <motion.div variants={itemVariants}>
        <PricingKpiCard
          label="Avg Cost/Shipment"
          value="$42.18"
          trend="+2.4%"
          status="negative"
          sparklinePoints={[40, 41, 39, 41, 42, 41, 42.18]}
        />
      </motion.div>

      {/* 2. Total Revenue */}
      <motion.div variants={itemVariants}>
        <PricingKpiCard
          label="Total Revenue"
          value="$2.8M"
          trend="+14.2%"
          status="positive"
          sparklinePoints={[2.2, 2.3, 2.4, 2.5, 2.6, 2.7, 2.8]}
        />
      </motion.div>

      {/* 3. Profit Margin % */}
      <motion.div variants={itemVariants}>
        <PricingKpiCard
          label="Profit Margin %"
          value="28.4%"
          trend="+1.8%"
          status="positive"
          sparklinePoints={[26.5, 27.0, 26.8, 27.5, 28.0, 27.9, 28.4]}
        />
      </motion.div>

      {/* 4. Cost Efficiency */}
      <motion.div variants={itemVariants}>
        <PricingKpiCard
          label="Cost Efficiency"
          value="94.1"
          trend="+3.1%"
          status="positive"
          sparklinePoints={[90, 91, 91.5, 92, 93, 93.8, 94.1]}
        />
      </motion.div>

      {/* 5. AI Opt. Impact */}
      <motion.div variants={itemVariants}>
        <PricingKpiCard
          label="AI Opt. Impact"
          value="+$424k"
          trend="Optimizing..."
          status="neutral"
          sparklinePoints={[400, 410, 415, 420, 418, 422, 424]}
        />
      </motion.div>

      {/* 6. Discount Usage */}
      <motion.div variants={itemVariants}>
        <PricingKpiCard
          label="Discount Usage"
          value="12.5%"
          trend="-0.8%"
          status="negative"
          sparklinePoints={[14.0, 13.8, 13.5, 13.0, 12.8, 12.6, 12.5]}
        />
      </motion.div>
    </motion.div>
  );
}
