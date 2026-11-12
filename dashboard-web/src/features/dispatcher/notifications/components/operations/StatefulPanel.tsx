import { AlertCircle, Loader2, SearchX } from 'lucide-react';

interface StatefulPanelProps {
  state: 'loading' | 'ready' | 'empty' | 'error';
  children: React.ReactNode;
  errorMessage?: string | null;
  onRetry?: () => void;
}

export default function StatefulPanel({
  state,
  children,
  errorMessage,
  onRetry,
}: StatefulPanelProps) {
  if (state === 'loading') {
    return (
      <div className="flex min-h-70 flex-col items-center justify-center rounded-3xl border border-slate-800/80 bg-slate-950/70 p-6 text-center text-slate-400 shadow-lg shadow-black/10">
        <Loader2 className="mb-3 h-8 w-8 animate-spin text-sky-400" />
        <p className="font-medium text-white">Loading issues</p>
        <p className="mt-1 text-sm">Fetching Issues from the operations API.</p>
      </div>
    );
  }

  if (state === 'empty') {
    return (
      <div className="flex min-h-70 flex-col items-center justify-center rounded-3xl border border-slate-800/80 bg-slate-950/70 p-6 text-center text-slate-400 shadow-lg shadow-black/10">
        <SearchX className="mb-3 h-8 w-8 text-slate-500" />
        <p className="font-medium text-white">No issues available</p>
        <p className="mt-1 text-sm">No Issues records were returned for the current query.</p>
      </div>
    );
  }

  if (state === 'error') {
    return (
      <div className="flex min-h-70 flex-col items-center justify-center rounded-3xl border border-slate-800/80 bg-slate-950/70 p-6 text-center text-slate-400 shadow-lg shadow-black/10">
        <AlertCircle className="mb-3 h-8 w-8 text-red-400" />
        <p className="font-medium text-white">Unable to load issues</p>
        <p className="mt-1 text-sm">
          {errorMessage?.trim() || 'The Issues API request failed.'}
        </p>
        {onRetry ? (
          <button
            type="button"
            onClick={onRetry}
            className="mt-4 rounded-xl border border-slate-700 bg-slate-900/80 px-4 py-2 text-sm font-semibold text-slate-200 hover:border-slate-500"
          >
            Retry
          </button>
        ) : null}
      </div>
    );
  }

  return <>{children}</>;
}
