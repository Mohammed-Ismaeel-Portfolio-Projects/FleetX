import { AnimatePresence, motion } from 'framer-motion';
import { Trash2 } from 'lucide-react';

interface DeleteDialogProps {
  isOpen: boolean;
  title: string;
  message: string;
  confirmLabel: string;
  isSubmitting: boolean;
  onConfirm: () => void;
  onCancel: () => void;
}

export default function DeleteDialog({
  isOpen,
  title,
  message,
  confirmLabel,
  isSubmitting,
  onConfirm,
  onCancel,
}: DeleteDialogProps) {
  return (
    <AnimatePresence>
      {isOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="delete-dialog-title">
          <motion.div
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            exit={{ opacity: 0 }}
            className="absolute inset-0 bg-slate-950/80 backdrop-blur-sm"
            onClick={onCancel}
          />

          <motion.div
            initial={{ opacity: 0, y: 18, scale: 0.98 }}
            animate={{ opacity: 1, y: 0, scale: 1 }}
            exit={{ opacity: 0, y: 12, scale: 0.98 }}
            transition={{ duration: 0.22 }}
            className="relative z-10 w-full max-w-lg rounded-2xl border border-white/10 bg-[#0E1320] p-6 shadow-2xl"
          >
            <div className="flex items-center gap-3 text-white">
              <span className="rounded-xl border border-red-500/20 bg-red-500/10 p-2 text-red-300">
                <Trash2 className="h-5 w-5" />
              </span>
              <div>
                <h2 id="delete-dialog-title" className="text-lg font-bold">{title}</h2>
                <p className="mt-1 text-sm text-slate-400">{message}</p>
              </div>
            </div>

            <div className="mt-6 flex flex-col gap-3 sm:flex-row sm:justify-end">
              <button
                type="button"
                onClick={onCancel}
                disabled={isSubmitting}
                className="rounded-xl border border-white/10 px-4 py-2.5 text-sm font-bold text-slate-300 transition-colors hover:bg-white/5 hover:text-white disabled:cursor-not-allowed disabled:opacity-50"
              >
                Cancel
              </button>
              <button
                type="button"
                onClick={onConfirm}
                disabled={isSubmitting}
                className="inline-flex items-center justify-center gap-2 rounded-xl bg-red-500 px-4 py-2.5 text-sm font-bold text-white transition-colors hover:bg-red-600 disabled:cursor-not-allowed disabled:opacity-60"
              >
                {isSubmitting && <span className="h-4 w-4 rounded-full border-2 border-white/40 border-t-transparent animate-spin" />}
                {confirmLabel}
              </button>
            </div>
          </motion.div>
        </div>
      )}
    </AnimatePresence>
  );
}
