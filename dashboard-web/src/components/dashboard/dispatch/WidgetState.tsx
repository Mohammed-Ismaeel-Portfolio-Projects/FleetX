import { motion } from 'framer-motion';
import { Loader2, AlertCircle, Inbox, RefreshCw } from 'lucide-react';

interface WidgetStateProps {
  state: 'loading' | 'empty' | 'error';
  message?: string;
  onRetry?: () => void;
}

export default function WidgetState({ state, message, onRetry }: WidgetStateProps) {
  if (state === 'loading') {
    return (
      <motion.div
        initial={{ opacity: 0 }}
        animate={{ opacity: 1 }}
        className="flex flex-col items-center justify-center py-10 px-4"
      >
        <Loader2 className="w-6 h-6 text-slate-400 animate-spin mb-3" />
        <p className="text-sm text-slate-400 font-medium">{message || 'Loading…'}</p>
      </motion.div>
    );
  }

  if (state === 'empty') {
    return (
      <motion.div
        initial={{ opacity: 0, y: 10 }}
        animate={{ opacity: 1, y: 0 }}
        className="flex flex-col items-center justify-center py-10 px-4"
      >
        <div className="w-10 h-10 rounded-full bg-white/5 flex items-center justify-center mb-3">
          <Inbox className="w-5 h-5 text-slate-500" />
        </div>
        <p className="text-sm text-slate-300 font-medium text-center">
          {message || 'Nothing to display.'}
        </p>
      </motion.div>
    );
  }

  if (state === 'error') {
    return (
      <motion.div
        initial={{ opacity: 0, y: 10 }}
        animate={{ opacity: 1, y: 0 }}
        className="flex flex-col items-center justify-center py-10 px-4"
      >
        <div className="w-10 h-10 rounded-full bg-red-500/10 flex items-center justify-center mb-3">
          <AlertCircle className="w-5 h-5 text-red-400" />
        </div>
        <p className="text-sm text-slate-300 font-medium mb-1">Unable to load this section</p>
        <p className="text-xs text-slate-500 mb-3 text-center">{message || 'Please try again.'}</p>
        {onRetry && (
          <button
            onClick={onRetry}
            className="flex items-center gap-2 px-4 py-2 bg-white/5 hover:bg-white/10 border border-white/10 rounded-lg transition-all text-xs font-semibold text-slate-300"
          >
            <RefreshCw className="w-3.5 h-3.5" />
            <span>Retry</span>
          </button>
        )}
      </motion.div>
    );
  }

  return null;
}
