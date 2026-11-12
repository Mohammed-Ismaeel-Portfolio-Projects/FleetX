import { useEffect, useRef } from 'react';
import { AnimatePresence, motion } from 'framer-motion';
import { AlertTriangle } from 'lucide-react';

interface DeleteDialogProps {
  isOpen: boolean;
  title: string;
  message: string;
  confirmLabel: string;
  tone?: 'danger' | 'restore';
  isSubmitting: boolean;
  onCancel: () => void;
  onConfirm: () => void;
}

export default function DeleteDialog({
  isOpen,
  title,
  message,
  confirmLabel,
  tone = 'danger',
  isSubmitting,
  onCancel,
  onConfirm,
}: DeleteDialogProps) {
  const cancelRef = useRef<HTMLButtonElement>(null);

  useEffect(() => {
    if (!isOpen) {
      return;
    }

    cancelRef.current?.focus();
    const handleKeyDown = (event: KeyboardEvent) => {
      if (event.key === 'Escape' && !isSubmitting) {
        onCancel();
      }
    };

    document.addEventListener('keydown', handleKeyDown);
    return () => document.removeEventListener('keydown', handleKeyDown);
  }, [isOpen, isSubmitting, onCancel]);

  const confirmClass = tone === 'danger'
    ? 'bg-red-500 text-white hover:bg-red-600'
    : 'bg-emerald-500 text-white hover:bg-emerald-600';

  return (
    <AnimatePresence>
      {isOpen && (
        <div className="fixed inset-0 z-[60] flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-label={title}>
          <motion.div
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            exit={{ opacity: 0 }}
            className="absolute inset-0 bg-slate-950/80 backdrop-blur-sm"
            onClick={() => !isSubmitting && onCancel()}
          />
          <motion.div
            initial={{ opacity: 0, scale: 0.96, y: 12 }}
            animate={{ opacity: 1, scale: 1, y: 0 }}
            exit={{ opacity: 0, scale: 0.96, y: 12 }}
            className="relative z-10 w-full max-w-md rounded-2xl border border-white/10 bg-[#0E1320] p-6 shadow-2xl"
          >
            <div className="flex items-start gap-4">
              <span className="rounded-xl border border-amber-500/20 bg-amber-500/10 p-2 text-amber-300">
                <AlertTriangle className="h-5 w-5" />
              </span>
              <div>
                <h2 className="text-base font-bold text-white">{title}</h2>
                <p className="mt-2 text-sm leading-6 text-slate-400">{message}</p>
              </div>
            </div>
            <div className="mt-6 flex justify-end gap-3">
              <button
                ref={cancelRef}
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
                className={`inline-flex items-center gap-2 rounded-xl px-4 py-2.5 text-sm font-bold transition-colors disabled:cursor-not-allowed disabled:opacity-60 ${confirmClass}`}>
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
