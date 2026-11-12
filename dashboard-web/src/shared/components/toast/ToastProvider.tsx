import { createContext, useCallback, useContext, useMemo, useState } from 'react';
import { AnimatePresence, motion } from 'framer-motion';
import { AlertTriangle, CheckCircle2, Info, X, XCircle } from 'lucide-react';

type ToastType = 'success' | 'error' | 'warning' | 'info';

interface Toast {
  id: string;
  type: ToastType;
  title: string;
  message?: string;
}

interface ToastContextValue {
  success: (title: string, message?: string) => void;
  error: (title: string, message?: string) => void;
  warning: (title: string, message?: string) => void;
  info: (title: string, message?: string) => void;
}

const ToastContext = createContext<ToastContextValue | null>(null);

const toastStyles: Record<ToastType, string> = {
  success: 'border-emerald-500/25 bg-emerald-500/10 text-emerald-300',
  error: 'border-red-500/25 bg-red-500/10 text-red-300',
  warning: 'border-amber-500/25 bg-amber-500/10 text-amber-300',
  info: 'border-sky-500/25 bg-sky-500/10 text-sky-300',
};

const toastIcons = {
  success: CheckCircle2,
  error: XCircle,
  warning: AlertTriangle,
  info: Info,
};

export function ToastProvider({ children }: { children: React.ReactNode }) {
  const [toasts, setToasts] = useState<Toast[]>([]);

  const dismiss = useCallback((id: string) => {
    setToasts((current) => current.filter((toast) => toast.id !== id));
  }, []);

  const push = useCallback((type: ToastType, title: string, message?: string) => {
    const id = crypto.randomUUID();
    setToasts((current) => [...current, { id, type, title, message }]);
    window.setTimeout(() => dismiss(id), 4200);
  }, [dismiss]);

  const value = useMemo<ToastContextValue>(() => ({
    success: (title, message) => push('success', title, message),
    error: (title, message) => push('error', title, message),
    warning: (title, message) => push('warning', title, message),
    info: (title, message) => push('info', title, message),
  }), [push]);

  return (
    <ToastContext.Provider value={value}>
      {children}
      <div
        className="fixed right-4 top-4 z-[80] flex w-[calc(100vw-2rem)] max-w-sm flex-col gap-3"
        aria-live="polite"
        aria-label="FleetX notifications"
      >
        <AnimatePresence>
          {toasts.map((toast) => {
            const Icon = toastIcons[toast.type];

            return (
              <motion.div
                key={toast.id}
                initial={{ opacity: 0, y: -12, scale: 0.98 }}
                animate={{ opacity: 1, y: 0, scale: 1 }}
                exit={{ opacity: 0, y: -10, scale: 0.98 }}
                className="rounded-2xl border border-white/10 bg-[#0E1320]/95 p-4 shadow-2xl backdrop-blur-md"
                role="status"
              >
                <div className="flex items-start gap-3">
                  <span className={`mt-0.5 rounded-lg border p-1.5 ${toastStyles[toast.type]}`}>
                    <Icon className="h-4 w-4" />
                  </span>
                  <div className="min-w-0 flex-1">
                    <p className="text-sm font-bold text-white">{toast.title}</p>
                    {toast.message && (
                      <p className="mt-1 text-xs leading-5 text-slate-400">{toast.message}</p>
                    )}
                  </div>
                  <button
                    type="button"
                    onClick={() => dismiss(toast.id)}
                    className="rounded-lg p-1 text-slate-500 transition-colors hover:bg-white/5 hover:text-white"
                    aria-label="Dismiss notification"
                  >
                    <X className="h-4 w-4" />
                  </button>
                </div>
              </motion.div>
            );
          })}
        </AnimatePresence>
      </div>
    </ToastContext.Provider>
  );
}

export function useToast() {
  const context = useContext(ToastContext);

  if (!context) {
    throw new Error('useToast must be used within ToastProvider');
  }

  return context;
}
