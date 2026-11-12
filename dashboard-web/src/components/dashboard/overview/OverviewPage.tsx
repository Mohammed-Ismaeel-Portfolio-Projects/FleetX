import { motion } from 'framer-motion';
import KpiGrid from './KpiGrid';
import AlertsWidget from './AlertsWidget';
import PerformanceWidget from './PerformanceWidget';
import ShipmentsTable from './ShipmentsTable';

export default function OverviewPage() {
  const containerVariants = {
    hidden: { opacity: 0 },
    visible: {
      opacity: 1,
      transition: {
        staggerChildren: 0.08,
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
    <motion.div
      variants={containerVariants}
      initial="hidden"
      animate="visible"
      className="flex flex-col gap-6 w-full text-left"
    >
      <motion.div variants={itemVariants} className="flex flex-col gap-1.5 mb-2 select-none">
        <h1 className="text-2xl font-bold tracking-tight text-white font-sans">
          Platform Overview
        </h1>
        <p className="text-slate-500 text-xs font-normal font-sans">
          Enterprise governance view — platform health, tenant activity, fleet and shipment oversight.
        </p>
      </motion.div>

      <motion.div variants={itemVariants} className="w-full">
        <KpiGrid />
      </motion.div>

      <motion.div
        variants={itemVariants}
        className="grid grid-cols-1 lg:grid-cols-2 gap-6 w-full items-stretch"
      >
        <AlertsWidget />
        <PerformanceWidget />
      </motion.div>

      <motion.div variants={itemVariants} className="w-full">
        <ShipmentsTable />
      </motion.div>
    </motion.div>
  );
}
