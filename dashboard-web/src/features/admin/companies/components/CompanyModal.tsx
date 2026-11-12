import { useEffect, useRef } from 'react';
import { AnimatePresence, motion } from 'framer-motion';
import { Building2, X } from 'lucide-react';
import CompanyForm from './CompanyForm';
import type { CompanyFormData, CompanyPayload, CompanyValidationErrors } from '../types/company.types';

interface CompanyModalProps {
  isOpen: boolean;
  title: string;
  description: string;
  formData: CompanyFormData;
  errors: CompanyValidationErrors;
  isSubmitting: boolean;
  submitLabel: string;
  formAlert?: string | null;
  icon?: React.ComponentType<{ className?: string }>;
  onChange: (formData: CompanyFormData) => void;
  onSubmit: (payload: CompanyPayload) => void;
  onClose: () => void;
}

const focusableSelector = 'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])';

export default function CompanyModal({
  isOpen,
  title,
  description,
  formData,
  errors,
  isSubmitting,
  submitLabel,
  formAlert,
  icon: Icon = Building2,
  onChange,
  onSubmit,
  onClose,
}: CompanyModalProps) {
  const panelRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    if (!isOpen) {
      return;
    }

    document.body.style.overflow = 'hidden';

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
    return () => {
      document.body.style.overflow = '';
      document.removeEventListener('keydown', handleKeyDown);
    };
  }, [isOpen, isSubmitting, onClose]);

  return (
    <AnimatePresence>
      {isOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-label={title}>
          <motion.div
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            exit={{ opacity: 0 }}
            className="absolute inset-0 bg-black/70 backdrop-blur-sm"
            onClick={() => !isSubmitting && onClose()}
          />

          <motion.div
            ref={panelRef}
            initial={{ opacity: 0, y: 18, scale: 0.98 }}
            animate={{ opacity: 1, y: 0, scale: 1 }}
            exit={{ opacity: 0, y: 12, scale: 0.98 }}
            transition={{ duration: 0.22 }}
            className="relative z-10 flex max-h-[calc(100vh-2rem)] w-full max-w-4xl flex-col overflow-hidden rounded-2xl border border-white/10 bg-[#0E1320] shadow-2xl"
          >
            <div className="flex items-start justify-between gap-4 border-b border-white/10 px-6 py-5 bg-[#090D16] shrink-0">
              <div className="flex items-start gap-3">
                <span className="rounded-xl border border-[#0250C5]/20 bg-[#0250C5]/10 p-2 text-[#0250C5]">
                  <Icon className="h-5 w-5" />
                </span>
                <div>
                  <h2 className="text-lg font-bold text-white">{title}</h2>
                  <p className="mt-1 text-xs leading-5 text-slate-400">{description}</p>
                </div>
              </div>
              <button
                type="button"
                onClick={onClose}
                disabled={isSubmitting}
                className="rounded-xl p-2 text-slate-400 transition-colors hover:bg-white/5 hover:text-white disabled:cursor-not-allowed disabled:opacity-40 cursor-pointer"
                aria-label="Close company modal"
              >
                <X className="h-5 w-5" />
              </button>
            </div>

            <div className="flex-1 overflow-y-auto px-6 py-5 min-h-0 scrollbar-thin scrollbar-thumb-white/10">
              {formAlert && (
                <div className="mb-4 rounded-xl border border-rose-500/20 bg-rose-500/10 p-3.5 text-xs text-rose-300 font-medium">
                  {formAlert}
                </div>
              )}
              <CompanyForm
                formData={formData}
                errors={errors}
                isSubmitting={isSubmitting}
                submitLabel={submitLabel}
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
