import { motion } from 'framer-motion';

export default function IncidentRecommendedActions() {
  return (
    <motion.div
      initial={{ opacity: 0, y: 10 }}
      animate={{ opacity: 1, y: 0 }}
      transition={{ duration: 0.2 }}
      className="rounded-lg border border-white/5 bg-white/[0.02] p-4"
    >
      <h3 className="text-[10px] font-semibold uppercase tracking-wider text-slate-500 mb-2">
        Recommended Actions
      </h3>
      <p className="text-xs leading-relaxed text-slate-500">
        Recommended actions are unavailable. This drawer is not connected to an incident-management API.
      </p>
    </motion.div>
  );
}
