import { motion } from 'framer-motion';
import { Check, ArrowUp, User, X } from 'lucide-react';

interface IncidentFooterProps {
  onClose?: () => void;
}

export default function IncidentFooter({ onClose }: IncidentFooterProps) {
  return (
    <motion.div
      initial={{ opacity: 0, y: 10 }}
      animate={{ opacity: 1, y: 0 }}
      transition={{ duration: 0.2 }}
      className="flex flex-col gap-3 pt-4 border-t border-white/10"
    >
      <p className="text-[11px] leading-relaxed text-slate-500">
        Resolve, escalate, and assign are unavailable. No incident-management mutation API is connected to this drawer.
      </p>
      <div className="flex items-center gap-2">
        <button
          type="button"
          disabled
          className="flex items-center gap-2 px-4 py-2 rounded-lg border border-white/10 bg-white/5 text-xs font-semibold text-slate-500 cursor-not-allowed"
        >
          <Check className="h-4 w-4" />
          <span>Resolve</span>
        </button>

        <button
          type="button"
          disabled
          className="flex items-center gap-2 px-4 py-2 rounded-lg border border-white/10 bg-white/5 text-xs font-semibold text-slate-500 cursor-not-allowed"
        >
          <ArrowUp className="h-4 w-4" />
          <span>Escalate</span>
        </button>

        <button
          type="button"
          disabled
          className="flex items-center gap-2 px-4 py-2 rounded-lg border border-white/10 bg-white/5 text-xs font-medium text-slate-500 cursor-not-allowed"
        >
          <User className="h-4 w-4" />
          <span>Assign</span>
        </button>

        <div className="flex-1" />

        <button
          type="button"
          onClick={onClose}
          className="flex items-center gap-2 px-4 py-2 rounded-lg border border-white/10 bg-white/5 text-xs font-medium text-slate-300 hover:bg-white/10 transition-all hover:scale-[1.01]"
        >
          <X className="h-4 w-4" />
          <span>Close</span>
        </button>
      </div>
    </motion.div>
  );
}
