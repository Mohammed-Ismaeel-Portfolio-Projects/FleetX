import { useEffect, useState } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import { Bell, AlertTriangle } from 'lucide-react';
import { Link } from 'react-router-dom';
import {
  issuesService,
  IssueApiError,
  type Issue,
} from '../../../features/dispatcher/dashboard/services/issues.service';

function isOpenIssue(issue: Issue): boolean {
  const statusName = issue.status?.name?.trim().toLowerCase();
  return statusName !== 'resolved';
}

export default function Notifications() {
  const [isOpen, setIsOpen] = useState(false);
  const [issues, setIssues] = useState<Issue[]>([]);
  const [isLoading, setIsLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    let cancelled = false;

    async function load() {
      setIsLoading(true);
      setError(null);
      try {
        const result = await issuesService.getIssues({
          page: 1,
          per_page: 8,
          sort_by: 'created_at',
          sort_direction: 'desc',
        });
        if (cancelled) return;
        setIssues(Array.isArray(result.data) ? result.data : []);
      } catch (err) {
        if (cancelled) return;
        setIssues([]);
        setError(err instanceof IssueApiError ? err.message : 'Failed to load issues');
      } finally {
        if (!cancelled) {
          setIsLoading(false);
        }
      }
    }

    void load();
    return () => {
      cancelled = true;
    };
  }, []);

  const openIssues = issues.filter(isOpenIssue);
  const unreadCount = error ? 0 : openIssues.length;

  return (
    <div className="relative">
      <button
        onClick={() => setIsOpen(!isOpen)}
        className="p-2 text-slate-400 hover:text-white rounded-full hover:bg-white/5 transition-colors duration-200 cursor-pointer relative"
        aria-label="Toggle issues"
      >
        <Bell className="w-5 h-5" />
        {unreadCount > 0 && (
          <span className="absolute top-1.5 right-1.5 w-2 h-2 bg-red-500 rounded-full ring-2 ring-[#0B0F19]" />
        )}
      </button>

      {isOpen && (
        <div
          className="fixed inset-0 z-40 cursor-default"
          onClick={() => setIsOpen(false)}
        />
      )}

      <AnimatePresence>
        {isOpen && (
          <motion.div
            initial={{ opacity: 0, y: 10, scale: 0.95 }}
            animate={{ opacity: 1, y: 0, scale: 1 }}
            exit={{ opacity: 0, y: 10, scale: 0.95 }}
            transition={{ duration: 0.2, ease: [0.16, 1, 0.3, 1] }}
            className="absolute right-0 mt-3 w-80 bg-[#121724] border border-white/5 rounded-2xl shadow-2xl z-50 overflow-hidden text-left"
          >
            <div className="px-5 py-4 border-b border-white/5 flex items-center justify-between">
              <span className="text-xs font-bold text-white tracking-wider uppercase font-sans">
                Open issues
              </span>
              {!error && unreadCount > 0 && (
                <span className="text-[9px] font-bold text-[#0250C5] bg-[#0250C5]/10 border border-[#0250C5]/20 px-2 py-0.5 rounded-full uppercase">
                  {unreadCount} open
                </span>
              )}
            </div>

            <div className="flex flex-col max-h-[320px] overflow-y-auto divide-y divide-white/5">
              {isLoading && (
                <div className="px-5 py-6 text-xs text-slate-400">Loading issues…</div>
              )}

              {!isLoading && error && (
                <div className="px-5 py-6 text-xs text-amber-200">
                  Issues unavailable. {error}
                </div>
              )}

              {!isLoading && !error && openIssues.length === 0 && (
                <div className="px-5 py-8 text-center">
                  <Bell className="mx-auto mb-2 h-8 w-8 text-slate-600" />
                  <p className="text-xs font-semibold text-slate-300">No open issues</p>
                  <p className="mt-1 text-[10px] text-slate-500">
                    The bell only shows records from GET /issues.
                  </p>
                </div>
              )}

              {!isLoading && !error && openIssues.map((issue) => (
                <div
                  key={issue.id}
                  className="px-5 py-4 flex items-start gap-3"
                >
                  <div className="w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0 border bg-orange-500/10 border-orange-500/20">
                    <AlertTriangle className="w-4 h-4 text-orange-400" />
                  </div>
                  <div className="flex flex-col gap-0.5 min-w-0">
                    <span className="text-xs font-semibold text-slate-200 font-sans leading-normal">
                      {issue.type ? `${issue.type}: ` : ''}
                      {issue.description || `Issue #${issue.id}`}
                    </span>
                    <span className="text-[10px] text-slate-500 font-sans">
                      {issue.status?.name || 'Status unavailable'}
                      {issue.created_at ? ` · ${issue.created_at}` : ''}
                    </span>
                  </div>
                </div>
              ))}
            </div>

            <div className="px-5 py-3.5 bg-slate-900/40 border-t border-white/5 text-center">
              <Link
                to="/admin/notifications"
                onClick={() => setIsOpen(false)}
                className="text-[11px] font-bold text-[#0250C5] hover:text-[#0244a5] tracking-wider uppercase"
              >
                View all issues
              </Link>
            </div>
          </motion.div>
        )}
      </AnimatePresence>
    </div>
  );
}
