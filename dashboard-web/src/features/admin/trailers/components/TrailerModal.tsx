import { useEffect, useRef } from 'react';
import { AnimatePresence, motion } from 'framer-motion';
import { Truck } from 'lucide-react';
import TrailerForm from './TrailerForm';
import type { TrailerFormData, TrailerPayload, TrailerValidationErrors } from '../types/trailer.types';
import type { LookupOption } from './TrailerForm';

interface TrailerModalProps {
  isOpen: boolean;
  title: string;
  description: string;
  formData: TrailerFormData;
  errors: TrailerValidationErrors;
  isSubmitting: boolean;
  submitLabel: string;
  trailerTypeOptions: LookupOption[];
  statusOptions: LookupOption[];
  onChange: (formData: TrailerFormData) => void;
  onSubmit: (payload: TrailerPayload) => void;
  onClose: () => void;
}

const focusableSelector = 'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])';

export default function TrailerModal({
  isOpen,
  title,
  description,
  formData,
  errors,
  isSubmitting,
  submitLabel,
  trailerTypeOptions,
  statusOptions,
  onChange,
  onSubmit,
  onClose,
}: TrailerModalProps) {
  const panelRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    if (!isOpen) {
      return;
    }

    const handleKeyDown = (event: KeyboardEvent) => {
      if (event.key === 'Escape' && !isSubmitting) {
        onClose();
      }

      if (event.key !== 'Tab' || !panelRef.current) {
        return;
      }

      const focusable = Array.from(panelRef.current.querySelectorAll<HTMLElement>(focusableSelector))
        .filter((element) => !element.hasAttribute('disabled'));
      const first = focusable[0];
      const last = focusable[focusable.length - 1];

      if (!first || !last) {
        return;
      }

      if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
      } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
      }
    };

    document.addEventListener('keydown', handleKeyDown);
    return () => document.removeEventListener('keydown', handleKeyDown);
  }, [isOpen, isSubmitting, onClose]);

  return (
    <AnimatePresence>
      {isOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-label={title}>
          <motion.div
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            exit={{ opacity: 0 }}
            className="absolute inset-0 bg-slate-950/80 backdrop-blur-sm"
            onClick={() => !isSubmitting && onClose()}
          />

          <motion.div
            ref={panelRef}
            initial={{ opacity: 0, y: 18, scale: 0.98 }}
            animate={{ opacity: 1, y: 0, scale: 1 }}
            exit={{ opacity: 0, y: 12, scale: 0.98 }}
            transition={{ duration: 0.22 }}
            className="relative z-10 flex max-h-[90vh] w-full max-w-5xl flex-col overflow-hidden rounded-2xl border border-white/10 bg-[#0E1320] shadow-2xl"
          >
            <div className="flex items-start justify-between gap-4 border-b border-white/5 px-6 py-5">
              <div className="flex items-start gap-3">
                <span className="rounded-xl border border-secondary/20 bg-secondary/10 p-2 text-accent">
                  <Truck className="h-5 w-5" />
                </span>
                <div>
                  <h2 className="text-lg font-bold text-white">{title}</h2>
                  <p className="mt-1 text-xs leading-5 text-slate-500">{description}</p>
                </div>
              </div>
              <button
                type="button"
                onClick={onClose}
                disabled={isSubmitting}
                className="rounded-xl p-2 text-slate-500 transition-colors hover:bg-white/5 hover:text-white disabled:cursor-not-allowed disabled:opacity-40"
                aria-label="Close trailer modal"
              >
                <span className="text-xl">×</span>
              </button>
            </div>

            <div className="overflow-y-auto px-6 py-5">
              <TrailerForm
                formData={formData}
                errors={errors}
                isSubmitting={isSubmitting}
                submitLabel={submitLabel}
                trailerTypeOptions={trailerTypeOptions}
                statusOptions={statusOptions}
                onChange={onChange}
                onSubmit={onSubmit}
                onCancel={onClose}
              />
            </div>
          </motion.div>
        </div>
      )}
    </AnimatePresence>
  );
}
